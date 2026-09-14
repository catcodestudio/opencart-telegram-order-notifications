<?php
namespace Opencart\System\Library\CcTelegram\Sms;

require_once __DIR__ . '/../polyfill.php';
require_once __DIR__ . '/../crypto.php';
require_once __DIR__ . '/../logger.php';
require_once __DIR__ . '/../license.php';
require_once __DIR__ . '/phone.php';
require_once __DIR__ . '/gateway.php';

use Opencart\System\Library\CcTelegram\Crypto;
use Opencart\System\Library\CcTelegram\License;
use Opencart\System\Library\CcTelegram\Logger;

/**
 * Pro: tells the CUSTOMER about their order over Viber / SMS when it reaches a
 * status the owner picked.
 *
 *  - one message per order per status: the claim row in the journal is written
 *    before the call, so a second addHistory with the same status (a payment
 *    callback, a double click) never pays twice;
 *  - the number goes out in E.164, read in the order's country; SMS and Viber
 *    sender names are picked per country ("UA=Shop", "PL=ShopPL", "*=Shop");
 *  - the history comment that came with the status is available as {comment},
 *    and the first 11–14 digit run in it as {tracking_number} — that is where
 *    OpenCart shops paste the waybill when they set "Shipped".
 *
 * Settings live in the cc_telegram_sms group (see SmsSettings::KEYS).
 */
class CustomerNotifier {
	public const PREFIX = 'cc_telegram_sms_';
	public const GROUP  = 'cc_telegram_sms';

	private $registry;
	private $config;
	private $db;
	private Logger $logger;

	public function __construct($registry) {
		$this->registry = $registry;
		$this->config   = $registry->get('config');
		$this->db       = $registry->get('db');
		$this->logger   = new Logger($this->db);
	}

	/** @return array<string,string> key => default */
	public static function defaults(): array {
		return [
			'enabled'       => '0',
			'gateway'       => 'turbosms',
			'api_key'       => '',
			'channel'       => Gateway::CHANNEL_HYBRID,
			'sms_senders'   => '',
			'viber_senders' => '',
			'country'       => 'UA',
			'rules'         => '',
		];
	}

	/** Default texts by status name, matched on install against the shop's own statuses. */
	public static function defaultTexts(): array {
		return [
			2 => '{site_name}: замовлення {order_number} прийнято, сума {order_total}. Дякуємо!',
			3 => "{site_name}: замовлення {order_number} відправлено.\nТТН: {tracking_number}",
			5 => '{site_name}: замовлення {order_number} виконано. Дякуємо за покупку!',
			7 => '{site_name}: замовлення {order_number} скасовано.',
		];
	}

	public function get(string $key): string {
		$value = $this->config->get(self::PREFIX . $key);
		if ($value === null) {
			return self::defaults()[$key] ?? '';
		}
		$value = is_array($value) ? json_encode($value) : (string)$value;
		return $key === 'api_key' && $value !== '' ? Crypto::decrypt($value) : $value;
	}

	/** @return array<int,array{on:string,text:string}> order_status_id => rule */
	public function rules(): array {
		$raw = $this->config->get(self::PREFIX . 'rules');
		if (is_string($raw)) {
			$raw = json_decode(html_entity_decode($raw, ENT_QUOTES, 'UTF-8'), true);
		}
		$out = [];
		foreach ((array)$raw as $id => $rule) {
			if ((int)$id > 0 && is_array($rule)) {
				$out[(int)$id] = ['on' => (string)($rule['on'] ?? '0'), 'text' => (string)($rule['text'] ?? '')];
			}
		}
		return $out;
	}

	public function gateway(): Gateway {
		return new Gateway($this->get('gateway'), $this->get('api_key'));
	}

	public function isActive(): bool {
		return $this->get('enabled') === '1' && License::isPro($this->registry);
	}

	/**
	 * Called from the addHistory/after event.
	 *
	 * @param array<string,mixed> $order    getOrder() row.
	 * @param int                 $statusId New status.
	 * @param int                 $oldId    Previous status (0 for a new order).
	 * @param string              $comment  History comment that came with it.
	 * @param callable            $render   fn(array $order, string $template, array $extra): string
	 */
	public function handle(array $order, int $statusId, int $oldId, string $comment, callable $render, callable $statusName): void {
		if (!$this->isActive() || $statusId <= 0) {
			return;
		}
		$rules = $this->rules();
		if (empty($rules[$statusId]) || $rules[$statusId]['on'] !== '1' || trim($rules[$statusId]['text']) === '') {
			return;
		}
		$orderId = (int)($order['order_id'] ?? 0);
		$event   = 'sms:' . $statusId;
		if ($orderId <= 0 || $this->alreadyHandled($orderId, $event)) {
			return;
		}

		$phone   = (string)($order['telephone'] ?? '');
		$country = $this->orderCountry($order);
		$number  = Phone::normalise($phone, $country !== '' ? $country : $this->get('country'));

		if ($number === null) {
			$this->logger->log($orderId, 0, $event, $phone !== '' ? mb_substr($phone, 0, 20) : '—', null, 'INVALID_PHONE', false);
			return;
		}

		// Claim first, send second: a parallel addHistory must find the row.
		$rowId = $this->logger->log($orderId, 0, $event, Phone::mask($number['e164']), null, 'SENDING', false);

		$tracking = preg_match('/(?<!\d)(\d{11,14})(?!\d)/', $comment, $m) ? $m[1] : '';
		$text     = trim($render($order, $rules[$statusId]['text'], [
			'order_status_old'    => $oldId > 0 ? $statusName($oldId) : '',
			'comment'             => trim($comment),
			'tracking_number'     => $tracking,
			'customer_first_name' => (string)($order['firstname'] ?? ''),
		]));
		if ($text === '') {
			$this->logger->resolve($rowId, false, null, 'EMPTY_TEXT');
			return;
		}

		$res = $this->deliver($number, $text);
		$this->logger->resolve($rowId, $res['ok'], ($res['code'] > 0 && $res['code'] < 65535) ? $res['code'] : null, $this->logText($res));
	}

	/**
	 * Free-form test to a number (admin button).
	 *
	 * @return array{ok:bool,code:int,status:string,message_id:string,transport:bool,phone:string}
	 */
	public function sendTest(string $phone, string $text): array {
		$number = Phone::normalise($phone, $this->get('country'));
		if ($number === null) {
			return ['ok' => false, 'code' => 0, 'status' => 'INVALID_PHONE', 'message_id' => '', 'transport' => false, 'phone' => $phone];
		}
		$res = $this->deliver($number, $text);
		$this->logger->log(0, 0, 'sms:test', Phone::mask($number['e164']), ($res['code'] > 0 && $res['code'] < 65535) ? $res['code'] : null, $this->logText($res), $res['ok']);
		$res['phone'] = $number['e164'];
		return $res;
	}

	/** Sender for a country from a "UA=Shop" list, "*" as the fallback. */
	public function sender(string $key, string $country): string {
		$map = [];
		foreach (preg_split('/[\r\n]+/', $this->get($key)) as $line) {
			$line = trim((string)$line);
			if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
				continue;
			}
			[$iso, $name] = array_map('trim', explode('=', $line, 2));
			$iso = strtoupper($iso);
			if ($name !== '' && ($iso === '*' || preg_match('/^[A-Z]{2}$/', $iso))) {
				$map[$iso] = $name;
			}
		}
		return $map[strtoupper($country)] ?? ($map['*'] ?? '');
	}

	private function deliver(array $number, string $text): array {
		$channel = $this->get('channel');
		if (!in_array($channel, Gateway::channels(), true)) {
			$channel = Gateway::CHANNEL_HYBRID;
		}
		$sms   = $this->sender('sms_senders', $number['country']);
		$viber = $this->sender('viber_senders', $number['country']);

		if (($channel !== Gateway::CHANNEL_VIBER && $sms === '') || ($channel !== Gateway::CHANNEL_SMS && $viber === '')) {
			return ['ok' => false, 'code' => 0, 'status' => 'NO_SENDER_FOR_' . $number['country'], 'message_id' => '', 'transport' => false];
		}

		return $this->gateway()->send($number['digits'], $text, $channel, $sms, $viber);
	}

	private function alreadyHandled(int $orderId, string $event): bool {
		$row = $this->db->query("SELECT `id` FROM `" . Logger::table() . "`
			WHERE `order_id` = " . (int)$orderId . "
			AND `event` = '" . str_replace(['\\', "'"], ['\\\\', "\\'"], $event) . "' LIMIT 1")->row;
		return !empty($row);
	}

	/** ISO-2 of the payment country, else the shipping one. */
	private function orderCountry(array $order): string {
		foreach (['payment_iso_code_2', 'shipping_iso_code_2'] as $field) {
			$iso = strtoupper(trim((string)($order[$field] ?? '')));
			if (preg_match('/^[A-Z]{2}$/', $iso)) {
				return $iso;
			}
		}
		foreach (['payment_country_id', 'shipping_country_id'] as $field) {
			$id = (int)($order[$field] ?? 0);
			if ($id > 0) {
				$row = $this->db->query("SELECT `iso_code_2` FROM `" . DB_PREFIX . "country` WHERE `country_id` = " . $id . " LIMIT 1")->row;
				if (!empty($row['iso_code_2'])) {
					return strtoupper((string)$row['iso_code_2']);
				}
			}
		}
		return '';
	}

	private function logText(array $res): string {
		return trim($this->gateway()->label() . ' ' . ($res['ok'] ? 'OK ' . $res['message_id'] : $res['status']));
	}
}
