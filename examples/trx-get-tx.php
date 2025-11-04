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
);


$txs = $blockbook->getAddressTransactions('TCU2Pf1wYqd84Ez9LrExHHcvFjEhtsWEb2');
foreach ($txs->transactions as $tx) {
	echo "Transaction {$tx->txid} => {$tx->outputs[0]->address} {$tx->outputs[0]->amount}\n";
	foreach ($tx->outputs[0]->assets as $asset) {
		echo "\t\t{$asset->name} {$asset->balance} {$asset->abbr}\n";
	}
}
