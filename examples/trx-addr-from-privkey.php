<?php

error_reporting(E_ALL ^ E_DEPRECATED);

require_once __DIR__ . "/../vendor/autoload.php";

use MultiCryptoApi\Api\TronApiClient;
use MultiCryptoApi\Provider\TrxBlockbook;
use MultiCryptoApi\Blockchain\RpcCredentials;
use MultiCryptoApi\Model\Enum\TransactionDirection;

$keys = include_once __DIR__ . '/keys.php';

$blockbook = new TrxBlockbook(
	new RpcCredentials(
		'https://trx.nownodes.io',
		'https://trx-blockbook.nownodes.io',
		[
			'api-key'          => $keys['NowNodes'], // for now nodes
			'TRON-PRO-API-KEY' => $keys['TronScan'], // for tronscan
		]
	),
	$keys['TronGridApiKey']
);

$api = new TronApiClient($blockbook);


$addr = $api->createWallet();
echo "Address {$addr->address}\n";

$addr2 = $api->createFromPrivateKey($addr->privateKey);
echo "Address2 {$addr2->address}\n";