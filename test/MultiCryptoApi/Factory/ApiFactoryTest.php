<?php

namespace MultiCryptoApi\Factory;

use MultiCryptoApi\Api\TronApiClient;
use MultiCryptoApi\Blockbook\TrxBlockbook;
use MultiCryptoApi\Blockchain\RpcCredentials;
use PHPUnit\Framework\TestCase;

class ApiFactoryTest extends TestCase
{
	public function testTronFactory()
	{
		$api = ApiFactory::tron(new RpcCredentials('', ''));

		$this->assertInstanceOf(TronApiClient::class, $api);
		$this->assertInstanceOf(TrxBlockbook::class, $api->blockbook());
	}
}
