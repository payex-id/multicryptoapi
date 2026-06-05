<?php

namespace Chikiday\MultiCryptoApi\Util;

use GuzzleHttp\Exception\ClientException;

class TronGridRateLimit
{
	public const DEFAULT_SUSPEND_SECONDS = 25;

	private const SUSPEND_PATTERN = '/suspended for (\d+)\s*s/i';

	/**
	 * Detect TronGrid frequency limit errors (HTTP 429 or known message).
	 */
	public static function isRateLimited(\Throwable $e): bool
	{
		if ($e instanceof ClientException && $e->getResponse()?->getStatusCode() === 429) {
			return true;
		}

		$message = self::collectMessage($e);

		return str_contains($message, '429 Too Many Requests')
			|| str_contains($message, 'frequency limit')
			|| str_contains($message, 'exceeds the frequency limit');
	}

	/**
	 * Parse "suspended for N s" from TronGrid error body or exception message.
	 */
	public static function parseSuspendSeconds(\Throwable $e): int
	{
		foreach (self::collectTexts($e) as $text) {
			if (preg_match(self::SUSPEND_PATTERN, $text, $matches)) {
				return max(1, (int) $matches[1]);
			}
		}

		return self::DEFAULT_SUSPEND_SECONDS;
	}

	public static function wait(\Throwable $e, int $bufferSeconds = 1): void
	{
		sleep(self::parseSuspendSeconds($e) + $bufferSeconds);
	}

	/**
	 * @return string[]
	 */
	private static function collectTexts(\Throwable $e): array
	{
		$texts = [self::collectMessage($e)];

		if ($e instanceof ClientException) {
			$texts[] = (string) $e->getResponse()?->getBody();
		}

		return array_values(array_filter($texts));
	}

	private static function collectMessage(\Throwable $e): string
	{
		return $e->getMessage();
	}
}
