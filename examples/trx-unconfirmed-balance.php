<?php

error_reporting(E_ALL ^ E_DEPRECATED);

require_once __DIR__ . "/../vendor/autoload.php";

use MultiCryptoApi\Provider\TrxBlockbook;
use MultiCryptoApi\Blockchain\RpcCredentials;

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


$trxBalance = $blockbook->getAddress('TU4vEruvZwLLkSfV9bNw12EJTPvNr7Pvaa', true);
echo "Balance: " . $trxBalance->balance . "\n";
foreach($trxBalance->assets as $asset) {
	echo "Asset: ". $asset->name . " {$asset->balance}\n";
}
//
$trxBalance = $blockbook->getUnconfirmedBalance('TU4vEruvZwLLkSfV9bNw12EJTPvNr7Pvaa');
echo "Unconfirmed Balance: " . $trxBalance->balance . "\n";
foreach($trxBalance->assets as $asset) {
	echo "Asset: ". $asset->name . " {$asset->balance}\n";
}

