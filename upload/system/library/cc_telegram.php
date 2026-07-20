<?php
/**
 * Telegram Order Notifications — shared library (OpenCart 3.x, no namespaces).
 *
 * CcTelegramClient    — Bot API transport (sendMessage / getMe / getUpdates).
 * CcTelegramFormatter — {placeholder} templating for orders and low-stock notices.
 * CcTelegramCrypto    — at-rest obfuscation for the stored bot token.
 * CcTelegramLogger    — the oc_cc_telegram_log table.
 *
 * Docs: https://core.telegram.org/bots/api
 */

/**
 * Telegram Bot API client.
 *
 * All calls are plain JSON POSTs to https://api.telegram.org/bot<token>/<method>.
 * Telegram answers {"ok":true,"result":…} or {"ok":false,"description":…,
 * "error_code":N} — the HTTP status alone is not enough, so both are inspected.
 */
class CcTelegramClient {

	/** Telegram hard-caps a message at 4096 UTF-8 chars. */
	const MAX_LEN = 4096;

	const ENDPOINT = 'https://api.telegram.org/bot';

	private $token;
	private $timeout = 20;

	public function __construct($token) {
		$this->token = trim((string)$token);
	}

	/** Cheap authenticated read used by the "test message" button. */
	public function getMe() {
		return $this->request('getMe');
	}

	/**
	 * Pull pending updates so the admin can discover a chat id without leaving
	 * OpenCart: write any message to the bot / add it to a group, then hit
	 * "Знайти chat_id".
	 */
	public function getUpdates() {
		return $this->request('getUpdates', array('limit' => 20));
	}

	/**
	 * @param string $chat_id   Numeric id or @username.
	 * @param string $text      Message body (HTML parse mode).
	 * @param int    $thread_id Forum topic id, 0 for none.
	 * @param bool   $silent    Deliver without a notification sound.
	 * @return array{ok:bool,status:int,body:string,json:array|null,error:string}
	 */
	public function sendMessage($chat_id, $text, $thread_id = 0, $silent = false) {
		$payload = array(
			'chat_id'    => (string)$chat_id,
			'text'       => self::truncate((string)$text),
			'parse_mode' => 'HTML',
			// Order links would otherwise render a fat preview card under every message.
			'link_preview_options' => array('is_disabled' => true),
		);
		if ((int)$thread_id > 0) {
			$payload['message_thread_id'] = (int)$thread_id;
		}
		if ($silent) {
			$payload['disable_notification'] = true;
		}

		return $this->request('sendMessage', $payload);
	}

	/**
	 * Cut to Telegram's limit on a character boundary, leaving a marker so a
	 * clipped message is obvious rather than mysteriously short.
	 */
	public static function truncate($text) {
		if (function_exists('mb_strlen')) {
			if (mb_strlen($text, 'UTF-8') <= self::MAX_LEN) {
				return $text;
			}
			return mb_substr($text, 0, self::MAX_LEN - 2, 'UTF-8') . '…';
		}
		if (strlen($text) <= self::MAX_LEN) {
			return $text;
		}
		return substr($text, 0, self::MAX_LEN - 2) . '…';
	}

	/**
	 * @param string $method Bot API method name.
	 * @param array  $body   JSON body.
	 * @return array{ok:bool,status:int,body:string,json:array|null,error:string}
	 */
	private function request($method, array $body = array()) {
		if ($this->token === '') {
			return array('ok' => false, 'status' => 0, 'body' => '', 'json' => null, 'error' => 'Bot token is empty.');
		}

		$ch = curl_init(self::ENDPOINT . rawurlencode($this->token) . '/' . $method);
		curl_setopt_array($ch, array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
			CURLOPT_HTTPHEADER     => array('Content-Type: application/json', 'Accept: application/json'),
			CURLOPT_TIMEOUT        => $this->timeout,
			CURLOPT_CONNECTTIMEOUT => 5,
			CURLOPT_USERAGENT      => 'CatCode-Telegram/1.0 (+https://catcode.com.ua)',
		));
		$raw  = curl_exec($ch);
		$err  = curl_error($ch);
		$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($raw === false) {
			return array('ok' => false, 'status' => 0, 'body' => '', 'json' => null, 'error' => $err !== '' ? $err : 'Connection failed.');
		}

		$decoded = json_decode((string)$raw, true);
		$json    = is_array($decoded) ? $decoded : null;
		$ok      = ($code >= 200 && $code < 300) && is_array($json) && !empty($json['ok']);

		$error = '';
		if (!$ok) {
			if (is_array($json) && !empty($json['description'])) {
				$error = (string)$json['description'];
			} else {
				$error = 'HTTP ' . $code;
			}
		}

		return array('ok' => $ok, 'status' => $code, 'body' => (string)$raw, 'json' => $json, 'error' => $error);
	}

	/**
	 * Parse the chat list. One target per line: a numeric chat id, an @channel
	 * username, optionally suffixed with a forum topic id after a colon
	 * (e.g. "-1001234567890:42"). Blank lines and "#" comments are ignored.
	 *
	 * @return array<int,array{chat_id:string,thread_id:int}>
	 */
	public static function chats($raw) {
		$out  = array();
		$seen = array();

		foreach (preg_split('/[\r\n,]+/', (string)$raw) as $line) {
			$line = trim((string)$line);
			if ($line === '' || strncmp($line, '#', 1) === 0) {
				continue;
			}

			$thread = 0;
			// Split off a trailing ":<digits>" topic id; @usernames never contain colons.
			if (preg_match('/^(.+):(\d+)$/', $line, $m)) {
				$line   = trim($m[1]);
				$thread = (int)$m[2];
			}
			if ($line === '' || isset($seen[$line . ':' . $thread])) {
				continue;
			}
			$seen[$line . ':' . $thread] = true;

			$out[] = array('chat_id' => $line, 'thread_id' => $thread);
		}

		return $out;
	}
}

/**
 * Turns an order (or a low-stock product) plus an admin-authored template into
 * the final Telegram message.
 *
 * Template markup is trusted (only an admin can edit it) and passed through as
 * HTML; every substituted *value* is escaped, so a customer cannot inject tags
 * and break Telegram's parser — an unbalanced tag makes the API reject the
 * whole message with "can't parse entities".
 */
class CcTelegramFormatter {

	/** Values that are pre-built markup and must not be escaped again. */
	private static $raw_html = array('items', 'admin_url', 'product_url');

	/**
	 * Substitute placeholders, escaping values, then drop any line whose
	 * placeholders all resolved to empty (e.g. a missing customer comment).
	 *
	 * @param string $template Raw template with {placeholders}.
	 * @param array  $values   Raw (unescaped) values.
	 */
	public static function render($template, array $values) {
		$out = array();

		foreach (explode("\n", str_replace("\r\n", "\n", (string)$template)) as $line) {
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
				$known++;
				$value = (string)$values[$key];
				if (trim($value) === '') {
					$empty++;
				}
				$line = str_replace(
					'{' . $key . '}',
					in_array($key, self::$raw_html, true) ? $value : self::esc($value),
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
		return trim(preg_replace("/\n{3,}/", "\n\n", $text));
	}

	/**
	 * One line per order item: "• Name (SKU) × 2 — 500.00 ₴".
	 *
	 * @param array    $products Rows of `order_product`.
	 * @param callable $money    fn(float): string
	 * @param array    $skus     product_id => SKU/model.
	 */
	public static function items(array $products, $money, array $skus = array()) {
		$lines = array();
		foreach ($products as $p) {
			$sku = '';
			if (isset($p['product_id']) && isset($skus[$p['product_id']])) {
				$sku = (string)$skus[$p['product_id']];
			} elseif (isset($p['model'])) {
				$sku = (string)$p['model'];
			}

			$line = '• ' . self::esc(isset($p['name']) ? (string)$p['name'] : '');
			if ($sku !== '') {
				$line .= ' (' . self::esc($sku) . ')';
			}
			$line .= ' × ' . (int)(isset($p['quantity']) ? $p['quantity'] : 0);
			$line .= ' — ' . self::esc(call_user_func($money, (float)(isset($p['total']) ? $p['total'] : 0)));

			$lines[] = $line;
		}
		// Already escaped piecewise — render() must not double-escape (see $raw_html).
		return implode("\n", $lines);
	}

	/** Collapse a multi-line address into one comma-separated line. */
	public static function flatten($address) {
		return trim(preg_replace('/\s*(<br\s*\/?>|\n|\r)\s*/i', ', ', (string)$address), ', ');
	}

	/**
	 * esc_url()-style display encoding would turn "&" into "&#038;", which
	 * Telegram passes through literally — the resulting admin link loses its
	 * query args. Escape only for Telegram's parser.
	 */
	public static function link($url, $label) {
		$url = trim((string)$url);
		if ($url === '') {
			return '';
		}
		return '<a href="' . self::esc($url) . '">' . self::esc((string)$label) . '</a>';
	}

	/** Telegram's HTML mode needs &, < and > escaped — and nothing else. */
	public static function esc($value) {
		return str_replace(array('&', '<', '>'), array('&amp;', '&lt;', '&gt;'), (string)$value);
	}

	/**
	 * Message templates use HTML parse mode — only <b>, <i>, <u>, <s>, <code>,
	 * <pre> and <a href> survive Telegram's parser, so keep markup to those.
	 */
	public static function defaultTemplate($which) {
		switch ($which) {
			case 'status':
				return "🔄 <b>Замовлення {order_number}</b>\n"
					. "Статус: {order_status_old} → <b>{order_status}</b>\n"
					. "Сума: {order_total}\n"
					. '{admin_url}';

			case 'low_stock':
				return "⚠️ <b>Низький залишок</b>\n"
					. "{product_name}\n"
					. "Артикул: {product_sku}\n"
					. "Залишилось: <b>{stock_qty}</b>\n"
					. '{admin_url}';

			case 'new_order':
			default:
				return "🛒 <b>Нове замовлення {order_number}</b>\n"
					. "👤 {customer_name}\n"
					. "📞 {customer_phone}\n"
					. "💳 {payment_method}\n"
					. "🚚 {shipping_method}\n"
					. "📍 {shipping_address}\n\n"
					. "{items}\n"
					. "💰 Разом: <b>{order_total}</b>\n"
					. "📝 {customer_note}\n"
					. '{admin_url}';
		}
	}

	/**
	 * Strip everything Telegram's HTML parse mode does not understand, so a
	 * stray <div> in a template cannot make every message bounce.
	 */
	public static function sanitizeTemplate($raw) {
		// Decode first: a template that made a round trip through the admin form
		// arrives as "&lt;b&gt;", and strip_tags() would keep those entities as
		// literal text. Telegram does not decode entities, so the message would
		// show the markup instead of applying it.
		$decoded = html_entity_decode((string)$raw, ENT_QUOTES, 'UTF-8');
		return trim(strip_tags($decoded, '<b><strong><i><em><u><s><a><code><pre>'));
	}
}

/**
 * At-rest obfuscation for the stored bot token (OC3).
 * NOT cryptographic-grade — defense in depth against casual DB-dump leaks.
 */
class CcTelegramCrypto {
	const PREFIX = 'cctg$';

	private static function secret() {
		$material = DIR_SYSTEM . (defined('DB_DATABASE') ? DB_DATABASE : '');
		return hash('sha256', 'CcTelegram|' . $material, true);
	}

	public static function encrypt($plain) {
		$plain = (string)$plain;
		if ($plain === '') {
			return '';
		}
		$secret = self::secret();
		$bytes  = '';
		for ($i = 0, $n = strlen($plain); $i < $n; $i++) {
			$bytes .= chr(ord($plain[$i]) ^ ord($secret[$i % strlen($secret)]));
		}
		return self::PREFIX . base64_encode($bytes);
	}

	public static function decrypt($stored) {
		$stored = (string)$stored;
		if ($stored === '') {
			return '';
		}
		if (strpos($stored, self::PREFIX) !== 0) {
			return $stored; // legacy plaintext
		}
		$bytes = base64_decode(substr($stored, strlen(self::PREFIX)), true);
		if ($bytes === false) {
			return '';
		}
		$secret = self::secret();
		$out    = '';
		for ($i = 0, $n = strlen($bytes); $i < $n; $i++) {
			$out .= chr(ord($bytes[$i]) ^ ord($secret[$i % strlen($secret)]));
		}
		return $out;
	}
}

/**
 * Notification event log (`oc_cc_telegram_log`).
 */
class CcTelegramLogger {

	/** @var DB */
	private $db;

	public function __construct($db) {
		$this->db = $db;
	}

	public static function table() {
		return DB_PREFIX . 'cc_telegram_log';
	}

	public function log($order_id, $event, $chat_id, $http, $message, $success) {
		$this->db->query("INSERT INTO `" . self::table() . "` SET
			`order_id` = '" . (int)$order_id . "',
			`event` = '" . $this->db->escape(substr((string)$event, 0, 32)) . "',
			`chat_id` = '" . $this->db->escape(substr((string)$chat_id, 0, 64)) . "',
			`http_status` = '" . (int)$http . "',
			`message` = '" . $this->db->escape(substr((string)$message, 0, 2000)) . "',
			`success` = '" . ($success ? 1 : 0) . "',
			`date_added` = NOW()");
	}

	public function latest($limit = 100) {
		$limit = max(1, (int)$limit);
		return $this->db->query("SELECT * FROM `" . self::table() . "` ORDER BY `log_id` DESC LIMIT " . $limit)->rows;
	}

	/** Trim the log so it never grows unbounded on busy shops. */
	public function prune($keep = 500) {
		$keep   = max(1, (int)$keep);
		$row    = $this->db->query("SELECT `log_id` FROM `" . self::table() . "` ORDER BY `log_id` DESC LIMIT 1 OFFSET " . $keep)->row;
		$cutoff = isset($row['log_id']) ? (int)$row['log_id'] : 0;
		if ($cutoff > 0) {
			$this->db->query("DELETE FROM `" . self::table() . "` WHERE `log_id` <= '" . $cutoff . "'");
		}
	}

	public function clear() {
		$this->db->query("TRUNCATE TABLE `" . self::table() . "`");
	}
}
