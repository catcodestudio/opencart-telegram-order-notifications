<?php
namespace Opencart\System\Library\CcTelegram;
require_once __DIR__ . '/polyfill.php';

/**
 * At-rest encryption for the stored Telegram bot token: an authenticated
 * HMAC-stream cipher that needs nothing beyond core PHP. New values are never
 * written with sodium: hosts often run CLI and web on different PHP builds,
 * and a token saved where sodium exists could not be read where it does not,
 * so every message silently failed with 404. Old sodium values (cctg1) are
 * still read when sodium is present; a legacy plaintext value is returned
 * as-is on decrypt so an upgrade never loses a token.
 *
 * The key is derived from OpenCart path/db constants, so a value encrypted in
 * admin decrypts from catalog / cron / CLI without any shared option row.
 */
class Crypto {
	private const PREFIX_SODIUM = 'cctg1:';
	private const PREFIX_HMAC   = 'cctg2:';

	public static function encrypt(string $plain): string {
		if ($plain === '') {
			return '';
		}
		$key = self::key();

		$nonce  = random_bytes(16);
		$stream = self::hmacStream($key, $nonce, strlen($plain));
		$cipher = $plain ^ $stream;
		$mac    = hash_hmac('sha256', $nonce . $cipher, $key, true);
		return self::PREFIX_HMAC . base64_encode($nonce . $mac . $cipher);
	}

	public static function decrypt(string $stored): string {
		if ($stored === '') {
			return '';
		}
		$key = self::key();

		if (str_starts_with($stored, self::PREFIX_SODIUM) && function_exists('sodium_crypto_secretbox_open')) {
			$raw = base64_decode(substr($stored, strlen(self::PREFIX_SODIUM)), true);
			if ($raw === false || strlen($raw) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
				return '';
			}
			$nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
			$ct    = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
			$plain = sodium_crypto_secretbox_open($ct, $nonce, substr($key, 0, SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
			return $plain === false ? '' : $plain;
		}

		if (str_starts_with($stored, self::PREFIX_HMAC)) {
			$raw = base64_decode(substr($stored, strlen(self::PREFIX_HMAC)), true);
			if ($raw === false || strlen($raw) < 48) {
				return '';
			}
			$nonce = substr($raw, 0, 16);
			$mac   = substr($raw, 16, 32);
			$ct    = substr($raw, 48);
			$calc  = hash_hmac('sha256', $nonce . $ct, $key, true);
			if (!hash_equals($mac, $calc)) {
				return '';
			}
			$stream = self::hmacStream($key, $nonce, strlen($ct));
			return $ct ^ $stream;
		}

		if (str_starts_with($stored, self::PREFIX_SODIUM)) {
			return ''; // Saved under a PHP build with sodium; re-enter the token in settings.
		}

		return $stored; // Legacy plaintext.
	}

	private static function key(): string {
		$root     = defined('DIR_OPENCART') ? DIR_OPENCART : (defined('DIR_SYSTEM') ? DIR_SYSTEM : __DIR__);
		$material = $root . (defined('DB_DATABASE') ? DB_DATABASE : '');
		return hash('sha256', 'CatCodeTelegramNotify|' . $material, true);
	}

	private static function hmacStream(string $key, string $nonce, int $len): string {
		$out     = '';
		$counter = 0;
		while (strlen($out) < $len) {
			$out .= hash_hmac('sha256', $nonce . pack('N', $counter++), $key, true);
		}
		return substr($out, 0, $len);
	}
}
