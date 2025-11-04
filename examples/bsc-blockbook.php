<?php

error_reporting(E_ALL ^ E_DEPRECATED);

require_once __DIR__ . "/../vendor/autoload.php";

use MultiCryptoApi\Provider\EthereumBlockbook;
use MultiCryptoApi\Blockchain\RpcCredentials;

$keys = include_once __DIR__ . '/keys.php';

$blockbook = new EthereumBlockbook(
	new RpcCredentials(
		'https://bsc.nownodes.io/' . $keys['NowNodes'],
		'https://bsc-blockbook.nownodes.io',
		[
			'api-key' => $keys['NowNodes']
		]
	),
	'wss://bsc-mainnet.infura.io/ws/v3/' . $keys['Infura'],
	56
);

$block = $blockbook->getBlock();
echo "Last block number: {$block->height} hash {$block->hash}, ".count($block->txids)." txs\n";

$tx = $blockbook->getTx($txid = $block->txids[0]);
echo "TX {$tx->txid} mined in block {$tx->blockNumber}, \n
\t{$tx->inputs[0]->address} {$tx->outputs[0]->amount} to {$tx->outputs[0]->address}\n";

foreach ($tx->outputs[0]->assets as $asset) {
	echo "\t\tAnd {$asset->type} {$asset->name} {$asset->balance} {$asset->abbr}\n";
}

//$addr = $blockbook->getAddress($tx->inputs[0]->address);
$addr = $blockbook->getAddress($tx->inputs[0]->address);
echo "Address {$addr->address} balance {$addr->balance->toBtc()}\n";

$assets = $blockbook->getAssets($addr->address);
foreach ($assets as $asset) {
	echo "\tAsset {$asset->type} {$asset->name} ($asset->abbr) {$asset->balance}\n";
}

$txs = $blockbook->getAddressTransactions($addr->address);
foreach ($txs->transactions as $tx) {
	echo "\tTransaction {$tx->txid} => {$tx->outputs[0]->address} {$tx->outputs[0]->amount} (fee {$tx->fee})\n";
	foreach ($tx->outputs[0]->assets as $asset) {
		echo "\t\tAsset {$asset->type} {$asset->name} {$asset->balance} {$asset->abbr}\n";
	}
}