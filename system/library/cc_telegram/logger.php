<?php
namespace Opencart\System\Library\CcTelegram;

/**
 * Delivery journal for Telegram notifications.
 *
 * Besides being the admin-facing log, this table carries two pieces of state:
 *  - a "new_order_mark" row per order, which makes the new-order announcement
 *    fire exactly once no matter how many history rows an order collects;
 *  - the attempt counter used by the retry cron.
 *
 * Marker rows are excluded from the UI and survive pruning.
 */
class Logger {
	public const MARK_NEW_ORDER = 'new_order_mark';

	private $db;

	public function __construct($db) {
		$this->db = $db;
	}

	public static function table(): string {
		return DB_PREFIX . 'cc_telegram_log';
	}

	/**
	 * String literal escaping that behaves the same on the mysqli and PDO
	 * adapters — OpenCart's PDO db->escape() returns an already-quoted value,
	 * which silently corrupts hand-built SQL.
	 */
	private static function q(string $value): string {
		return str_replace(['\\', "'"], ['\\\\', "\\'"], $value);
	}

	public function log(int $orderId, int $productId, string $event, string $chatId, ?int $http, string $message, bool $success): int {
		$this->db->query("INSERT INTO `" . self::table() . "` SET
			`order_id` = " . (int)$orderId . ",
			`product_id` = " . (int)$productId . ",
			`event` = '" . self::q(mb_substr($event, 0, 32)) . "',
			`chat_id` = '" . self::q(mb_substr($chatId, 0, 64)) . "',
			`http_status` = " . ($http === null ? 'NULL' : (int)$http) . ",
			`message` = '" . self::q(mb_substr($message, 0, 2000)) . "',
			`success` = " . ($success ? 1 : 0) . ",
			`attempts` = 1,
			`created_at` = NOW()");

		return (int)$this->db->getLastId();
	}

	/** Has the one-time "new order" announcement already been made? */
	public function newOrderSent(int $orderId): bool {
		$row = $this->db->query("SELECT `id` FROM `" . self::table() . "`
			WHERE `order_id` = " . (int)$orderId . "
			AND `event` = '" . self::MARK_NEW_ORDER . "' LIMIT 1")->row;
		return !empty($row);
	}

	public function markNewOrderSent(int $orderId): void {
		if ($this->newOrderSent($orderId)) {
			return;
		}
		$this->db->query("INSERT INTO `" . self::table() . "` SET
			`order_id` = " . (int)$orderId . ",
			`product_id` = 0,
			`event` = '" . self::MARK_NEW_ORDER . "',
			`chat_id` = '',
			`http_status` = NULL,
			`message` = '',
			`success` = 1,
			`attempts` = 0,
			`created_at` = NOW()");
	}

	/** Throttle low-stock notices to one per product per day. */
	public function lowStockRecently(int $productId, int $hours = 24): bool {
		$row = $this->db->query("SELECT `id` FROM `" . self::table() . "`
			WHERE `product_id` = " . (int)$productId . "
			AND `event` = 'low_stock'
			AND `created_at` > DATE_SUB(NOW(), INTERVAL " . (int)$hours . " HOUR) LIMIT 1")->row;
		return !empty($row);
	}

	/** Failed order-bound rows still under the attempt cap, for the retry cron. */
	public function retryable(int $maxAttempts, int $limit = 50): array {
		return $this->db->query("SELECT * FROM `" . self::table() . "`
			WHERE `success` = 0
			AND `order_id` > 0
			AND `attempts` < " . (int)$maxAttempts . "
			AND `event` IN ('new_order', 'status', 'failed')
			ORDER BY `id` DESC LIMIT " . (int)$limit)->rows;
	}

	public function resolve(int $id, bool $success, ?int $http, string $message): void {
		$this->db->query("UPDATE `" . self::table() . "` SET
			`success` = " . ($success ? 1 : 0) . ",
			`http_status` = " . ($http === null ? 'NULL' : (int)$http) . ",
			`message` = '" . self::q(mb_substr($message, 0, 2000)) . "',
			`attempts` = `attempts` + 1
			WHERE `id` = " . (int)$id);
	}

	public function recent(int $limit = 100): array {
		return $this->db->query("SELECT * FROM `" . self::table() . "`
			WHERE `event` <> '" . self::MARK_NEW_ORDER . "'
			ORDER BY `id` DESC LIMIT " . (int)max(1, $limit))->rows;
	}

	/** Trim the journal so it never grows unbounded on a busy shop. */
	public function prune(int $keep = 500): void {
		$row = $this->db->query("SELECT `id` FROM `" . self::table() . "`
			WHERE `event` <> '" . self::MARK_NEW_ORDER . "'
			ORDER BY `id` DESC LIMIT 1 OFFSET " . (int)max(1, $keep))->row;
		if (!$row) {
			return;
		}
		$this->db->query("DELETE FROM `" . self::table() . "`
			WHERE `id` <= " . (int)$row['id'] . "
			AND `event` <> '" . self::MARK_NEW_ORDER . "'");
	}
}
