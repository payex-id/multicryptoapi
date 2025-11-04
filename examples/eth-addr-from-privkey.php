<?php

error_reporting(E_ALL ^ E_DEPRECATED);

require_once __DIR__ . "/../vendor/autoload.php";

use MultiCryptoApi\Api\EthereumApiClient;
use MultiCryptoApi\Provider\EthereumBlockbook;
use MultiCryptoApi\Blockchain\RpcCredentials;

$keys = include_once __DIR__ . '/keys.php';

$blockbook = new EthereumBlockbook(
	new RpcCredentials(
		'https://eth-sepolia.nownodes.io/' . $keys['NowNodes'],
		'https://ethbook-sepolia.nownodes.io',
		[
			'api-key' => $keys['NowNodes'],
		]
	)
);

$eth = new EthereumApiClient($blockbook);

$addr = $eth->createWallet();
echo "Address {$addr->address}\n";

$addr2 = $eth->createFromPrivateKey($addr->privateKey);
echo "Address2 {$addr2->address}\n";