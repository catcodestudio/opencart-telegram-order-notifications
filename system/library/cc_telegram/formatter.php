<?php
namespace Opencart\System\Library\CcTelegram;

require_once __DIR__ . '/settings.php';

/**
 * Turns an order (or a low-stock product) plus an admin-authored template into
 * the final Telegram message.
 *
 * Template markup is trusted (only an admin can edit it) and passed through as
 * HTML; every substituted *value* is escaped, so a customer cannot inject tags
 * and break Telegram's parser — an unbalanced tag makes the API reject the
 * whole message with "can't parse entities".
 */
class Formatter {

	/** Placeholders whose value is pre-built markup and must not be escaped. */
	private const RAW_HTML = ['items', 'admin_url', 'product_url'];

	private $db;
	private $config;
	private $currency;
	private Settings $settings;

	public function __construct($registry, Settings $settings) {
		$this->db       = $registry->get('db');
		$this->config   = $registry->get('config');
		$this->currency = $registry->get('currency');
		$this->settings = $settings;
	}

	/**
	 * Render a template against an order.
	 *
	 * @param array<string,mixed>  $order Row from model_checkout_order->getOrder().
	 * @param array<string,string> $extra Extra placeholder values (unescaped).
	 */
	public function order(array $order, string $template, array $extra = []): string {
		return self::render($template, array_merge($this->orderValues($order), $extra));
	}

	/**
	 * Render a template against a product (low-stock notice).
	 *
	 * @param array<string,mixed> $product Row from the product table.
	 */
	public function product(array $product, string $storeUrl): string {
		$productId = (int)($product['product_id'] ?? 0);

		return self::render(
			(string)$this->settings->get('template_low_stock', ''),
			[
				'site_name'    => (string)$this->config->get('config_name'),
				'product_name' => (string)($product['name'] ?? ''),
				'product_id'   => (string)$productId,
				'product_sku'  => trim((string)($product['sku'] ?? '')) !== '' ? (string)$product['sku'] : (string)($product['model'] ?? ''),
				'stock_qty'    => (string)(int)($product['quantity'] ?? 0),
				'product_url'  => self::link(rtrim($storeUrl, '/') . '/index.php?route=product/product&product_id=' . $productId, 'Відкрити товар'),
				'admin_url'    => self::link($this->adminLink('catalog/product.form&product_id=' . $productId), 'Редагувати товар'),
			]
		);
	}

	/**
	 * All order-derived placeholder values, still unescaped.
	 *
	 * @return array<string,string>
	 */
	private function orderValues(array $order): array {
		$orderId  = (int)($order['order_id'] ?? 0);
		$code     = (string)($order['currency_code'] ?? $this->config->get('config_currency'));
		$rate     = (float)($order['currency_value'] ?? 1);
		$statusId = (int)($order['order_status_id'] ?? 0);

		$name = trim((string)($order['firstname'] ?? '') . ' ' . (string)($order['lastname'] ?? ''));
		if ($name === '') {
			$name = trim((string)($order['shipping_firstname'] ?? '') . ' ' . (string)($order['shipping_lastname'] ?? ''));
		}

		$number = trim((string)($order['invoice_prefix'] ?? '') . (string)($order['invoice_no'] ?? ''));
		if ((int)($order['invoice_no'] ?? 0) <= 0) {
			$number = '#' . $orderId;
		}

		return [
			'site_name'        => (string)($order['store_name'] ?? $this->config->get('config_name')),
			'order_number'     => $number,
			'order_id'         => (string)$orderId,
			'order_total'      => $this->money((float)($order['total'] ?? 0), $code, $rate),
			'order_status'     => $this->statusName($statusId),
			'order_status_id'  => (string)$statusId,
			'order_date'       => $this->date((string)($order['date_added'] ?? '')),
			'payment_method'   => self::methodName($order['payment_method'] ?? ''),
			'shipping_method'  => self::methodName($order['shipping_method'] ?? ''),
			'shipping_total'   => $this->money($this->orderTotal($orderId, 'shipping'), $code, $rate),
			'customer_name'    => $name,
			'customer_phone'   => (string)($order['telephone'] ?? ''),
			'customer_email'   => (string)($order['email'] ?? ''),
			'customer_note'    => trim((string)($order['comment'] ?? '')),
			'billing_address'  => self::address($order, 'payment'),
			'shipping_address' => self::address($order, 'shipping'),
			'items'            => $this->items($orderId, $code, $rate),
			'items_count'      => (string)count($this->products($orderId)),
			'admin_url'        => self::link($this->adminLink('sale/order.info&order_id=' . $orderId), 'Відкрити замовлення'),
			'product_name'     => '',
			'product_sku'      => '',
			'stock_qty'        => '',
		];
	}

	/**
	 * One line per order item: "• Name (SKU) × 2 — 500,00 ₴".
	 * Built escaped piecewise, so render() must not escape it again.
	 */
	private function items(int $orderId, string $code, float $rate): string {
		$lines = [];
		foreach ($this->products($orderId) as $item) {
			$line = '• ' . self::esc((string)$item['name']);
			if (trim((string)$item['model']) !== '') {
				$line .= ' (' . self::esc((string)$item['model']) . ')';
			}
			$line .= ' × ' . (int)$item['quantity'];
			// order_product.total is already price × quantity, net of tax.
			$line .= ' — ' . self::esc($this->money((float)$item['total'], $code, $rate));

			$lines[] = $line;
		}
		return implode("\n", $lines);
	}

	/** @var array<int,array<int,array<string,mixed>>> */
	private array $productCache = [];

	private function products(int $orderId): array {
		if (!isset($this->productCache[$orderId])) {
			$this->productCache[$orderId] = $this->db->query(
				"SELECT `product_id`, `name`, `model`, `quantity`, `price`, `total` FROM `" . DB_PREFIX . "order_product` WHERE `order_id` = " . (int)$orderId
			)->rows;
		}
		return $this->productCache[$orderId];
	}

	private function orderTotal(int $orderId, string $code): float {
		// db->escape() differs between the mysqli and PDO adapters (the PDO one
		// returns an already-quoted string), so keep this to a safe literal.
		$code = preg_replace('/[^a-z0-9_]/', '', strtolower($code));
		$row  = $this->db->query(
			"SELECT `value` FROM `" . DB_PREFIX . "order_total` WHERE `order_id` = " . (int)$orderId . " AND `code` = '" . $code . "' LIMIT 1"
		)->row;
		return $row ? (float)$row['value'] : 0.0;
	}

	private function statusName(int $statusId): string {
		if ($statusId <= 0) {
			return '';
		}
		$row = $this->db->query(
			"SELECT `name` FROM `" . DB_PREFIX . "order_status`
			WHERE `order_status_id` = " . (int)$statusId . "
			AND `language_id` = " . (int)$this->config->get('config_language_id') . " LIMIT 1"
		)->row;
		return $row ? (string)$row['name'] : '';
	}

	/** Public so the event controller can name the previous status. */
	public function orderStatusName(int $statusId): string {
		return $this->statusName($statusId);
	}

	/**
	 * OpenCart 4 stores payment_method / shipping_method as a JSON object and
	 * getOrder() hands back the decoded array; older data may still be a plain
	 * string.
	 */
	private static function methodName($method): string {
		if (is_array($method)) {
			return (string)($method['name'] ?? '');
		}
		$decoded = json_decode((string)$method, true);
		if (is_array($decoded)) {
			return (string)($decoded['name'] ?? '');
		}
		return (string)$method;
	}

	/** Flatten an OpenCart address block into one comma-separated line. */
	private static function address(array $order, string $prefix): string {
		$parts = [];
		foreach (['postcode', 'country', 'zone', 'city', 'address_1', 'address_2', 'company'] as $field) {
			$value = trim((string)($order[$prefix . '_' . $field] ?? ''));
			if ($value !== '') {
				$parts[] = $value;
			}
		}
		return implode(', ', $parts);
	}

	private function money(float $amount, string $code, float $rate): string {
		// The currency library is registered in both admin and catalog, but keep
		// a plain fallback so a message never turns into a fatal.
		if (!is_object($this->currency) || !method_exists($this->currency, 'format')) {
			return number_format($amount, 2, '.', ' ') . ' ' . $code;
		}
		$formatted = $this->currency->format($amount, $code, $rate > 0 ? $rate : 1);
		// OpenCart returns the symbol HTML-encoded (e.g. &#8372;); Telegram would
		// print the entity verbatim.
		return trim(html_entity_decode(strip_tags((string)$formatted), ENT_QUOTES, 'UTF-8'));
	}

	private function date(string $raw): string {
		if ($raw === '' || str_starts_with($raw, '0000')) {
			return '';
		}
		$ts = strtotime($raw);
		return $ts ? date('d.m.Y H:i', $ts) : $raw;
	}

	/** Absolute admin link, or '' when the admin URL was never captured. */
	private function adminLink(string $route): string {
		$base = $this->settings->adminUrl();
		return $base === '' ? '' : $base . 'index.php?route=' . $route;
	}

	/**
	 * Substitute placeholders, escaping values, then drop any line whose
	 * placeholders all resolved to empty (e.g. a missing customer note).
	 *
	 * @param array<string,string> $values Raw values.
	 */
	public static function render(string $template, array $values): string {
		$out = [];
		foreach (explode("\n", str_replace("\r\n", "\n", $template)) as $line) {
			if (!preg_match_all('/\{([a-z0-9_]+)\}/', $line, $m)) {
				$out[] = $line;
				continue;
			}

			$known = 0;
			$empty = 0;
			foreach ($m[1] as $key) {
				if (!array_key_exists($key, $values)) {
					continue;
				}
				++$known;
				$value = (string)$values[$key];
				if (trim($value) === '') {
					++$empty;
				}
				$line = str_replace(
					'{' . $key . '}',
					in_array($key, self::RAW_HTML, true) ? $value : self::esc($value),
					$line
				);
			}

			// Line existed only to carry now-empty data — skip it.
			if ($known > 0 && $known === $empty) {
				continue;
			}
			$out[] = $line;
		}

		$text = implode("\n", $out);
		// Unknown placeholders stay visible on purpose: a typo in the template
		// should be noticeable, not silently swallowed.
		return trim((string)preg_replace("/\n{3,}/", "\n\n", $text));
	}

	/**
	 * Build an <a> for Telegram. Deliberately not an OpenCart-escaped URL: an
	 * "&" turned into "&#038;" is passed through literally by Telegram and the
	 * resulting admin link loses its query args.
	 */
	public static function link(string $url, string $label): string {
		if (trim($url) === '') {
			return '';
		}
		return '<a href="' . self::esc($url) . '">' . self::esc($label) . '</a>';
	}

	/** Telegram's HTML mode needs &, < and > escaped — and nothing else. */
	public static function esc(string $value): string {
		return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $value);
	}
}
