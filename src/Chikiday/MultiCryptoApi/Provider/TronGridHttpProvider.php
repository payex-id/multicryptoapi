<?php

namespace Chikiday\MultiCryptoApi\Provider;

use Chikiday\MultiCryptoApi\Util\TronGridRateLimit;
use IEXBase\TronAPI\Provider\HttpProvider;

class TronGridHttpProvider extends HttpProvider
{
	public function request($url, array $payload = [], string $method = 'get'): array
	{
		return TronGridRateLimit::retry(
			fn() => parent::request($url, $payload, $method),
		);
	}
}
