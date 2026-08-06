<?php
require_once DIR_SYSTEM . 'library/cc_telegram.php';

/**
 * Telegram Order Notifications — admin controller (OpenCart 3.x).
 *
 * Two tabs: Settings and Journal. The bot token is stored encrypted and is
 * never echoed back into the form; submitting an empty field keeps the stored
 * one rather than wiping it.
 */
class ControllerExtensionModuleCcTelegram extends Controller {

	private $error = array();
	private $route = 'extension/module/cc_telegram';

	/** Setting keys that are plain scalars. */
	private $fields = array(
		'module_cc_telegram_status',
		'module_cc_telegram_chat_ids',
		'module_cc_telegram_silent',
		'module_cc_telegram_notify_new_order',
		'module_cc_telegram_notify_status',
		'module_cc_telegram_notify_low_stock',
		'module_cc_telegram_low_stock_qty',
		'module_cc_telegram_retry_enabled',
		'module_cc_telegram_max_attempts',
		'module_cc_telegram_admin_url',
		'module_cc_telegram_template_new_order',
		'module_cc_telegram_template_status',
		'module_cc_telegram_template_low_stock',
	);

	public function index() {
		$this->load->language($this->route);
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			// OpenCart 3 connects with set_charset('utf8') — the 3-byte variant.
			// Emoji are 4-byte, so templates would be stored as "????" unless the
			// connection is switched for this request before the settings are written.
			try { $this->db->query("SET NAMES utf8mb4"); } catch (Exception $e) {}
			$this->model_setting_setting->editSetting('module_cc_telegram', $this->collect());
			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
		}

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';

		$data['breadcrumbs']   = array();
		$data['breadcrumbs'][] = array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true));
		$data['breadcrumbs'][] = array('text' => $this->language->get('text_extension'), 'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
		$data['breadcrumbs'][] = array('text' => $this->language->get('heading_title'), 'href' => $this->url->link($this->route, 'user_token=' . $this->session->data['user_token'], true));

		$data['action']     = $this->url->link($this->route, 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel']     = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
		// OpenCart 3 always returns "&amp;" from url->link(). That is right for an
		// href, but these two are handed to $.ajax as-is, and a literal "&amp;"
		// turns user_token into "amp;user_token" — the call then 403s.
		$data['test_url']   = html_entity_decode($this->url->link($this->route . '/test', 'user_token=' . $this->session->data['user_token'], true), ENT_QUOTES, 'UTF-8');
		$data['find_url']   = html_entity_decode($this->url->link($this->route . '/findchats', 'user_token=' . $this->session->data['user_token'], true), ENT_QUOTES, 'UTF-8');
		$data['clear_url']  = $this->url->link($this->route . '/clearlog', 'user_token=' . $this->session->data['user_token'], true);

		foreach ($this->fields as $f) {
			$data[$f] = isset($this->request->post[$f]) ? $this->request->post[$f] : $this->config->get($f);
		}

		// Defaults on a fresh install.
		if ($data['module_cc_telegram_low_stock_qty'] === null || $data['module_cc_telegram_low_stock_qty'] === '') {
			$data['module_cc_telegram_low_stock_qty'] = 3;
		}
		if ($data['module_cc_telegram_admin_url'] === null || $data['module_cc_telegram_admin_url'] === '') {
			$data['module_cc_telegram_admin_url'] = HTTP_CATALOG . 'admin/';
		}
		// Retry defaults to on: the failure it covers (a blip at api.telegram.org)
		// is exactly the one a shop owner never finds out about otherwise.
		if ($this->config->get('module_cc_telegram_max_attempts') === null && !isset($this->request->post['module_cc_telegram_max_attempts'])) {
			$data['module_cc_telegram_retry_enabled'] = 1;
			$data['module_cc_telegram_max_attempts']  = 3;
		}
		if ((int)$data['module_cc_telegram_max_attempts'] < 1) {
			$data['module_cc_telegram_max_attempts'] = 3;
		}

		$data['cron_url'] = (defined('HTTP_CATALOG') ? HTTP_CATALOG : '') . 'index.php?route=extension/module/cc_telegram/retry';

		// Not $this->config: the bootstrap read has already turned every 4-byte
		// emoji into "?", and the form would then persist that on the next save.
		foreach (array('new_order', 'status', 'low_stock') as $which) {
			$key = 'module_cc_telegram_template_' . $which;
			if (!isset($this->request->post[$key])) {
				$data[$key] = CcTelegramSettings::template($this->db, $this->config, $which);
			} elseif (trim((string)$data[$key]) === '') {
				$data[$key] = CcTelegramFormatter::defaultTemplate($which);
			}
		}

		// The token is write-only: show whether one is stored, never its value.
		$data['has_token'] = trim((string)$this->config->get('module_cc_telegram_bot_token')) !== '';

		$this->load->model('localisation/order_status');
		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		$data['new_order_statuses'] = $this->selectedStatuses('new_order_statuses');
		$data['status_statuses']    = $this->selectedStatuses('status_statuses');

		$data['placeholders'] = $this->placeholders();
		$data['logs']         = $this->logRows();

		$data['header']      = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer']      = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view($this->route, $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', $this->route)) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	/**
	 * Build the settings payload from the POST.
	 *
	 * ⚠ editSetting() deletes every row of this code first, so any key missing
	 * from the payload is wiped — the write-only bot token has to be carried
	 * through explicitly whenever the field was submitted empty.
	 */
	private function collect() {
		$post = $this->request->post;
		$out  = array();

		foreach ($this->fields as $f) {
			$out[$f] = isset($post[$f]) ? $post[$f] : '';
		}

		$out['module_cc_telegram_low_stock_qty'] = (int)$out['module_cc_telegram_low_stock_qty'];
		$out['module_cc_telegram_max_attempts']  = max(1, min(10, (int)$out['module_cc_telegram_max_attempts']));
		$out['module_cc_telegram_admin_url']     = trim((string)$out['module_cc_telegram_admin_url']);
		$out['module_cc_telegram_chat_ids']      = trim((string)$out['module_cc_telegram_chat_ids']);

		// Templates carry Telegram markup, so tags are kept — but only the handful
		// Telegram itself understands. An emptied field falls back to the default
		// rather than muting the notification silently.
		foreach (array('new_order', 'status', 'low_stock') as $which) {
			$key       = 'module_cc_telegram_template_' . $which;
			$out[$key] = CcTelegramFormatter::sanitizeTemplate($out[$key]);
			if ($out[$key] === '') {
				$out[$key] = CcTelegramFormatter::defaultTemplate($which);
			}
		}

		// Unchecking everything is a legitimate choice (mute that event), so an
		// empty list is stored as-is rather than snapped back to the defaults.
		foreach (array('new_order_statuses', 'status_statuses') as $key) {
			$raw = isset($post['module_cc_telegram_' . $key]) && is_array($post['module_cc_telegram_' . $key])
				? $post['module_cc_telegram_' . $key]
				: array();
			$out['module_cc_telegram_' . $key] = array_values(array_filter(array_map('intval', $raw)));
		}

		$token = isset($post['module_cc_telegram_bot_token']) ? trim((string)$post['module_cc_telegram_bot_token']) : '';
		$out['module_cc_telegram_bot_token'] = $token !== ''
			? CcTelegramCrypto::encrypt($token)
			: (string)$this->config->get('module_cc_telegram_bot_token');

		return $out;
	}

	// -------------------------------------------------------------------- tools

	/** Verify the token and push a test message to every configured chat. */
	public function test() {
		$this->load->language($this->route);
		$json = array();

		if (!$this->user->hasPermission('modify', $this->route)) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			$client = new CcTelegramClient($this->postedToken());
			$me     = $client->getMe();

			if (!$me['ok']) {
				$json['error'] = sprintf($this->language->get('error_token'), $me['error']);
			} else {
				$chats = CcTelegramClient::chats($this->postedChats());

				if (!$chats) {
					$json['error'] = $this->language->get('error_no_chats');
				} else {
					$text = '✅ <b>' . CcTelegramFormatter::esc($this->language->get('text_test_title')) . "</b>\n"
						. CcTelegramFormatter::esc($this->language->get('text_test_shop')) . ' '
						. CcTelegramFormatter::esc((string)$this->config->get('config_name')) . "\n"
						. CcTelegramFormatter::esc($this->language->get('text_test_body'));

					$logger = new CcTelegramLogger($this->db);
					$sent   = 0;
					$errors = array();

					foreach ($chats as $chat) {
						$res = $client->sendMessage($chat['chat_id'], $text, $chat['thread_id']);
						$logger->log(0, 'test', $chat['chat_id'], $res['status'], $res['ok'] ? 'OK' : $res['error'], $res['ok']);

						if ($res['ok']) {
							$sent++;
						} else {
							$errors[] = $chat['chat_id'] . ': ' . $res['error'];
						}
					}

					if (!$errors) {
						$username = isset($me['json']['result']['username']) ? (string)$me['json']['result']['username'] : '';
						$json['success'] = sprintf($this->language->get('text_test_ok'), $username, $sent);
					} else {
						$json['error'] = sprintf($this->language->get('error_not_delivered'), implode('; ', $errors));
					}
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Read pending updates and report the chat ids seen there, so the admin does
	 * not have to hunt for a third-party "get my id" bot.
	 */
	public function findchats() {
		$this->load->language($this->route);
		$json = array();

		if (!$this->user->hasPermission('modify', $this->route)) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			$client = new CcTelegramClient($this->postedToken());
			$res    = $client->getUpdates();

			if (!$res['ok']) {
				$json['error'] = sprintf($this->language->get('error_generic'), $res['error']);
			} else {
				$found  = array();
				$result = isset($res['json']['result']) && is_array($res['json']['result']) ? $res['json']['result'] : array();

				foreach ($result as $update) {
					foreach (array('message', 'channel_post', 'my_chat_member') as $key) {
						if (!isset($update[$key]['chat']) || !is_array($update[$key]['chat'])) {
							continue;
						}
						$chat = $update[$key]['chat'];
						if (!isset($chat['id'])) {
							continue;
						}
						$title = isset($chat['title'])
							? (string)$chat['title']
							: trim((isset($chat['first_name']) ? $chat['first_name'] : '') . ' ' . (isset($chat['last_name']) ? $chat['last_name'] : ''));

						$found[(string)$chat['id']] = $chat['id'] . ($title !== '' ? ' (' . $title . ')' : '');
					}
				}

				if (!$found) {
					$json['error'] = $this->language->get('error_no_updates');
				} else {
					$json['success'] = sprintf($this->language->get('text_found_chats'), implode(', ', $found));
					$json['chats']   = array_keys($found);
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function clearlog() {
		$this->load->language($this->route);

		if ($this->user->hasPermission('modify', $this->route)) {
			$logger = new CcTelegramLogger($this->db);
			$logger->clear();
			$this->session->data['success'] = $this->language->get('text_log_cleared');
		}

		$this->response->redirect($this->url->link($this->route, 'user_token=' . $this->session->data['user_token'], true));
	}

	/** Token typed into the form wins, so the buttons work before the first save. */
	private function postedToken() {
		$posted = isset($this->request->post['bot_token']) ? trim((string)$this->request->post['bot_token']) : '';
		if ($posted !== '') {
			return $posted;
		}

		return CcTelegramCrypto::decrypt((string)$this->config->get('module_cc_telegram_bot_token'));
	}

	private function postedChats() {
		$posted = isset($this->request->post['chat_ids']) ? trim((string)$this->request->post['chat_ids']) : '';
		if ($posted !== '') {
			return $posted;
		}

		return (string)$this->config->get('module_cc_telegram_chat_ids');
	}

	// ------------------------------------------------------------------ journal

	private function logRows() {
		$table = CcTelegramLogger::table();
		$check = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape($table) . "'");
		if (!$check->num_rows) {
			return array();
		}

		$logger = new CcTelegramLogger($this->db);
		// Upgrading in place re-uploads the files but never re-runs install(), so
		// this is the one moment an older table is guaranteed to be seen.
		$logger->ensureColumns();

		$out = array();

		foreach ($logger->latest(100) as $r) {
			$order_id = (int)$r['order_id'];
			$out[]    = array(
				'date_added' => $r['date_added'],
				'order_id'   => $order_id,
				'href'       => $order_id > 0
					? $this->url->link('sale/order/info', 'user_token=' . $this->session->data['user_token'] . '&order_id=' . $order_id, true)
					: '',
				'event'      => $r['event'],
				'chat_id'    => $r['chat_id'],
				'http'       => $r['http_status'],
				'success'    => (bool)$r['success'],
				'attempts'   => isset($r['attempts']) ? (int)$r['attempts'] : 1,
				'message'    => mb_substr((string)$r['message'], 0, 200),
			);
		}

		return $out;
	}

	/**
	 * @return int[] Currently selected order_status_id list.
	 */
	private function selectedStatuses($key) {
		$field = 'module_cc_telegram_' . $key;

		if (isset($this->request->post[$field]) && is_array($this->request->post[$field])) {
			$raw = $this->request->post[$field];
		} else {
			$raw = $this->config->get($field);
		}

		if (!is_array($raw)) {
			// Fresh install — announce the usual "order is real now" statuses.
			return $key === 'new_order_statuses' ? array(1, 2) : array(3, 5, 7);
		}

		return array_values(array_filter(array_map('intval', $raw)));
	}

	/**
	 * @return array<int,array{tag:string,text:string}> Template tag reference.
	 */
	private function placeholders() {
		$tags = array(
			'order_number', 'order_id', 'order_total', 'order_status', 'order_status_old',
			'order_date', 'items', 'items_count', 'customer_name', 'customer_phone',
			'customer_email', 'customer_note', 'payment_method', 'shipping_method',
			'shipping_total', 'billing_address', 'shipping_address', 'admin_url',
			'site_name', 'product_name', 'product_sku', 'stock_qty',
		);

		$out = array();
		foreach ($tags as $tag) {
			$out[] = array('tag' => $tag, 'text' => $this->language->get('tag_' . $tag));
		}

		return $out;
	}

	// ---------------------------------------------------------------- lifecycle

	public function install() {
		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "cc_telegram_log` (
			`log_id` int(11) NOT NULL AUTO_INCREMENT,
			`order_id` int(11) NOT NULL DEFAULT '0',
			`product_id` int(11) NOT NULL DEFAULT '0',
			`event` varchar(32) NOT NULL DEFAULT '',
			`chat_id` varchar(64) NOT NULL DEFAULT '',
			`http_status` smallint(6) NOT NULL DEFAULT '0',
			`message` text,
			`success` tinyint(1) NOT NULL DEFAULT '0',
			`attempts` smallint(6) NOT NULL DEFAULT '1',
			`date_added` datetime DEFAULT NULL,
			PRIMARY KEY (`log_id`),
			KEY `order_id` (`order_id`),
			KEY `product_id` (`product_id`),
			KEY `event` (`event`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

		// A 1.0.0 table survived the CREATE above untouched — bring it forward.
		$logger = new CcTelegramLogger($this->db);
		$logger->ensureColumns();

		// OC3 addEvent() takes positional arguments (code, trigger, action) —
		// the array form is OpenCart 4 only.
		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode('cc_telegram_before');
		$this->model_setting_event->deleteEventByCode('cc_telegram_after');
		$this->model_setting_event->addEvent('cc_telegram_before', 'catalog/model/checkout/order/addOrderHistory/before', 'extension/module/cc_telegram/eventOrderHistoryBefore');
		$this->model_setting_event->addEvent('cc_telegram_after', 'catalog/model/checkout/order/addOrderHistory/after', 'extension/module/cc_telegram/eventOrderHistoryAfter');

		$this->registerCron();
	}

	public function uninstall() {
		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode('cc_telegram_before');
		$this->model_setting_event->deleteEventByCode('cc_telegram_after');

		if ($this->cronTableExists()) {
			$this->db->query("DELETE FROM `" . DB_PREFIX . "cron` WHERE `code` = 'cc_telegram_retry'");
		}
		// The log table and settings survive uninstall (data safety).
	}

	private function cronTableExists() {
		try {
			return $this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "cron'")->num_rows > 0;
		} catch (Exception $e) {
			return false;
		}
	}

	/**
	 * OpenCart 3 ships an `oc_cron` table only from 3.0.3.x; register there when
	 * it exists so the merchant does not have to add a system cron. Older builds
	 * still have the URL printed in the settings screen.
	 */
	private function registerCron() {
		if (!$this->cronTableExists()) {
			return;
		}

		try {
			$this->db->query("DELETE FROM `" . DB_PREFIX . "cron` WHERE `code` = 'cc_telegram_retry'");
			$this->db->query("INSERT INTO `" . DB_PREFIX . "cron`
				SET `code` = 'cc_telegram_retry',
				    `cycle` = 'hour',
				    `action` = 'extension/module/cc_telegram/retry',
				    `status` = '1',
				    `date_added` = NOW(),
				    `date_modified` = NOW()");
		} catch (Exception $e) {
			// Older 3.0.x builds use a different cron schema.
		}
	}
}
