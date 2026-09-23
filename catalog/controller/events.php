<?php
namespace Opencart\Catalog\Controller\Extension\CcTelegram;

require_once DIR_EXTENSION . 'cc_telegram/system/library/cc_telegram/settings.php';
require_once DIR_EXTENSION . 'cc_telegram/system/library/cc_telegram/notifier.php';
require_once DIR_EXTENSION . 'cc_telegram/system/library/cc_telegram/sms/customer_notifier.php';

use Opencart\System\Library\CcTelegram\Settings;
use Opencart\System\Library\CcTelegram\Notifier;
use Opencart\System\Library\CcTelegram\Sms\CustomerNotifier;

/**
 * Order events. Both handlers hang off the single point where an OpenCart order
 * gains a status — storefront checkout, payment callbacks and admin edits all
 * funnel through catalog/model/checkout/order.addHistory.
 */
class Events extends \Opencart\System\Engine\Controller {

	/**
	 * order_id => stack of the statuses the order held before each addHistory()
	 * still in progress. Static: the event system builds a new controller for
	 * every handler call. A stack rather than one slot, so an addHistory() made
	 * from inside another one's `after` handlers cannot overwrite the outer
	 * call's snapshot.
	 *
	 * @var array<int,int[]>
	 */
	private static array $previous = [];

	/**
	 * Trigger: catalog/model/checkout/order.addHistory/before
	 *
	 * The order row still holds the old status here. Reading it back from
	 * order_history afterwards is not reliable: other extensions (CRM and
	 * delivery connectors) append their own history rows with the same status
	 * in the same request, so "the second row from the top" was the new status
	 * again and the message read "Shipped → Shipped".
	 */
	public function orderHistoryBefore(&$route, &$args): void {
		$orderId = (int)($args[0] ?? 0);
		if ($orderId <= 0) {
			return;
		}

		$row = $this->db->query("SELECT `order_status_id` FROM `" . DB_PREFIX . "order`
			WHERE `order_id` = '" . $orderId . "' LIMIT 1")->row;

		self::$previous[$orderId][] = isset($row['order_status_id']) ? (int)$row['order_status_id'] : 0;
	}

	/**
	 * Trigger: catalog/model/checkout/order.addHistory/after
	 * args: [order_id, order_status_id, comment, notify, override]
	 */
	public function orderHistoryAdded(string &$route, array &$args, mixed &$output): void {
		$settings = new Settings($this->config);

		$orderId  = (int)($args[0] ?? 0);
		$statusId = (int)($args[1] ?? 0);
		if ($orderId <= 0 || $statusId <= 0) {
			return;
		}

		$previousId = $this->previousStatusId($orderId, $statusId, (string)($args[2] ?? ''));

		// Pro: the customer's Viber / SMS does not depend on the Telegram side
		// being switched on — a shop may want only one of the two.
		$this->customerMessage($settings, $args, $previousId);

		if (!$settings->isEnabled() || !$settings->isConfigured()) {
			return;
		}

		$this->load->model('checkout/order');
		$order = $this->model_checkout_order->getOrder($orderId);
		if (!$order) {
			return;
		}
		// getOrder() is read after the history row landed, so make sure the
		// status we report is the one this event is about.
		$order['order_status_id'] = $statusId;

		$notifier = new Notifier($this->registry, $settings);

		// A brand-new order landing in a trigger status announces itself as
		// "new" rather than as a status change — otherwise checkout would
		// produce two messages.
		if ($notifier->maybeNewOrder($order, $statusId)) {
			return;
		}

		// The same status written again (a payment callback, a comment added in
		// the admin) is not a change of status.
		if ($previousId === $statusId) {
			return;
		}

		$notifier->statusChanged($order, $previousId, $statusId);
	}

	/**
	 * Trigger: catalog/model/checkout/order.addHistory/after (second handler).
	 * OpenCart subtracts stock inside addHistory, so by the time this runs the
	 * product quantities already reflect the order.
	 */
	public function lowStock(string &$route, array &$args, mixed &$output): void {
		$settings = new Settings($this->config);
		if (!$settings->isEnabled() || !$settings->isConfigured()) {
			return;
		}
		if ((string)$settings->get('notify_low_stock', '0') !== '1') {
			return;
		}

		$orderId = (int)($args[0] ?? 0);
		if ($orderId <= 0) {
			return;
		}

		$threshold = (int)$settings->get('low_stock_threshold', 3);
		$notifier  = new Notifier($this->registry, $settings);
		$logger    = $notifier->logger();

		// HTTP_SERVER is the storefront URL in the catalog context; HTTP_CATALOG
		// only exists in the admin config and is undefined here.
		$storeUrl = defined('HTTP_SERVER') ? HTTP_SERVER : (string)$this->config->get('config_url');

		$rows = $this->db->query("SELECT p.`product_id`, p.`quantity`, p.`sku`, p.`model`, pd.`name`
			FROM `" . DB_PREFIX . "order_product` op
			INNER JOIN `" . DB_PREFIX . "product` p ON (p.`product_id` = op.`product_id`)
			LEFT JOIN `" . DB_PREFIX . "product_description` pd
				ON (pd.`product_id` = p.`product_id` AND pd.`language_id` = " . (int)$this->config->get('config_language_id') . ")
			WHERE op.`order_id` = " . (int)$orderId . "
			AND p.`subtract` = '1'
			AND p.`quantity` <= " . $threshold)->rows;

		foreach ($rows as $product) {
			$productId = (int)$product['product_id'];
			if ($logger->lowStockRecently($productId)) {
				continue;
			}
			$notifier->lowStock($product, $storeUrl);
		}
	}

	/**
	 * Viber / SMS to the customer for the status that just landed. Never lets an
	 * exception out: a gateway hiccup must not break checkout or the admin save.
	 */
	private function customerMessage(Settings $settings, array $args, int $previousId): void {
		$orderId  = (int)($args[0] ?? 0);
		$statusId = (int)($args[1] ?? 0);
		if ($orderId <= 0 || $statusId <= 0) {
			return;
		}

		try {
			$sms = new CustomerNotifier($this->registry);
			if (!$sms->isActive()) {
				return;
			}

			$this->load->model('checkout/order');
			$order = $this->model_checkout_order->getOrder($orderId);
			if (!$order) {
				return;
			}
			$order['order_status_id'] = $statusId;

			$formatter = (new Notifier($this->registry, $settings))->formatter();
			$sms->handle(
				$order,
				$statusId,
				$previousId,
				(string)($args[2] ?? ''),
				static function (array $order, string $template, array $extra) use ($formatter): string {
					return $formatter->customerText($order, $template, $extra);
				},
				static function (int $id) use ($formatter): string {
					return $formatter->orderStatusName($id);
				}
			);
		} catch (\Throwable $e) {
			$this->log->write('CatCode Telegram / SMS: ' . $e->getMessage());
		}
	}

	/**
	 * The status the order held before this addHistory() call; 0 for a new order.
	 *
	 * Normally the snapshot taken by orderHistoryBefore(). A shop that updated
	 * the files without opening the module settings yet has no `before` hook
	 * registered — then the history is read, but the row of THIS call is looked
	 * up by its status and comment instead of taking the second row from the
	 * top, which may be a row another extension appended afterwards.
	 */
	private function previousStatusId(int $orderId, int $statusId, string $comment): int {
		if (!empty(self::$previous[$orderId])) {
			$previous = (int)array_pop(self::$previous[$orderId]);

			if (!self::$previous[$orderId]) {
				unset(self::$previous[$orderId]);
			}

			return $previous;
		}

		$rows = $this->db->query("SELECT `order_status_id`, `comment` FROM `" . DB_PREFIX . "order_history`
			WHERE `order_id` = '" . (int)$orderId . "'
			ORDER BY `order_history_id` DESC LIMIT 50")->rows;

		foreach ($rows as $i => $row) {
			if ((int)$row['order_status_id'] === $statusId && trim((string)$row['comment']) === trim($comment)) {
				return isset($rows[$i + 1]) ? (int)$rows[$i + 1]['order_status_id'] : 0;
			}
		}

		return isset($rows[1]) ? (int)$rows[1]['order_status_id'] : 0;
	}
}
