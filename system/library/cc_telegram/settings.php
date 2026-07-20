<?php
namespace Opencart\System\Library\CcTelegram;

require_once __DIR__ . '/crypto.php';

/**
 * Settings repository over the OpenCart config. Every value lives under
 * module_cc_telegram_*; the secret bot_token is transparently decrypted.
 */
class Settings {
	public const SECRET_KEYS = ['bot_token'];

	/** Stored as comma separated lists of OC order_status_id. */
	public const LIST_KEYS = ['new_order_statuses', 'status_statuses'];

	private $config;
	private ?array $cache = null;

	public function __construct($config) {
		$this->config = $config;
	}

	public static function defaults(): array {
		return [
			'status'              => '0',
			'bot_token'           => '',
			'chat_ids'            => '',
			'silent'              => '0',
			'notify_new_order'    => '1',
			'new_order_statuses'  => '1,2',   // Pending, Processing
			'notify_status'       => '1',
			'status_statuses'     => '3,5,7', // Shipped, Complete, Canceled
			'notify_low_stock'    => '0',
			'low_stock_threshold' => '3',
			'retry_enabled'       => '1',
			'max_attempts'        => '3',
			'admin_url'           => '',
			'template_new_order'  => '',
			'template_status'     => '',
			'template_low_stock'  => '',
		];
	}

	/**
	 * Message templates use Telegram's HTML parse mode — only <b>, <i>, <u>,
	 * <s>, <code>, <pre> and <a href> survive its parser, so keep markup to
	 * those. A line whose placeholders all resolve to empty is dropped.
	 */
	public static function defaultTemplate(string $which): string {
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

	public function all(): array {
		if ($this->cache !== null) {
			return $this->cache;
		}
		$out = self::defaults();
		foreach ($out as $key => $default) {
			$val = $this->config->get('module_cc_telegram_' . $key);
			if ($val !== null && $val !== '') {
				$out[$key] = is_array($val) ? implode(',', $val) : $val;
			}
		}
		foreach (self::SECRET_KEYS as $secret) {
			if (!empty($out[$secret])) {
				$out[$secret] = Crypto::decrypt((string)$out[$secret]);
			}
		}
		// An empty template means "never saved" — fall back to the shipped one
		// rather than sending a blank message.
		//
		// The decode matters: a template that made a round trip through the admin
		// form can come back as "&lt;b&gt;". Telegram does not decode entities, so
		// such a message shows the tags as plain text instead of bold.
		foreach (['new_order', 'status', 'low_stock'] as $which) {
			if (trim((string)$out['template_' . $which]) === '') {
				$out['template_' . $which] = self::defaultTemplate($which);
			} else {
				$out['template_' . $which] = html_entity_decode((string)$out['template_' . $which], ENT_QUOTES, 'UTF-8');
			}
		}
		$this->cache = $out;
		return $out;
	}

	public function get(string $key, $default = null) {
		$all = $this->all();
		return $all[$key] ?? $default;
	}

	public function isEnabled(): bool {
		return (string)$this->get('status', '0') === '1';
	}

	public function isConfigured(): bool {
		return (string)$this->get('bot_token', '') !== '' && $this->chats() !== [];
	}

	public function isSilent(): bool {
		return (string)$this->get('silent', '0') === '1';
	}

	/** @return int[] OC order_status_id list for the given settings key. */
	public function statusList(string $key): array {
		$raw = (string)$this->get($key, '');
		$out = [];
		foreach (explode(',', $raw) as $id) {
			$id = (int)trim($id);
			if ($id > 0 && !in_array($id, $out, true)) {
				$out[] = $id;
			}
		}
		return $out;
	}

	/**
	 * Parse the chat list. One target per line: a numeric chat id, an @channel
	 * username, optionally suffixed with a forum topic id after a colon
	 * (e.g. "-1001234567890:42"). Blank lines and "#" comments are ignored.
	 *
	 * @return array<int,array{chat_id:string,thread_id:int}>
	 */
	public function chats(): array {
		$raw  = (string)$this->get('chat_ids', '');
		$out  = [];
		$seen = [];

		foreach (preg_split('/[\r\n,]+/', $raw) as $line) {
			$line = trim((string)$line);
			if ($line === '' || str_starts_with($line, '#')) {
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

			$out[] = ['chat_id' => $line, 'thread_id' => $thread];
		}

		return $out;
	}

	/** Base admin URL captured at save time (admin dir may be renamed). */
	public function adminUrl(): string {
		$url = trim((string)$this->get('admin_url', ''));
		return $url === '' ? '' : rtrim($url, '/') . '/';
	}
}
