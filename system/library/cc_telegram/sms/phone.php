<?php
namespace Opencart\System\Library\CcTelegram\Sms;

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
class Phone {

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
	public static function normalise(string $raw, string $country): ?array {
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

	private static function matchNational(string $digits, string $iso, array $spec): ?array {
		[$code, $min, $max, $trunk] = $spec;

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

	private static function matchInternational(string $digits, array $table): ?array {
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
