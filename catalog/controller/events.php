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
	 * Trigger: catalog/model/checkout/order.addHistory/after
	 * args: [order_id, order_status_id, comment, notify, override]
	 */
	public function orderHistoryAdded(string &$route, array &$args, mixed &$output): void {
		$settings = new Settings($this->config);

		// Pro: the customer's Viber / SMS does not depend on the Telegram side
		// being switched on — a shop may want only one of the two.
		$this->customerMessage($settings, $args);

		if (!$settings->isEnabled() || !$settings->isConfigured()) {
			return;
		}

		$orderId  = (int)($args[0] ?? 0);
		$statusId = (int)($args[1] ?? 0);
		if ($orderId <= 0 || $statusId <= 0) {
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

		$notifier->statusChanged($order, $this->previousStatusId($orderId), $statusId);
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
	private function customerMessage(Settings $settings, array $args): void {
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
				$this->previousStatusId($orderId),
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
	 * The status the order held before the history row that just landed.
	 * 0 when this is the very first history entry.
	 */
	private function previousStatusId(int $orderId): int {
		$rows = $this->db->query("SELECT `order_status_id` FROM `" . DB_PREFIX . "order_history`
			WHERE `order_id` = " . (int)$orderId . "
			ORDER BY `order_history_id` DESC LIMIT 2")->rows;

		return isset($rows[1]) ? (int)$rows[1]['order_status_id'] : 0;
	}
}
