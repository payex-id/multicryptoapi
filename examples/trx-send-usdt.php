<?php

error_reporting(E_ALL ^ E_DEPRECATED);

require_once __DIR__ . "/../vendor/autoload.php";

use MultiCryptoApi\Api\TronApiClient;
use MultiCryptoApi\Provider\TrxBlockbook;
use MultiCryptoApi\Blockchain\AddressCredentials;
use MultiCryptoApi\Blockchain\Amount;
use MultiCryptoApi\Blockchain\Fee;
use MultiCryptoApi\Blockchain\RpcCredentials;

$keys = include_once __DIR__ . '/keys.php';

$api = new TronApiClient(
	new TrxBlockbook(
		new RpcCredentials(
			'https://trx.nownodes.io',
			'https://trx-blockbook.nownodes.io',
			[
				'api-key' => $keys['NowNodes'], // for now nodes
				'TRON-PRO-API-KEY' => $keys['TronScan'], // for tronscan
			]
		)
	),
);

$wallet = new AddressCredentials(
	'<put here your address>',
	'<put here your private-keu>'
);


$addr = $api->blockbook()->getAddress($wallet->address);
echo "Wallet balance: {$addr->balance->toBtc()}\n";

$list = $api->blockbook()->getAssets($wallet->address);
foreach ($list as $item) {
	echo "Asset {$item->name} ({$item->abbr}): {$item->balance}\n";
}

echo "Sending 1 USDT as test... ";

// usdt
$result = $api->sendAsset(
	$wallet,
	'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
	'<put your address>',
	"10",
	6,
	new Fee(null, Amount::value(75))
);

echo "Transaction sent, txid {$result->txid}\n";
var_dump($result);

