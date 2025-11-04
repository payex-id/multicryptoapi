<?php

error_reporting(E_ALL ^ E_DEPRECATED);

require_once __DIR__ . "/../vendor/autoload.php";

use MultiCryptoApi\Provider\TrxBlockbook;
use MultiCryptoApi\Blockchain\RpcCredentials;
use MultiCryptoApi\Model\IncomingBlock;
use MultiCryptoApi\Model\IncomingTransaction;
use MultiCryptoApi\Stream\TronStream;

$keys = include_once __DIR__ . '/keys.php';

$blockbook = new TrxBlockbook(
	new RpcCredentials(
		'https://trx.nownodes.io',
		'https://trx-blockbook.nownodes.io',
		[
			'api-key' => $keys['NowNodes'], // for now nodes
			'TRON-PRO-API-KEY' => $keys['TronScan'], // for tronscan
		]
	),
	$keys['TronGridApiKey']
);

$stream = new TronStream($blockbook);
$stream->debug = true;


$i = 0;

ob_implicit_flush(true);
$stream->subscribeToAnyTransaction(function (IncomingTransaction $tx) use(&$i) {
	$i++;
	echo "Tx {$tx->txid} [$i]\n";
});
echo "Subscribed to transactions\n";

$stream->subscribeToAnyBlock(function (IncomingBlock $block) {
	$memory = round(memory_get_usage() / 1024 / 1024, 2);
	echo "\nBlock mined #{$block->blockNumber} had " . count($block->txs) . " txes [mem {$memory}]";
});
echo "Subscribed to blocks\n";
/*

$stream->subscribeToAddresses(
	$addrs = [
		'TU4vEruvZwLLkSfV9bNw12EJTPvNr7Pvaa',
	],
	function (IncomingTransaction $tx) {
		echo "\n\n\n======\n\n\n";
		echo "\t\tNEW TRANSACTION [{$tx->txid}]\n";
		echo "\t\tTO: {$tx->to}\n";
		echo "\t\tFROM: {$tx->from}\n";
		echo "\t\tAMOUNT: {$tx->amount}\n";
		echo "\t\tCONTRACT: {$tx->contractAddress}\n";
		echo "\n\n\n======\n\n\n";
	}
);

echo "Subscribed to addresses: " . implode(",", $addrs) . "\n";*/