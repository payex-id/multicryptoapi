<?php

error_reporting(E_ALL ^ E_DEPRECATED);

require_once __DIR__ . "/../vendor/autoload.php";

use Chikiday\MultiCryptoApi\Blockbook\TrxBlockbook;
use Chikiday\MultiCryptoApi\Blockchain\RpcCredentials;
use Chikiday\MultiCryptoApi\Model\IncomingBlock;
use Chikiday\MultiCryptoApi\Stream\TronStream;

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


$stream->reConsiderBlock(59003397, function(IncomingBlock $block) {
	foreach ($block->txs as $item) {
		echo "{$item->txid} {$item->from} {$item->to} {$item->amount->toBtc()}\n";
	 }
});