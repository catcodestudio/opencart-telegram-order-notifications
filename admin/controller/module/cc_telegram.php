<?php
namespace Opencart\Admin\Controller\Extension\CcTelegram\Module;
require_once __DIR__ . '/polyfill.php';

require_once DIR_EXTENSION . 'cc_telegram/system/library/cc_telegram/crypto.php';
require_once DIR_EXTENSION . 'cc_telegram/system/library/cc_telegram/settings.php';
require_once DIR_EXTENSION . 'cc_telegram/system/library/cc_telegram/logger.php';
require_once DIR_EXTENSION . 'cc_telegram/system/library/cc_telegram/client.php';
require_once DIR_EXTENSION . 'cc_telegram/system/library/cc_telegram/formatter.php';
require_once DIR_EXTENSION . 'cc_telegram/system/library/cc_telegram/notifier.php';

use Opencart\System\Library\CcTelegram\Crypto;
use Opencart\System\Library\CcTelegram\Settings;
use Opencart\System\Library\CcTelegram\Logger;
use Opencart\System\Library\CcTelegram\Client;
use Opencart\System\Library\CcTelegram\Formatter;
use Opencart\System\Library\CcTelegram\Notifier;

class CcTelegram extends \Opencart\System\Engine\Controller {
	private string $route = 'extension/cc_telegram/module/cc_telegram';

	private function jsonResponse(array $data): void {
		if (ob_get_level() > 0) {
			ob_clean();
		}
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($data));
	}

	public function index(): void {
		$this->load->language($this->route);
		$this->document->setTitle($this->language->get('heading_title'));

		$settings = new Settings($this->config);
		$all      = $settings->all();

		$data = [];
		foreach (Settings::defaults() as $key => $default) {
			$data[$key] = $all[$key];
		}
		// Never echo the secret back; expose a "set" flag for the placeholder.
		$data['bot_token_set'] = ($all['bot_token'] ?? '') !== '' ? 1 : 0;
		$data['bot_token']     = '';

		// Cast to string: the twig "in" test compares against order_status_id
		// values that arrive from the DB as strings.
		$data['new_order_selected'] = array_map('strval', $settings->statusList('new_order_statuses'));
		$data['status_selected']    = array_map('strval', $settings->statusList('status_statuses'));

		$this->load->model('localisation/order_status');
		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		$data['placeholders'] = [];
		foreach (self::placeholders() as $tag) {
			$data['placeholders'][] = ['tag' => '{' . $tag . '}', 'label' => $this->language->get('tag_' . $tag)];
		}

		$data['breadcrumbs'] = [
			['text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])],
			['text' => $this->language->get('heading_title'), 'href' => $this->url->link($this->route, 'user_token=' . $this->session->data['user_token'])],
		];
		$data['save']       = $this->url->link($this->route . '.save', 'user_token=' . $this->session->data['user_token']);
		$data['test']       = $this->url->link($this->route . '.test', 'user_token=' . $this->session->data['user_token']);
		$data['discover']   = $this->url->link($this->route . '.discover', 'user_token=' . $this->session->data['user_token']);
		$data['log']        = $this->url->link($this->route . '.log', 'user_token=' . $this->session->data['user_token']);
		$data['back']       = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module');
		$data['user_token'] = $this->session->data['user_token'];

		$data['header']      = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer']      = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view($this->route, $data));
	}

	public function save(): void {
		$this->load->language($this->route);
		if (!$this->user->hasPermission('modify', $this->route)) {
			$this->jsonResponse(['error' => $this->language->get('error_permission')]);
			return;
		}
		$post = $this->request->post;

		$data = [];
		foreach (Settings::defaults() as $key => $default) {
			$field = 'module_cc_telegram_' . $key;

			if (in_array($key, Settings::SECRET_KEYS, true)) {
				$plain = trim((string)($post[$field] ?? ''));
				if ($plain !== '') {
					$data[$field] = Crypto::encrypt($plain);            // new secret
				} else {
					$data[$field] = (string)$this->config->get($field); // keep existing (encrypted)
				}
			} elseif (in_array($key, Settings::LIST_KEYS, true)) {
				$ids = [];
				foreach ((array)($post[$field] ?? []) as $id) {
					$id = (int)$id;
					if ($id > 0 && !in_array($id, $ids, true)) {
						$ids[] = $id;
					}
				}
				$data[$field] = implode(',', $ids);
			} elseif ($key === 'admin_url') {
				// Captured here because the catalog side has no way to learn a
				// renamed admin directory; HTTP_SERVER in the admin config is it.
				$data[$field] = defined('HTTP_SERVER') ? HTTP_SERVER : '';
			} elseif (strpos($key, 'template_') === 0) {
				// Store templates with real "<b>" tags. Twig escapes the textarea on
				// render, so without decoding here every save would push another
				// round of entities into the setting and Telegram would print the
				// markup instead of applying it.
				$data[$field] = html_entity_decode((string)($post[$field] ?? $default), ENT_QUOTES, 'UTF-8');
			} else {
				$data[$field] = $post[$field] ?? $default;
			}
		}

		$this->load->model('setting/setting');
		$this->model_setting_setting->editSetting('module_cc_telegram', $data);

		$this->jsonResponse(['success' => $this->language->get('text_success')]);
	}

	/**
	 * Verify the token with getMe, then push a real message to every configured
	 * chat so a wrong chat id surfaces immediately rather than at the first
	 * order.
	 */
	public function test(): void {
		$this->load->language($this->route);
		if (!$this->user->hasPermission('modify', $this->route)) {
			$this->jsonResponse(['ok' => false, 'error' => $this->language->get('error_permission')]);
			return;
		}

		$settings = $this->overrideSettings();
		$token    = (string)$settings->get('bot_token', '');
		if ($token === '' || $settings->chats() === []) {
			$this->jsonResponse(['ok' => false, 'error' => $this->language->get('error_not_configured')]);
			return;
		}

		$notifier = new Notifier($this->registry, $settings);

		$me = $notifier->client()->getMe();
		if (!$me['ok']) {
			$this->jsonResponse(['ok' => false, 'error' => $this->language->get('error_token') . ' ' . $me['error']]);
			return;
		}

		$text = '✅ <b>' . Formatter::esc($this->language->get('text_test_title')) . "</b>\n"
			. Formatter::esc($this->language->get('text_test_store')) . ': ' . Formatter::esc((string)$this->config->get('config_name')) . "\n"
			. Formatter::esc($this->language->get('text_test_body'));

		$res = $notifier->broadcastRaw('test', $text);

		if ($res['errors'] === []) {
			$this->jsonResponse([
				'ok'      => true,
				'message' => sprintf($this->language->get('text_test_ok'), (string)($me['json']['result']['username'] ?? ''), (int)$res['sent']),
			]);
			return;
		}

		$this->jsonResponse(['ok' => false, 'error' => $this->language->get('error_delivery') . ' ' . implode('; ', $res['errors'])]);
	}

	/**
	 * Read pending updates and report the chat ids seen there, so the admin does
	 * not have to hunt for a third-party "get my id" bot.
	 */
	public function discover(): void {
		$this->load->language($this->route);
		if (!$this->user->hasPermission('modify', $this->route)) {
			$this->jsonResponse(['ok' => false, 'error' => $this->language->get('error_permission')]);
			return;
		}

		$settings = $this->overrideSettings();
		$token    = (string)$settings->get('bot_token', '');
		if ($token === '') {
			$this->jsonResponse(['ok' => false, 'error' => $this->language->get('error_token_empty')]);
			return;
		}

		$res = (new Client($token))->getUpdates();
		if (!$res['ok']) {
			$this->jsonResponse(['ok' => false, 'error' => $res['error']]);
			return;
		}

		$found = [];
		foreach ((array)($res['json']['result'] ?? []) as $update) {
			foreach (['message', 'channel_post', 'my_chat_member'] as $key) {
				$chat = $update[$key]['chat'] ?? null;
				if (!is_array($chat) || !isset($chat['id'])) {
					continue;
				}
				$title = (string)($chat['title'] ?? trim((string)($chat['first_name'] ?? '') . ' ' . (string)($chat['last_name'] ?? '')));
				$found[(string)$chat['id']] = $chat['id'] . ($title !== '' ? ' (' . $title . ')' : '');
			}
		}

		if ($found === []) {
			$this->jsonResponse(['ok' => false, 'error' => $this->language->get('error_no_updates')]);
			return;
		}

		$this->jsonResponse([
			'ok'      => true,
			'message' => $this->language->get('text_found_chats') . ' ' . implode(', ', $found),
			'ids'     => array_keys($found),
		]);
	}

	public function log(): void {
		$this->load->language($this->route);
		if (!$this->user->hasPermission('access', $this->route)) {
			$this->response->setOutput($this->language->get('error_permission'));
			return;
		}
		$logger = new Logger($this->db);

		$data = [];
		$data['rows']       = $logger->recent(100);
		$data['user_token'] = $this->session->data['user_token'];

		foreach (['text_log', 'text_empty', 'column_date', 'column_event', 'column_order', 'column_chat', 'column_status', 'column_result'] as $key) {
			$data[$key] = $this->language->get($key);
		}

		$this->response->setOutput($this->load->view('extension/cc_telegram/module/cc_telegram_log', $data));
	}

	/**
	 * Settings view that prefers whatever is currently typed into the form, so
	 * the test buttons work before the first save.
	 */
	private function overrideSettings(): Settings {
		$stored = new Settings($this->config);

		$token = trim((string)($this->request->post['module_cc_telegram_bot_token'] ?? ''));
		$chats = trim((string)($this->request->post['module_cc_telegram_chat_ids'] ?? ''));
		if ($token === '' && $chats === '') {
			return $stored;
		}

		$overrides = [
			'bot_token' => $token !== '' ? $token : (string)$stored->get('bot_token', ''),
			'chat_ids'  => $chats !== '' ? $chats : (string)$stored->get('chat_ids', ''),
		];

		// Anonymous config shim: same get() contract, form values win.
		return new Settings(new class ($this->config, $overrides) {
			private $config;
			private array $overrides;

			public function __construct($config, array $overrides) {
				$this->config    = $config;
				$this->overrides = $overrides;
			}

			public function get($key) {
				$short = str_starts_with($key, 'module_cc_telegram_') ? substr($key, strlen('module_cc_telegram_')) : $key;
				if (isset($this->overrides[$short])) {
					// Settings::all() decrypts secrets, so hand it ciphertext.
					return in_array($short, Settings::SECRET_KEYS, true)
						? Crypto::encrypt((string)$this->overrides[$short])
						: $this->overrides[$short];
				}
				return $this->config->get($key);
			}
		});
	}

	/** @return string[] placeholder names, each with a matching tag_* language key. */
	private static function placeholders(): array {
		return [
			'order_number', 'order_id', 'order_total', 'order_status', 'order_status_old',
			'order_date', 'items', 'items_count', 'customer_name', 'customer_phone',
			'customer_email', 'customer_note', 'payment_method', 'shipping_method',
			'shipping_total', 'billing_address', 'shipping_address', 'admin_url',
			'site_name', 'product_name', 'product_sku', 'stock_qty', 'product_url',
		];
	}

	public function install(): void {
		$prefix = DB_PREFIX;
		$this->db->query("CREATE TABLE IF NOT EXISTS `{$prefix}cc_telegram_log` (
			`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			`order_id` INT(11) NOT NULL DEFAULT 0,
			`product_id` INT(11) NOT NULL DEFAULT 0,
			`event` VARCHAR(32) NOT NULL,
			`chat_id` VARCHAR(64) NOT NULL DEFAULT '',
			`http_status` SMALLINT UNSIGNED DEFAULT NULL,
			`message` TEXT NULL,
			`success` TINYINT(1) NOT NULL DEFAULT 0,
			`attempts` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			`created_at` DATETIME NOT NULL,
			PRIMARY KEY (`id`),
			KEY `order_id` (`order_id`),
			KEY `product_id` (`product_id`),
			KEY `event` (`event`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

		$this->load->model('setting/event');

		// OpenCart 4 fires model events as <route>.<method> — note the dot before
		// the method name — and addEvent() takes a single associative array.
		$this->model_setting_event->deleteEventByCode('cc_telegram_order_history');
		$this->model_setting_event->addEvent([
			'code'        => 'cc_telegram_order_history',
			'description' => 'CatCode Telegram Notifications — new order / order status change',
			'trigger'     => 'catalog/model/checkout/order*addHistory/after',
			'action'      => 'extension/cc_telegram/events.orderHistoryAdded',
			'status'      => 1,
			'sort_order'  => 20,
		]);

		$this->model_setting_event->deleteEventByCode('cc_telegram_low_stock');
		$this->model_setting_event->addEvent([
			'code'        => 'cc_telegram_low_stock',
			'description' => 'CatCode Telegram Notifications — low stock warning after stock subtraction',
			'trigger'     => 'catalog/model/checkout/order*addHistory/after',
			'action'      => 'extension/cc_telegram/events.lowStock',
			'status'      => 1,
			'sort_order'  => 30,
		]);

		$this->load->model('setting/cron');
		try {
			$this->model_setting_cron->deleteCronByCode('cc_telegram_retry');
		} catch (\Throwable $e) {
			// Older builds have no deleteCronByCode.
		}
		$this->model_setting_cron->addCron('cc_telegram_retry', 'CatCode Telegram Notifications — retry failed deliveries', 'hour', 'extension/cc_telegram/cron.retry', true);

		$this->load->model('user/user_group');
		try {
			$this->model_user_user_group->addPermission((int)$this->user->getGroupId(), 'access', $this->route);
			$this->model_user_user_group->addPermission((int)$this->user->getGroupId(), 'modify', $this->route);
		} catch (\Throwable $e) {
			// Permission already granted.
		}
	}

	public function uninstall(): void {
		$this->load->model('setting/event');
		try {
			$this->model_setting_event->deleteEventByCode('cc_telegram_order_history');
			$this->model_setting_event->deleteEventByCode('cc_telegram_low_stock');
		} catch (\Throwable $e) {
			// Nothing registered.
		}

		$this->load->model('setting/cron');
		try {
			$this->model_setting_cron->deleteCronByCode('cc_telegram_retry');
		} catch (\Throwable $e) {
			// Nothing registered.
		}
		// The log table is preserved so delivery history survives a reinstall.
	}
}
