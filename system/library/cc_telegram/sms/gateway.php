<?php
namespace Opencart\System\Library\CcTelegram\Sms;

/**
 * SMS / Viber gateways: TurboSMS, Alpha SMS, SMS-Fly. One class, three wire
 * formats — each answers "send this text to this number over this channel" and
 * "what is the balance" in the same shape, so the notifier never cares which
 * service is on.
 *
 * Channels: viber_sms (Viber, SMS if Viber did not deliver — the fallback runs
 * on the gateway's side), viber, sms (never pays for a Viber attempt).
 *
 * Wire formats (checked against the public docs 14.09.2026):
 *  - TurboSMS  POST https://api.turbosms.ua/message/send.json, Bearer token,
 *              {"recipients":[…],"viber":{sender,text,ttl},"sms":{sender,text}},
 *              per-recipient response_code 800–803 = accepted. https://turbosms.ua/api.html
 *  - Alpha SMS POST https://alphasms.ua/api/json.php,
 *              {"auth":key,"data":[{"type":"viber+sms","phone":380…,"sms_signature",
 *              "sms_message","viber_type":"text","viber_signature","viber_message",
 *              "viber_lifetime"}]}, success on the envelope AND on the item. https://docs.alphasms.ua/ru/api/json/
 *  - SMS-Fly   POST https://sms-fly.ua/api/v2/api.php,
 *              {"auth":{"key"},"action":"SENDMESSAGE","data":{"recipient","channels":["viber","sms"],
 *              "viber":{source,ttl,text},"sms":{source,ttl,text}}}, ttl in MINUTES,
 *              "success":1 + data.messageID. https://sms-fly.ua/public/api.v2.4_en.pdf
 *
 * A staging shop defines CC_TELEGRAM_SMS_API (a mock URL) in config.php; the
 * gateway id is then appended as ?gw=<id>. Production never defines it.
 */
class Gateway {
	public const CHANNEL_HYBRID = 'viber_sms';
	public const CHANNEL_VIBER  = 'viber';
	public const CHANNEL_SMS    = 'sms';

	/** Seconds Viber gets before the hybrid falls back to SMS. */
	public const VIBER_TTL = 3600;

	private const ENDPOINTS = [
		'turbosms' => 'https://api.turbosms.ua/',
		'alphasms' => 'https://alphasms.ua/api/json.php',
		'smsfly'   => 'https://sms-fly.ua/api/v2/api.php',
	];

	private string $id;
	private string $key;

	public function __construct(string $id, string $key) {
		$this->id  = isset(self::ENDPOINTS[$id]) ? $id : 'turbosms';
		$this->key = trim($key);
	}

	/** @return array<string,string> id => label */
	public static function available(): array {
		return ['turbosms' => 'TurboSMS', 'alphasms' => 'Alpha SMS', 'smsfly' => 'SMS-Fly'];
	}

	/** @return string[] */
	public static function channels(): array {
		return [self::CHANNEL_HYBRID, self::CHANNEL_VIBER, self::CHANNEL_SMS];
	}

	public function id(): string {
		return $this->id;
	}

	public function label(): string {
		return self::available()[$this->id];
	}

	/**
	 * @return array{ok:bool,code:int,status:string,message_id:string,transport:bool}
	 *         transport = the gateway was not reached at all (worth a retry).
	 */
	public function send(string $digits, string $text, string $channel, string $smsSender, string $viberSender): array {
		if ($this->key === '') {
			return self::outcome(false, 0, 'REQUIRED_KEY');
		}
		switch ($this->id) {
			case 'alphasms':
				return $this->sendAlpha($digits, $text, $channel, $smsSender, $viberSender);
			case 'smsfly':
				return $this->sendFly($digits, $text, $channel, $smsSender, $viberSender);
			default:
				return $this->sendTurbo($digits, $text, $channel, $smsSender, $viberSender);
		}
	}

	/** @return array{ok:bool,balance:string,status:string} */
	public function balance(): array {
		if ($this->key === '') {
			return ['ok' => false, 'balance' => '', 'status' => 'REQUIRED_KEY'];
		}

		if ($this->id === 'alphasms') {
			$res   = $this->post(self::ENDPOINTS['alphasms'], ['auth' => $this->key, 'data' => [['type' => 'balance']]]);
			$json  = $res['json'] ?? [];
			$entry = $json['data'][0] ?? [];
			if (!empty($json['success']) && !empty($entry['success']) && isset($entry['data']['amount'])) {
				return ['ok' => true, 'balance' => trim($entry['data']['amount'] . ' ' . ($entry['data']['currency'] ?? '')), 'status' => 'OK'];
			}
			return ['ok' => false, 'balance' => '', 'status' => $res['json'] === null ? $res['error'] : (string)($json['error'] ?? ($entry['error'] ?? 'ERROR'))];
		}

		if ($this->id === 'smsfly') {
			$res  = $this->post(self::ENDPOINTS['smsfly'], ['auth' => ['key' => $this->key], 'action' => 'GETBALANCE', 'data' => new \stdClass()]);
			$json = $res['json'] ?? [];
			$ok   = !empty($json['success']) && isset($json['data']['balance']);
			return ['ok' => $ok, 'balance' => $ok ? (string)$json['data']['balance'] : '', 'status' => $res['json'] === null ? $res['error'] : ($ok ? 'OK' : (string)($json['error']['code'] ?? 'ERROR'))];
		}

		$res  = $this->post(self::ENDPOINTS['turbosms'] . 'user/balance.json', new \stdClass(), ['Authorization: Bearer ' . $this->key]);
		$json = $res['json'] ?? [];
		$ok   = (int)($json['response_code'] ?? -1) === 0 && isset($json['response_result']['balance']);
		return ['ok' => $ok, 'balance' => $ok ? (string)$json['response_result']['balance'] : '', 'status' => $res['json'] === null ? $res['error'] : (string)($json['response_status'] ?? '')];
	}

	private function sendTurbo(string $digits, string $text, string $channel, string $sms, string $viber): array {
		$payload = ['recipients' => [$digits]];
		if ($channel !== self::CHANNEL_SMS) {
			$payload['viber'] = ['sender' => $viber, 'text' => $text, 'ttl' => self::VIBER_TTL];
		}
		if ($channel !== self::CHANNEL_VIBER) {
			$payload['sms'] = ['sender' => $sms, 'text' => $text];
		}

		$res = $this->post(self::ENDPOINTS['turbosms'] . 'message/send.json', $payload, ['Authorization: Bearer ' . $this->key]);
		if ($res['json'] === null) {
			return self::outcome(false, -1, $res['error'], '', true);
		}
		$json      = $res['json'];
		$recipient = $json['response_result'][0] ?? [];
		$code      = isset($recipient['response_code']) ? (int)$recipient['response_code'] : (int)($json['response_code'] ?? -1);
		$status    = isset($recipient['response_status']) ? (string)$recipient['response_status'] : (string)($json['response_status'] ?? '');
		$messageId = (string)($recipient['message_id'] ?? '');
		$accepted  = $code === 0 || ($code >= 800 && $code <= 803);

		return self::outcome($accepted && $messageId !== '', $code, $status, $messageId);
	}

	private function sendAlpha(string $digits, string $text, string $channel, string $sms, string $viber): array {
		$item = [
			'id'    => (int)(microtime(true) * 1000) % 2147483647,
			'phone' => (int)$digits,
			'type'  => $channel === self::CHANNEL_SMS ? 'sms' : ($channel === self::CHANNEL_VIBER ? 'viber' : 'viber+sms'),
		];
		if ($channel !== self::CHANNEL_SMS) {
			$item['viber_type']      = 'text';
			$item['viber_signature'] = $viber;
			$item['viber_message']   = $text;
			$item['viber_lifetime']  = self::VIBER_TTL;
		}
		if ($channel !== self::CHANNEL_VIBER) {
			$item['sms_signature'] = $sms;
			$item['sms_message']   = $text;
		}

		$res = $this->post(self::ENDPOINTS['alphasms'], ['auth' => $this->key, 'data' => [$item]]);
		if ($res['json'] === null) {
			return self::outcome(false, -1, $res['error'], '', true);
		}
		$json = $res['json'];
		if (empty($json['success'])) {
			return self::outcome(false, $res['http'], (string)($json['error'] ?? 'ERROR'));
		}
		$entry = $json['data'][0] ?? [];
		if (empty($entry['success'])) {
			return self::outcome(false, $res['http'], (string)($entry['error'] ?? 'ERROR'));
		}
		$messageId = isset($entry['data']['msg_id']) ? (string)$entry['data']['msg_id'] : '';

		return self::outcome($messageId !== '', $res['http'], 'ACCEPTED', $messageId);
	}

	private function sendFly(string $digits, string $text, string $channel, string $sms, string $viber): array {
		$ttl  = (int)max(1, min(1440, round(self::VIBER_TTL / 60)));
		$data = ['recipient' => $digits, 'channels' => []];
		if ($channel !== self::CHANNEL_SMS) {
			$data['channels'][] = 'viber';
			$data['viber']      = ['source' => $viber, 'ttl' => $ttl, 'text' => $text];
		}
		if ($channel !== self::CHANNEL_VIBER) {
			$data['channels'][] = 'sms';
			$data['sms']        = ['source' => $sms, 'ttl' => $ttl, 'text' => $text];
		}

		$res = $this->post(self::ENDPOINTS['smsfly'], ['auth' => ['key' => $this->key], 'action' => 'SENDMESSAGE', 'data' => $data]);
		if ($res['json'] === null) {
			return self::outcome(false, -1, $res['error'], '', true);
		}
		$json = $res['json'];
		if (empty($json['success'])) {
			$desc = trim((string)($json['error']['description'] ?? ''));
			return self::outcome(false, $res['http'], (string)($json['error']['code'] ?? 'ERROR') . ($desc !== '' ? ' ' . $desc : ''));
		}
		$messageId = (string)($json['data']['messageID'] ?? '');
		$status    = [];
		foreach (['viber', 'sms'] as $ch) {
			if (isset($json['data'][$ch]['status'])) {
				$status[] = $ch . ':' . $json['data'][$ch]['status'];
			}
		}

		return self::outcome($messageId !== '', $res['http'], $status ? implode(' ', $status) : 'ACCEPTD', $messageId);
	}

	/**
	 * @param array|\stdClass $payload
	 * @return array{http:int,json:array|null,error:string}
	 */
	private function post(string $url, $payload, array $headers = []): array {
		if (!function_exists('curl_init')) {
			return ['http' => 0, 'json' => null, 'error' => 'no_curl'];
		}
		if (defined('CC_TELEGRAM_SMS_API')) {
			$url = (string)constant('CC_TELEGRAM_SMS_API') . '?gw=' . $this->id . '&path=' . rawurlencode((string)parse_url($url, PHP_URL_PATH));
		}

		$ch = curl_init($url);
		curl_setopt_array($ch, [
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => (string)json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
			CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/json', 'Accept: application/json'], $headers),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => 15,
			CURLOPT_CONNECTTIMEOUT => 5,
		]);
		$body  = curl_exec($ch);
		$error = (string)curl_error($ch);
		$http  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		$decoded = is_string($body) ? json_decode($body, true) : null;

		return [
			'http'  => $http,
			'json'  => is_array($decoded) ? $decoded : null,
			'error' => is_array($decoded) ? '' : ($error !== '' ? $error : 'HTTP ' . $http),
		];
	}

	private static function outcome(bool $ok, int $code, string $status, string $messageId = '', bool $transport = false): array {
		return ['ok' => $ok, 'code' => $code, 'status' => $status, 'message_id' => $messageId, 'transport' => $transport];
	}
}
