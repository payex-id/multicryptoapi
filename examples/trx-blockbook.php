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


$block = $blockbook->getBlock();
echo "Last block number: {$block->height} hash {$block->hash}, ".count($block->txids)." txs\n";

$tx = $blockbook->getTx($txid = $block->txids[0]);
echo "TX {$tx->txid} mined in block {$tx->blockNumber}, \n
\t{$tx->inputs[0]->address} {$tx->outputs[0]->amount} to {$tx->outputs[0]->address}\n";

foreach ($tx->outputs[0]->assets as $asset) {
	echo "\t\tAnd asset {$asset->name} ({$asset->abbr}) {$asset->balance}\n";
}

$addr = $blockbook->getAddress($tx->inputs[0]->address);
echo "Address {$addr->address} balance {$addr->balance->toBtc()}\n";

$assets = $blockbook->getAssets($addr->address);
foreach ($assets as $asset) {
	echo "Asset {$asset->type} {$asset->name} ($asset->abbr) {$asset->balance}\n";
}

$txs = $blockbook->getAddressTransactions($addr->address);
foreach ($txs->transactions as $tx) {
	echo "Transaction {$tx->txid} => {$tx->outputs[0]->address} {$tx->outputs[0]->amount} (fee {$tx->fee})\n";
	foreach ($tx->outputs[0]->assets as $asset) {
		echo "\t\tAsset {$asset->type} {$asset->name} {$asset->balance} {$asset->abbr}\n";
	}
}