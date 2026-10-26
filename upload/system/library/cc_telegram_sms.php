<?php
/**
 * Telegram Order Notifications — Pro: Viber / SMS to customers (OpenCart 3.x, no namespaces).
 *
 * Ported from the OpenCart 4 build; see that tree for the long-form notes.
 */

require_once DIR_SYSTEM . 'library/cc_telegram.php';

/**
 * Phone number → E.164, decided by the order's country rather than by a guess
 * from the leading digits.
 *
 * Guessing is what breaks on mixed shops: a Polish 9-digit mobile "731 234 567"
 * and a Ukrainian "073 123 45 67" with the zero dropped look the same once the
 * spaces are gone. So:
 *  1. "+…" or "00…" is already international — the calling code is matched
 *     (longest first) and the length validated;
 *  2. anything else is read in the order's country (payment, then shipping,
 *     then the module's default): trunk prefix dropped, calling code added; a
 *     number that already carries that country's code at full length is kept;
 *  3. only when that fails, a bare international number of another country is
 *     tried (a Ukrainian 380… number on a Polish shop).
 */
class CcTelegramSmsPhone {

	/**
	 * ISO => [calling code, min subscriber digits, max subscriber digits, trunk prefix].
	 *
	 * @return array<string,array{0:string,1:int,2:int,3:string}>
	 */
	public static function countries(): array {
		return [
			'UA' => ['380', 9, 9, '0'],
			'PL' => ['48', 9, 9, ''],
			'MD' => ['373', 8, 8, '0'],
			'RO' => ['40', 9, 9, '0'],
			'CZ' => ['420', 9, 9, ''],
			'SK' => ['421', 9, 9, '0'],
			'LT' => ['370', 8, 8, '8'],
			'LV' => ['371', 8, 8, ''],
			'EE' => ['372', 7, 8, ''],
			'DE' => ['49', 10, 11, '0'],
			'AT' => ['43', 10, 13, '0'],
			'GE' => ['995', 9, 9, '0'],
			'TR' => ['90', 10, 10, '0'],
			'GB' => ['44', 10, 10, '0'],
			'BG' => ['359', 8, 9, '0'],
			'HU' => ['36', 8, 9, '06'],
		];
	}

	/**
	 * @return array{digits:string,e164:string,country:string}|null
	 */
	public static function normalise(string $raw, string $country) {
		$raw    = trim($raw);
		$digits = (string)preg_replace('/\D+/', '', $raw);
		if (strlen($digits) < 7) {
			return null;
		}

		$table   = self::countries();
		$country = strtoupper(trim($country));

		$international = strpos($raw, '+') === 0;
		if (!$international && strpos($digits, '00') === 0) {
			$digits        = substr($digits, 2);
			$international = true;
		}

		if ($international) {
			return self::matchInternational($digits, $table);
		}

		if (isset($table[$country])) {
			$national = self::matchNational($digits, $country, $table[$country]);
			if ($national !== null) {
				return $national;
			}
		}

		return self::matchInternational($digits, $table);
	}

	/** Masked for the journal: +38093***4567. */
	public static function mask(string $e164): string {
		$len = strlen($e164);
		if ($len < 8) {
			return $e164;
		}
		return substr($e164, 0, 6) . str_repeat('*', max(1, $len - 10)) . substr($e164, -4);
	}

	private static function matchNational(string $digits, string $iso, array $spec) {
		list($code, $min, $max, $trunk) = $spec;

		if (strpos($digits, $code) === 0 && self::fits(strlen($digits) - strlen($code), $min, $max)) {
			return self::result($digits, $iso);
		}
		// Ukraine only: "80931234567", the old long-distance form, still typed.
		if ($iso === 'UA' && strlen($digits) === 11 && strpos($digits, '80') === 0) {
			return self::result('3' . $digits, $iso);
		}
		if ($trunk !== '' && strpos($digits, $trunk) === 0 && self::fits(strlen($digits) - strlen($trunk), $min, $max)) {
			return self::result($code . substr($digits, strlen($trunk)), $iso);
		}
		if (self::fits(strlen($digits), $min, $max)) {
			return self::result($code . $digits, $iso);
		}
		return null;
	}

	private static function matchInternational(string $digits, array $table) {
		uasort($table, static function ($a, $b) {
			return strlen($b[0]) - strlen($a[0]);
		});
		foreach ($table as $iso => $spec) {
			if (strpos($digits, $spec[0]) === 0 && self::fits(strlen($digits) - strlen($spec[0]), $spec[1], $spec[2])) {
				return self::result($digits, (string)$iso);
			}
		}
		return null;
	}

	private static function fits(int $len, int $min, int $max): bool {
		return $len >= $min && $len <= $max;
	}

	private static function result(string $digits, string $iso): array {
		return ['digits' => $digits, 'e164' => '+' . $digits, 'country' => $iso];
	}
}

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
class CcTelegramSmsGateway {
	const CHANNEL_HYBRID = 'viber_sms';
	const CHANNEL_VIBER  = 'viber';
	const CHANNEL_SMS    = 'sms';

	/** Seconds Viber gets before the hybrid falls back to SMS. */
	const VIBER_TTL = 3600;

	const ENDPOINTS = [
		'turbosms' => 'https://api.turbosms.ua/',
		'alphasms' => 'https://alphasms.ua/api/json.php',
		'smsfly'   => 'https://sms-fly.ua/api/v2/api.php',
	];

	private $id;
	private $key;

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

/**
 * Pro licence gate + CatCode licence-server client.
 *
 * Pro is unlocked only by a key the server confirmed as valid. A fresh install
 * is plain free: nothing switches itself on, so a merchant who only wanted the
 * free Telegram alerts never sees customer messages appear and vanish a week later.
 *
 * Two ways to get a key, both ending in the same place:
 *
 *  1. Purchase on catcode.com.ua → paste the key → activate().
 *  2. "Try 7 days" tab → e-mail → startTrial() mints a real 7-day key through
 *     POST /wp-json/catcode/v1/trial and activates it right away.
 *
 * Pro here = messages to the CUSTOMER over Viber / SMS. The Telegram alerts to
 * the shop owner stay free and never depend on this class.
 *
 * OpenCart licences are for good: a purchased key carries no expiry, and once the
 * server confirmed a non-trial key `license_owned` latches — Pro stays on even
 * while our server is unreachable. A trial is a loan and ends after 7 days.
 *
 * Storage (cc_telegram_sms_* settings — their own group, so saving the
 * Telegram form, which rewrites module_cc_telegram as a whole, never touches them):
 *  - license_key         — current key
 *  - license_status      — 'valid' | 'invalid' | '' (never checked)
 *  - license_checked_at  — 'Y-m-d H:i:s' of the last server roundtrip
 *  - license_expires_at  — expiry the server reported
 *  - license_data        — JSON of the last response (diagnostics)
 *  - trial_started       — timestamp the merchant started the trial, 0 = never
 *
 * Override endpoints for staging via CATCODE_LICENSE_API / CATCODE_TRIAL_API.
 */
class CcTelegramSmsLicense {
	/** Canonical module slug — MUST match post_name of the module CPT. */
	const EXPECTED_SLUG = 'opencart-telegram-notifications';

	/** Free trial length in days. Paid licences are annual (1–5 years). */
	const TRIAL_DAYS = 7;

	/** Days the cached 'valid' stays authoritative while the server is down. */
	const GRACE_DAYS = 14;

	/** Setting prefix. */
	const PREFIX = 'cc_telegram_sms_';

	/** Setting group code used by editSetting(). */
	const GROUP = 'cc_telegram_sms';

	const DEFAULT_LICENSE_API = 'https://catcode.com.ua/wp-json/catcode/v1/license';
	const DEFAULT_TRIAL_API   = 'https://catcode.com.ua/wp-json/catcode/v1/trial';

	/** How often the stored key is silently re-verified in the background. */
	const VERIFY_EVERY = 86400;

	const HTTP_TIMEOUT = 8;

	// -----------------------------------------------------------------------
	// State
	// -----------------------------------------------------------------------

	/**
	 * Pro is on when the server said 'valid' and that answer is still fresh.
	 * An unreachable server does not revoke Pro for GRACE_DAYS — a merchant must
	 * not lose paid features because our site had an outage.
	 */
	public static function isPro($registry): bool {
		// A purchase, once confirmed, is confirmed for good. The flag is only ever
		// written after the server blessed a non-trial key, and only cleared when the
		// owner detaches the licence themselves.
		if (self::isOwned($registry)) {
			return true;
		}

		$config = $registry->get('config');

		if ((string)$config->get(self::PREFIX . 'license_status') !== 'valid') {
			return false;
		}

		$checkedAt = (string)$config->get(self::PREFIX . 'license_checked_at');

		if ($checkedAt === '') {
			return false;
		}

		// A trial also has to be inside its own window. The server enforces this on
		// every verify, but the local check means a trial cannot be stretched by
		// cutting the site off from the internet: the grace window below exists for
		// paying customers, not for trials.
		if (self::isTrialKey($registry) && self::trialDaysLeft($registry) < 1) {
			return false;
		}

		return (time() - (int)strtotime($checkedAt)) <= self::GRACE_DAYS * 86400;
	}

	/** A confirmed purchase — the features stay on even after the term lapses. */
	public static function isOwned($registry): bool {
		return '1' === (string)$registry->get('config')->get(self::PREFIX . 'license_owned');
	}

	/** The stored key came from the trial endpoint rather than from a purchase. */
	public static function isTrialKey($registry): bool {
		return 'trial' === (string)$registry->get('config')->get(self::PREFIX . 'license_kind');
	}

	/**
	 * Are updates and support still covered?
	 *
	 * Separate from isPro() on purpose: after an annual term ends the module keeps
	 * working, but the shop is no longer entitled to new versions. Saying so plainly
	 * is what makes "the features stay" an honest promise rather than a reason never
	 * to renew.
	 */
	public static function updatesActive($registry): bool {
		if (self::isTrialKey($registry)) {
			return self::trialDaysLeft($registry) > 0;
		}

		if (!self::hasKey($registry)) {
			return false;
		}

		$expires = (string)$registry->get('config')->get(self::PREFIX . 'license_expires_at');

		if ($expires === '') {
			return (string)$registry->get('config')->get(self::PREFIX . 'license_status') === 'valid';
		}

		return (int)strtotime($expires) >= time();
	}

	/** A key is stored, whether or not the server has blessed it yet. */
	public static function hasKey($registry): bool {
		return trim((string)$registry->get('config')->get(self::PREFIX . 'license_key')) !== '';
	}

	/**
	 * Offline shape check. Used only to reject obvious typos before spending a
	 * roundtrip — it never grants Pro on its own.
	 */
	public static function keyLooksValid(string $key): bool {
		$key = strtoupper(trim($key));

		if ($key === '') {
			return false;
		}

		return (bool)preg_match('/^[A-Z0-9]{4,10}(-[A-Z0-9]{4,10}){1,6}$/', $key);
	}

	/** True only after the merchant opted in; a never-started trial is not active. */
	public static function trialActive($registry): bool {
		return self::trialStarted($registry) > 0 && self::isPro($registry);
	}

	/** Timestamp the merchant started the trial, or 0 when never started. */
	public static function trialStarted($registry): int {
		return (int)$registry->get('config')->get(self::PREFIX . 'trial_started');
	}

	/** Offered once per install, and never while a key is already in place. */
	public static function trialAvailable($registry): bool {
		return self::trialStarted($registry) <= 0 && !self::hasKey($registry);
	}

	public static function trialDaysLeft($registry): int {
		$started = self::trialStarted($registry);

		if ($started <= 0) {
			return self::TRIAL_DAYS;
		}

		return max(0, self::TRIAL_DAYS - (int)floor((time() - $started) / 86400));
	}

	/** Expiry the server reported for the current key ('' when open-ended). */
	public static function expiresAt($registry): string {
		$raw = (string)$registry->get('config')->get(self::PREFIX . 'license_expires_at');

		if ($raw === '') {
			return '';
		}

		$ts = (int)strtotime($raw);

		return $ts > 0 ? date('Y-m-d', $ts) : '';
	}

	/**
	 * Settings the form never posts: they are written by this client and by the
	 * dismiss button. save() must carry them over untouched, otherwise every
	 * "Save" would clear the activated licence.
	 */
	public static function serverOwned(): array {
		return [
			self::PREFIX . 'license_key',
			self::PREFIX . 'license_status',
			self::PREFIX . 'license_checked_at',
			self::PREFIX . 'license_expires_at',
			self::PREFIX . 'license_data',
			self::PREFIX . 'trial_started',
			self::PREFIX . 'license_kind',
			self::PREFIX . 'license_owned',
		];
	}

	// -----------------------------------------------------------------------
	// Server calls
	// -----------------------------------------------------------------------

	/**
	 * Activate a pasted key.
	 *
	 * Two steps: verify first, then activate. verify() consumes no activation
	 * slot, so a key meant for another CatCode product (or a typo) doesn't burn
	 * one of the merchant's sites.
	 */
	public static function activate($registry, string $key): array {
		$key = trim($key);

		if ($key === '') {
			return ['ok' => false, 'error' => 'missing_key'];
		}

		$pre = self::call($registry, $key, 'verify');

		if (empty($pre['ok'])) {
			return $pre;
		}

		$result = self::call($registry, $key, 'activate');
		self::store($registry, $key, $result);

		return $result;
	}

	/** Re-verify the stored key. */
	public static function verify($registry): array {
		$key = trim((string)$registry->get('config')->get(self::PREFIX . 'license_key'));

		if ($key === '') {
			return ['ok' => false, 'error' => 'no_key'];
		}

		$result = self::call($registry, $key, 'verify');
		self::store($registry, $key, $result);

		return $result;
	}

	/** Release the server-side slot and clear the local cache. */
	public static function deactivate($registry): array {
		$key    = trim((string)$registry->get('config')->get(self::PREFIX . 'license_key'));
		$result = $key === '' ? ['ok' => true, 'note' => 'no_key_stored'] : self::call($registry, $key, 'deactivate');

		self::write($registry, [
			self::PREFIX . 'license_key'        => '',
			self::PREFIX . 'license_status'     => '',
			self::PREFIX . 'license_checked_at' => '',
			self::PREFIX . 'license_expires_at' => '',
			self::PREFIX . 'license_data'       => '',
			// The latch goes with the key: releasing is how a shop moves its licence.
			self::PREFIX . 'license_kind'       => '',
			self::PREFIX . 'license_owned'      => '',
		]);

		return $result;
	}

	/**
	 * Mint a 7-day trial key for this shop and activate it. `trial_started` is
	 * written only after a key really arrived, so a rejected request leaves the
	 * trial on offer.
	 */
	public static function startTrial($registry, string $email): array {
		$email = trim($email);

		if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
			return ['ok' => false, 'error' => 'bad_email'];
		}

		if (!self::trialAvailable($registry)) {
			return ['ok' => false, 'error' => 'trial_used_site'];
		}

		$response = self::post(self::trialEndpoint(), [
			'email'       => $email,
			'module_slug' => self::EXPECTED_SLUG,
			'site_url'    => self::siteUrl($registry),
		]);

		if (empty($response['ok']) || empty($response['key'])) {
			return $response;
		}

		$key        = (string)$response['key'];
		$activation = self::call($registry, $key, 'activate');

		self::store($registry, $key, $activation, [self::PREFIX . 'trial_started' => (string)time(), self::PREFIX . 'license_kind' => 'trial']);

		$activation['key']        = $key;
		$activation['expires_at'] = (string)($response['expires_at'] ?? ($activation['expires_at'] ?? ''));

		return $activation;
	}

	/**
	 * Re-verify at most once a day, quietly. OpenCart has no reliable cron, so
	 * this piggybacks on the admin page load; a network failure is a no-op
	 * thanks to the grace window in isPro().
	 */
	public static function maybeVerify($registry): bool {
		$config = $registry->get('config');

		if (trim((string)$config->get(self::PREFIX . 'license_key')) === '') {
			return false;
		}

		$checkedAt = (string)$config->get(self::PREFIX . 'license_checked_at');

		if ($checkedAt !== '' && (time() - (int)strtotime($checkedAt)) < self::VERIFY_EVERY) {
			return false;
		}

		self::verify($registry);

		return true;
	}

	// -----------------------------------------------------------------------
	// Plumbing
	// -----------------------------------------------------------------------

	public static function licenseEndpoint(): string {
		return defined('CATCODE_LICENSE_API') ? (string)constant('CATCODE_LICENSE_API') : self::DEFAULT_LICENSE_API;
	}

	public static function trialEndpoint(): string {
		return defined('CATCODE_TRIAL_API') ? (string)constant('CATCODE_TRIAL_API') : self::DEFAULT_TRIAL_API;
	}

	/** One licence-API call, with the per-product guard applied to the answer. */
	private static function call($registry, string $key, string $action): array {
		$data = self::post(self::licenseEndpoint(), [
			'key'      => $key,
			'site_url' => self::siteUrl($registry),
			'action'   => $action,
		]);

		// Per-product enforcement: a key issued for another CatCode module must
		// not unlock this module's Pro. Older server builds may omit product_slug —
		// absent means "no info, trust it"; present-and-different hard-fails.
		if (!empty($data['ok']) && isset($data['product_slug']) && $data['product_slug'] !== ''
			&& $data['product_slug'] !== self::EXPECTED_SLUG) {
			$data['ok']    = false;
			$data['error'] = 'wrong_product';
		}

		// The server leaves `error` blank on a failed verify — normalize it so
		// the UI can say what to do instead of a generic "key invalid".
		if (empty($data['ok']) && empty($data['error'])) {
			if (!empty($data['expired'])) {
				$data['error'] = 'expired';
			} elseif ((int)($data['http'] ?? 0) === 401) {
				$data['error'] = 'invalid_key';
			} elseif ((int)($data['http'] ?? 0) === 403) {
				$data['error'] = 'limit_reached';
			}
		}

		return $data;
	}

	/**
	 * POST JSON and decode. A transport failure returns ok=false without an
	 * error the server never sent, so an outage never flips status to invalid.
	 */
	private static function post(string $url, array $payload): array {
		if (!function_exists('curl_init')) {
			return ['ok' => false, 'error' => 'no_curl', 'http' => 0];
		}

		$ch = curl_init($url);

		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POST           => true,
			CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
			CURLOPT_POSTFIELDS     => (string)json_encode($payload),
			CURLOPT_TIMEOUT        => self::HTTP_TIMEOUT,
			CURLOPT_CONNECTTIMEOUT => 5,
		]);

		$raw  = curl_exec($ch);
		$err  = curl_error($ch);
		$http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($raw === false) {
			return ['ok' => false, 'error' => 'network', 'message' => $err, 'http' => 0];
		}

		$data = json_decode((string)$raw, true);

		if (!is_array($data)) {
			return ['ok' => false, 'error' => 'bad_response', 'http' => $http, 'raw' => substr((string)$raw, 0, 500)];
		}

		$data['http']        = $http;
		$data['verified_at'] = date('Y-m-d H:i:s');

		return $data;
	}

	/**
	 * Storefront URL as scheme+host. The server normalizes the same way, so
	 * http vs https and trailing slashes don't burn a second activation slot.
	 */
	private static function siteUrl($registry): string {
		$base = '';

		foreach (['HTTPS_CATALOG', 'HTTPS_SERVER', 'HTTP_CATALOG', 'HTTP_SERVER'] as $name) {
			if (defined($name)) {
				$base = (string)constant($name);

				break;
			}
		}

		if ($base === '') {
			$base = (string)$registry->get('config')->get('config_url');
		}

		$parts = parse_url($base);

		if (!$parts || empty($parts['host'])) {
			return '';
		}

		$scheme = strtolower(isset($parts['scheme']) ? $parts['scheme'] : 'https');

		return $scheme . '://' . strtolower($parts['host']);
	}

	/**
	 * True when the call never reached a verdict: DNS down, connection refused,
	 * a 5xx, or a body that isn't our JSON. Anything here says nothing about the
	 * key itself, so it must not be stored as a verdict.
	 */
	private static function isTransportFailure(array $result): bool {
		if (!empty($result['ok'])) {
			return false;
		}

		$error = (string)(isset($result['error']) ? $result['error'] : '');

		if (in_array($error, ['network', 'no_curl', 'bad_response'], true)) {
			return true;
		}

		$http = (int)(isset($result['http']) ? $result['http'] : 0);

		return $http === 0 || $http >= 500;
	}

	/**
	 * Persist key + verdict + raw response.
	 *
	 * A transport failure is NOT a verdict. Writing `invalid` on an unreachable
	 * server made GRACE_DAYS dead code: the first failed daily poll during a
	 * CatCode outage closed Pro on every paying shop, because isPro() checks
	 * status before it ever looks at the grace window. So an outage leaves the
	 * previous status, expiry and checked_at untouched — the grace window keeps
	 * running from the last real answer and closes on its own after GRACE_DAYS.
	 */
	private static function store($registry, string $key, array $result, array $extra = []) {
		if (self::isTransportFailure($result) && self::hasKey($registry)) {
			self::write($registry, array_merge([
				self::PREFIX . 'license_key'  => $key,
				self::PREFIX . 'license_data' => (string)json_encode($result, JSON_UNESCAPED_UNICODE),
			], $extra));

			return;
		}

		self::write($registry, array_merge([
			self::PREFIX . 'license_key'        => $key,
			self::PREFIX . 'license_status'     => !empty($result['ok']) ? 'valid' : 'invalid',
			self::PREFIX . 'license_checked_at' => (string)(isset($result['verified_at']) ? $result['verified_at'] : date('Y-m-d H:i:s')),
			self::PREFIX . 'license_expires_at' => (string)(isset($result['expires_at']) ? $result['expires_at'] : ''),
			self::PREFIX . 'license_data'       => (string)json_encode($result, JSON_UNESCAPED_UNICODE),
		], self::kindPatch($registry, $key, $result, $extra), $extra));
	}

	/**
	 * Trial or purchase, from the server's `trial` flag (or the caller's explicit
	 * kind), plus the purchase latch. A different key starts from a clean slate.
	 */
	private static function kindPatch($registry, string $key, array $result, array $extra): array {
		$patch  = [];
		$config = $registry->get('config');

		if (trim((string)$config->get(self::PREFIX . 'license_key')) !== $key) {
			$patch[self::PREFIX . 'license_kind']  = '';
			$patch[self::PREFIX . 'license_owned'] = '';
		}

		$kind = '';
		if (isset($extra[self::PREFIX . 'license_kind'])) {
			$kind = (string)$extra[self::PREFIX . 'license_kind'];
		} elseif (array_key_exists('trial', $result)) {
			$kind = !empty($result['trial']) ? 'trial' : 'purchase';
		}
		if ($kind !== '') {
			$patch[self::PREFIX . 'license_kind'] = $kind;
		}
		if ($kind === 'purchase' && !empty($result['ok'])) {
			$patch[self::PREFIX . 'license_owned'] = '1';
		}

		return $patch;
	}

	/**
	 * Merge a handful of keys into the module's setting group.
	 *
	 * editSetting() replaces the whole group, so the current rows are read back
	 * first — otherwise saving a licence would wipe the gateway key and the
	 * per-status texts that share this group. The live $config is patched too, so code further
	 * down the same request already sees the new state.
	 */
	private static function write($registry, array $values) {
		$loader = $registry->get('load');
		$loader->model('setting/setting');

		$model   = $registry->get('model_setting_setting');
		$current = $model->getSetting(self::GROUP);

		if (!is_array($current)) {
			$current = [];
		}

		$config = $registry->get('config');

		foreach ($values as $key => $value) {
			$current[$key] = $value;
			$config->set($key, $value);
		}

		$model->editSetting(self::GROUP, $current);
	}
}

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
class CcTelegramSmsNotifier {
	const PREFIX = 'cc_telegram_sms_';
	const GROUP  = 'cc_telegram_sms';

	private $registry;
	private $config;
	private $db;
	private $logger;

	public function __construct($registry) {
		$this->registry = $registry;
		$this->config   = $registry->get('config');
		$this->db       = $registry->get('db');
		$this->logger   = new CcTelegramLogger($this->db);
	}

	/** @return array<string,string> key => default */
	public static function defaults(): array {
		return [
			'enabled'       => '0',
			'gateway'       => 'turbosms',
			'api_key'       => '',
			'channel'       => CcTelegramSmsGateway::CHANNEL_HYBRID,
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
		return $key === 'api_key' && $value !== '' ? CcTelegramCrypto::decrypt($value) : $value;
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

	public function gateway() {
		return new CcTelegramSmsGateway($this->get('gateway'), $this->get('api_key'));
	}

	public function isActive(): bool {
		return $this->get('enabled') === '1' && CcTelegramSmsLicense::isPro($this->registry);
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
	public function handle(array $order, int $statusId, int $oldId, string $comment, callable $render, callable $statusName) {
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
		$number  = CcTelegramSmsPhone::normalise($phone, $country !== '' ? $country : $this->get('country'));

		if ($number === null) {
			$this->logger->log($orderId, $event, $phone !== '' ? mb_substr($phone, 0, 20) : '—', null, 'INVALID_PHONE', false);
			return;
		}

		// Claim first, send second: a parallel addHistory must find the row.
		$rowId = $this->logger->log($orderId, $event, CcTelegramSmsPhone::mask($number['e164']), null, 'SENDING', false);

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
		$number = CcTelegramSmsPhone::normalise($phone, $this->get('country'));
		if ($number === null) {
			return ['ok' => false, 'code' => 0, 'status' => 'INVALID_PHONE', 'message_id' => '', 'transport' => false, 'phone' => $phone];
		}
		$res = $this->deliver($number, $text);
		$this->logger->log(0, 'sms:test', CcTelegramSmsPhone::mask($number['e164']), ($res['code'] > 0 && $res['code'] < 65535) ? $res['code'] : null, $this->logText($res), $res['ok']);
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
			list($iso, $name) = array_map('trim', explode('=', $line, 2));
			$iso = strtoupper($iso);
			if ($name !== '' && ($iso === '*' || preg_match('/^[A-Z]{2}$/', $iso))) {
				$map[$iso] = $name;
			}
		}
		return $map[strtoupper($country)] ?? ($map['*'] ?? '');
	}

	private function deliver(array $number, string $text): array {
		$channel = $this->get('channel');
		if (!in_array($channel, CcTelegramSmsGateway::channels(), true)) {
			$channel = CcTelegramSmsGateway::CHANNEL_HYBRID;
		}
		$sms   = $this->sender('sms_senders', $number['country']);
		$viber = $this->sender('viber_senders', $number['country']);

		if (($channel !== CcTelegramSmsGateway::CHANNEL_VIBER && $sms === '') || ($channel !== CcTelegramSmsGateway::CHANNEL_SMS && $viber === '')) {
			return ['ok' => false, 'code' => 0, 'status' => 'NO_SENDER_FOR_' . $number['country'], 'message_id' => '', 'transport' => false];
		}

		return $this->gateway()->send($number['digits'], $text, $channel, $sms, $viber);
	}

	private function alreadyHandled(int $orderId, string $event): bool {
		$row = $this->db->query("SELECT `log_id` FROM `" . CcTelegramLogger::table() . "`
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

