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

$tx = $blockbook->getAddressTransactions('TWTEYFUK2jk92iDXn1EiPcqtUVL95Bhhaj');
foreach ($tx->transactions as $item) {
	$list = $item->getRelatedTransactions('TWTEYFUK2jk92iDXn1EiPcqtUVL95Bhhaj');

	echo "Transaction {$item->txid} has " . count($list) . " incoming transactions\n";
	foreach ($list as $_tx) {
		echo "\t{$_tx->from} => {$_tx->to} {$_tx->amount} [{$_tx->contractAddress}] \n";
	}
}