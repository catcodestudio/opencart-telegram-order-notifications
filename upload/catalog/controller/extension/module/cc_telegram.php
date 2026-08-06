<?php
require_once DIR_SYSTEM . 'library/cc_telegram.php';

/**
 * Telegram Order Notifications — catalog side (OpenCart 3.x).
 *
 * Wired to catalog/model/checkout/order/addOrderHistory (before + after). That
 * single model method is the funnel for everything worth announcing:
 *   • storefront checkout confirming an order (status 0 → real status),
 *   • payment-gateway callbacks,
 *   • admin order-status changes (admin/sale/order posts to the catalog API,
 *     which calls this very model).
 *
 * The `before` pass snapshots the previous status — by `after` the DB already
 * holds the new one and {order_status_old} would be unobtainable.
 */
class ControllerExtensionModuleCcTelegram extends Controller {

	/**
	 * order_id => previous order_status_id, captured in the `before` pass.
	 * Static so it survives across the two controller instantiations the event
	 * system creates within one request.
	 *
	 * @var array<int,int>
	 */
	public static $previous = array();

	/**
	 * @param string $route  Triggering route.
	 * @param array  $args   addOrderHistory($order_id, $order_status_id, $comment, $notify, $override)
	 */
	public function eventOrderHistoryBefore($route, &$args) {
		if (!$this->enabled() || !isset($args[0])) {
			return;
		}

		$order_id = (int)$args[0];
		$row      = $this->db->query("SELECT `order_status_id` FROM `" . DB_PREFIX . "order` WHERE `order_id` = '" . $order_id . "' LIMIT 1")->row;

		self::$previous[$order_id] = isset($row['order_status_id']) ? (int)$row['order_status_id'] : 0;
	}

	/**
	 * @param string $route  Triggering route.
	 * @param array  $args   addOrderHistory arguments.
	 * @param mixed  $output Model return value (unused).
	 */
	public function eventOrderHistoryAfter($route, &$args, &$output) {
		if (!$this->enabled() || !isset($args[0])) {
			return;
		}

		$order_id  = (int)$args[0];
		$new_id    = isset($args[1]) ? (int)$args[1] : 0;
		$old_id    = isset(self::$previous[$order_id]) ? (int)self::$previous[$order_id] : 0;

		if ($order_id <= 0 || $new_id <= 0) {
			return;
		}

		$this->load->language('extension/module/cc_telegram');
		$this->load->model('checkout/order');

		$order = $this->model_checkout_order->getOrder($order_id);
		if (!$order) {
			return;
		}

		// A brand-new order landing in a trigger status announces itself as "new"
		// rather than as a status change — otherwise confirmation produces two messages.
		if ($this->maybeNewOrder($order, $new_id)) {
			$this->maybeLowStock($order_id, $old_id);
			return;
		}

		$this->maybeStatusChange($order, $old_id, $new_id);
		$this->maybeLowStock($order_id, $old_id);
	}

	// ------------------------------------------------------------------ events

	/**
	 * Announce a new order once, when it first reaches a configured status.
	 *
	 * @return bool Whether the new-order message was sent by this call.
	 */
	private function maybeNewOrder(array $order, $new_id) {
		if (!$this->config->get('module_cc_telegram_notify_new_order')) {
			return false;
		}
		if (!in_array((int)$new_id, $this->statusList('new_order_statuses'), true)) {
			return false;
		}

		$order_id = (int)$order['order_id'];

		// Dedup: a `new_order` row for this order means it has already been announced.
		$seen = $this->db->query("SELECT `log_id` FROM `" . CcTelegramLogger::table() . "`
			WHERE `order_id` = '" . $order_id . "' AND `event` = 'new_order' LIMIT 1");
		if ($seen->num_rows) {
			return false;
		}

		$text = CcTelegramFormatter::render(
			$this->template('new_order'),
			$this->orderValues($order, 0)
		);
		if (trim($text) === '') {
			return false;
		}

		$this->broadcast($order_id, 'new_order', $text);
		return true;
	}

	private function maybeStatusChange(array $order, $old_id, $new_id) {
		if (!$this->config->get('module_cc_telegram_notify_status')) {
			return;
		}
		if ((int)$old_id === (int)$new_id) {
			return;
		}
		if (!in_array((int)$new_id, $this->statusList('status_statuses'), true)) {
			return;
		}

		$text = CcTelegramFormatter::render(
			$this->template('status'),
			$this->orderValues($order, $old_id)
		);
		if (trim($text) === '') {
			return;
		}

		$this->broadcast((int)$order['order_id'], 'status', $text);
	}

	/**
	 * OpenCart subtracts stock inside addOrderHistory, and only on the very
	 * first transition out of status 0 — so that is the one moment a product
	 * can newly cross the low-stock threshold because of this order.
	 */
	private function maybeLowStock($order_id, $old_id) {
		if (!$this->config->get('module_cc_telegram_notify_low_stock') || (int)$old_id !== 0) {
			return;
		}

		$threshold = (int)$this->config->get('module_cc_telegram_low_stock_qty');
		if ($threshold < 0) {
			return;
		}

		$rows = $this->db->query("SELECT DISTINCT op.product_id, op.name, p.model, p.quantity, p.subtract
			FROM `" . DB_PREFIX . "order_product` op
			INNER JOIN `" . DB_PREFIX . "product` p ON p.product_id = op.product_id
			WHERE op.order_id = '" . (int)$order_id . "'")->rows;

		$template = $this->template('low_stock');
		$logger   = new CcTelegramLogger($this->db);

		foreach ($rows as $r) {
			if (!(int)$r['subtract'] || (int)$r['quantity'] > $threshold) {
				continue;
			}

			// A product that stays under the threshold would otherwise generate a
			// notice per order — one per product per day is enough to act on.
			if ($logger->lowStockRecently((int)$r['product_id'])) {
				continue;
			}

			$text = CcTelegramFormatter::render($template, array(
				'site_name'    => (string)$this->config->get('config_name'),
				'product_name' => (string)$r['name'],
				'product_sku'  => (string)$r['model'],
				'stock_qty'    => (string)(int)$r['quantity'],
				'admin_url'    => CcTelegramFormatter::link(
					$this->adminUrl('catalog/product'),
					$this->language->get('text_open_product')
				),
			));
			if (trim($text) === '') {
				continue;
			}

			$this->broadcast(0, 'low_stock', $text, (int)$r['product_id']);
		}
	}

	// ------------------------------------------------------------- placeholders

	/**
	 * All order-derived placeholder values, still unescaped.
	 *
	 * @return array<string,string>
	 */
	private function orderValues(array $order, $old_id) {
		$order_id = (int)$order['order_id'];

		$name = trim((string)$order['firstname'] . ' ' . (string)$order['lastname']);
		if ($name === '') {
			$name = trim((string)$order['shipping_firstname'] . ' ' . (string)$order['shipping_lastname']);
		}

		// `order_product` already carries the model/SKU as sold — no product join needed.
		$products = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_product` WHERE `order_id` = '" . $order_id . "'")->rows;

		$controller = $this;
		$money      = function ($amount) use ($controller, $order) {
			return $controller->money($amount, $order);
		};

		return array(
			'site_name'        => (string)$this->config->get('config_name'),
			'order_number'     => '#' . $order_id,
			'order_id'         => (string)$order_id,
			'order_total'      => $this->money($order['total'], $order),
			'order_status'     => $this->statusName((int)$order['order_status_id']),
			'order_status_old' => (int)$old_id > 0 ? $this->statusName((int)$old_id) : '',
			'order_date'       => isset($order['date_added']) ? date('d.m.Y H:i', strtotime((string)$order['date_added'])) : '',
			'payment_method'   => (string)$order['payment_method'],
			'shipping_method'  => (string)$order['shipping_method'],
			'shipping_total'   => $this->money($this->shippingTotal($order_id), $order),
			'customer_name'    => $name,
			'customer_phone'   => (string)$order['telephone'],
			'customer_email'   => (string)$order['email'],
			'customer_note'    => (string)$order['comment'],
			'billing_address'  => CcTelegramFormatter::flatten($this->address($order, 'payment')),
			'shipping_address' => CcTelegramFormatter::flatten($this->address($order, 'shipping')),
			'items'            => CcTelegramFormatter::items($products, $money),
			'items_count'      => (string)count($products),
			'admin_url'        => CcTelegramFormatter::link(
				$this->adminUrl('sale/order/info&order_id=' . $order_id),
				$this->language->get('text_open_order')
			),
		);
	}

	/**
	 * Public so the {items} money closure can reach it.
	 *
	 * @return string
	 */
	public function money($amount, array $order) {
		$formatted = $this->currency->format(
			(float)$amount,
			(string)$order['currency_code'],
			(float)$order['currency_value']
		);

		return trim(html_entity_decode(strip_tags((string)$formatted), ENT_QUOTES, 'UTF-8'));
	}

	private function shippingTotal($order_id) {
		$row = $this->db->query("SELECT `value` FROM `" . DB_PREFIX . "order_total`
			WHERE `order_id` = '" . (int)$order_id . "' AND `code` = 'shipping' LIMIT 1")->row;

		return isset($row['value']) ? (float)$row['value'] : 0.0;
	}

	/**
	 * @param string $kind 'payment' or 'shipping'.
	 */
	private function address(array $order, $kind) {
		$parts = array(
			isset($order[$kind . '_postcode']) ? $order[$kind . '_postcode'] : '',
			isset($order[$kind . '_country']) ? $order[$kind . '_country'] : '',
			isset($order[$kind . '_zone']) ? $order[$kind . '_zone'] : '',
			isset($order[$kind . '_city']) ? $order[$kind . '_city'] : '',
			isset($order[$kind . '_address_1']) ? $order[$kind . '_address_1'] : '',
			isset($order[$kind . '_address_2']) ? $order[$kind . '_address_2'] : '',
			isset($order[$kind . '_company']) ? $order[$kind . '_company'] : '',
		);

		$parts = array_filter(array_map('trim', array_map('strval', $parts)), 'strlen');

		return implode(', ', $parts);
	}

	private function statusName($status_id) {
		if ((int)$status_id <= 0) {
			return '';
		}
		$row = $this->db->query("SELECT `name` FROM `" . DB_PREFIX . "order_status`
			WHERE `order_status_id` = '" . (int)$status_id . "'
			  AND `language_id` = '" . (int)$this->config->get('config_language_id') . "' LIMIT 1")->row;

		return isset($row['name']) ? (string)$row['name'] : '';
	}

	/**
	 * Admin links are built from the storefront base URL (HTTP_SERVER — the
	 * catalog side has no HTTP_CATALOG) plus the configured admin folder.
	 */
	private function adminUrl($route) {
		$base = trim((string)$this->config->get('module_cc_telegram_admin_url'));
		if ($base === '') {
			$base = HTTP_SERVER . 'admin/';
		}
		if (substr($base, -1) !== '/') {
			$base .= '/';
		}

		return $base . 'index.php?route=' . $route;
	}

	// ---------------------------------------------------------------- transport

	private function broadcast($order_id, $event, $text, $product_id = 0) {
		$token = CcTelegramCrypto::decrypt((string)$this->config->get('module_cc_telegram_bot_token'));
		$chats = CcTelegramClient::chats($this->config->get('module_cc_telegram_chat_ids'));

		if ($token === '' || !$chats) {
			return;
		}

		$client = new CcTelegramClient($token);
		$logger = new CcTelegramLogger($this->db);
		$silent = (bool)$this->config->get('module_cc_telegram_silent');

		foreach ($chats as $chat) {
			$res = $client->sendMessage($chat['chat_id'], $text, $chat['thread_id'], $silent);

			// A dropped connection (no HTTP response at all) is the one failure
			// worth repeating immediately: nothing was delivered, so a retry
			// cannot duplicate the message.
			if (!$res['ok'] && (int)$res['status'] === 0) {
				$res = $client->sendMessage($chat['chat_id'], $text, $chat['thread_id'], $silent);
			}

			$logger->log(
				$order_id,
				$event,
				$chat['chat_id'],
				$res['status'],
				$res['ok'] ? 'OK' : $res['error'],
				$res['ok'],
				$product_id
			);
		}

		$logger->prune();
	}

	// -------------------------------------------------------------------- retry

	/**
	 * Second attempt at deliveries that failed — the usual cause is a blip at
	 * api.telegram.org or a rate limit.
	 *
	 * Registered in the OpenCart cron table as
	 * `extension/module/cc_telegram/retry`; a system cron can hit the same route
	 * directly. Lives here rather than in a `_cron` controller so it reuses the
	 * very same rendering the original send used.
	 *
	 * The message is re-rendered from the live order, so a retry reflects
	 * reality at send time rather than at failure time.
	 */
	public function retry() {
		if (!$this->enabled()) {
			return;
		}
		if ((string)$this->config->get('module_cc_telegram_retry_enabled') !== '1') {
			return;
		}

		$token = CcTelegramCrypto::decrypt((string)$this->config->get('module_cc_telegram_bot_token'));
		if ($token === '') {
			return;
		}

		$logger = new CcTelegramLogger($this->db);
		$rows   = $logger->retryable((int)$this->config->get('module_cc_telegram_max_attempts'));
		if (!$rows) {
			return;
		}

		$this->load->language('extension/module/cc_telegram');
		$this->load->model('checkout/order');

		$client = new CcTelegramClient($token);
		$silent = (bool)$this->config->get('module_cc_telegram_silent');

		// The stored chat id carries no topic, so recover the thread from the
		// configured list — a chat that was removed there is simply skipped.
		$threads = array();
		foreach (CcTelegramClient::chats($this->config->get('module_cc_telegram_chat_ids')) as $chat) {
			$threads[(string)$chat['chat_id']] = (int)$chat['thread_id'];
		}

		foreach ($rows as $row) {
			$chat_id = (string)$row['chat_id'];
			if (!isset($threads[$chat_id])) {
				continue;
			}

			$order = $this->model_checkout_order->getOrder((int)$row['order_id']);
			if (!$order) {
				continue;
			}

			$event = (string)$row['event'];
			$text  = CcTelegramFormatter::render(
				$this->template($event === 'status' ? 'status' : 'new_order'),
				$this->orderValues($order, 0)
			);
			if (trim($text) === '') {
				continue;
			}

			$res = $client->sendMessage($chat_id, $text, $threads[$chat_id], $silent);

			$logger->resolve(
				(int)$row['log_id'],
				$res['ok'],
				$res['status'],
				$res['ok'] ? 'OK' : $res['error']
			);
		}
	}

	// ----------------------------------------------------------------- settings

	private function enabled() {
		return (bool)$this->config->get('module_cc_telegram_status');
	}

	private function template($which) {
		return CcTelegramSettings::template($this->db, $this->config, $which);
	}

	/**
	 * @return int[] Configured order_status_id list.
	 */
	private function statusList($key) {
		$raw = $this->config->get('module_cc_telegram_' . $key);
		if (!is_array($raw)) {
			$raw = array();
		}

		return array_values(array_filter(array_map('intval', $raw)));
	}
}
