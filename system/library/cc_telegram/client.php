<?php
namespace Opencart\System\Library\CcTelegram;

/**
 * Telegram Bot API client.
 *
 * All calls are plain JSON POSTs to https://api.telegram.org/bot<token>/<method>.
 * Telegram answers {"ok":true,"result":…} or {"ok":false,"description":…,
 * "error_code":N} — the HTTP status alone is not enough, so both are inspected.
 *
 * Docs: https://core.telegram.org/bots/api
 */
class Client {

	/** Telegram hard-caps a message at 4096 UTF-8 chars. */
	private const MAX_LEN = 4096;

	private string $token;

	public function __construct(string $token) {
		$this->token = trim($token);
	}

	/**
	 * Cheap authenticated read used by the "send test message" button to tell a
	 * bad token apart from a bad chat id.
	 *
	 * @return array{ok:bool,status:int,body:string,json:?array,error:string}
	 */
	public function getMe(): array {
		return $this->request('getMe');
	}

	/**
	 * Pull pending updates so the admin can discover a chat id without leaving
	 * OpenCart: write any message to the bot / add it to a group, then press
	 * "Знайти chat_id".
	 *
	 * @return array{ok:bool,status:int,body:string,json:?array,error:string}
	 */
	public function getUpdates(): array {
		return $this->request('getUpdates', ['limit' => 20]);
	}

	/**
	 * @param string $chatId   Numeric id or @username.
	 * @param string $text     Message body (HTML parse mode).
	 * @param int    $threadId Forum topic id, 0 for none.
	 * @param bool   $silent   Deliver without a notification sound.
	 * @return array{ok:bool,status:int,body:string,json:?array,error:string}
	 */
	public function sendMessage(string $chatId, string $text, int $threadId = 0, bool $silent = false): array {
		$payload = [
			'chat_id'    => $chatId,
			'text'       => self::truncate($text),
			'parse_mode' => 'HTML',
			// Order links would otherwise render a fat preview card under every message.
			'link_preview_options' => ['is_disabled' => true],
		];
		if ($threadId > 0) {
			$payload['message_thread_id'] = $threadId;
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
	public static function truncate(string $text): string {
		if (mb_strlen($text) <= self::MAX_LEN) {
			return $text;
		}
		return mb_substr($text, 0, self::MAX_LEN - 2) . '…';
	}

	/**
	 * @return array{ok:bool,status:int,body:string,json:?array,error:string}
	 */
	private function request(string $method, array $body = []): array {
		if ($this->token === '') {
			return ['ok' => false, 'status' => 0, 'body' => '', 'json' => null, 'error' => 'Bot token is empty'];
		}

		$url  = 'https://api.telegram.org/bot' . rawurlencode($this->token) . '/' . $method;
		$json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		$ch = curl_init();
		curl_setopt_array($ch, [
			CURLOPT_URL            => $url,
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => $json,
			CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_TIMEOUT        => 20,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
		]);

		$raw     = curl_exec($ch);
		$curlErr = curl_error($ch);
		$status  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($raw === false) {
			return ['ok' => false, 'status' => $status, 'body' => '', 'json' => null, 'error' => $curlErr !== '' ? $curlErr : 'Request failed'];
		}

		$raw     = (string)$raw;
		$decoded = json_decode($raw, true);
		$json    = is_array($decoded) ? $decoded : null;

		$ok = ($status >= 200 && $status < 300) && is_array($json) && !empty($json['ok']);

		$error = '';
		if (!$ok) {
			if (is_array($json) && !empty($json['description'])) {
				$error = (string)$json['description'];
			} elseif ($curlErr !== '') {
				$error = $curlErr;
			} else {
				$error = 'HTTP ' . $status;
			}
		}

		return ['ok' => $ok, 'status' => $status, 'body' => $raw, 'json' => $json, 'error' => $error];
	}
}
