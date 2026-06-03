<?php

error_reporting(E_ALL ^ E_DEPRECATED);

require_once __DIR__ . "/../vendor/autoload.php";

use Chikiday\MultiCryptoApi\Api\EthereumApiClient;
use Chikiday\MultiCryptoApi\Blockbook\EthereumBlockbook;
use Chikiday\MultiCryptoApi\Blockchain\RpcCredentials;


$keys = include_once __DIR__ . '/keys.php';

$blockbook = new EthereumBlockbook(
	new RpcCredentials(
		'https://matic.nownodes.io/' . $keys['NowNodes'],
		'https://maticbook.nownodes.io',
		[
			'api-key' => $keys['NowNodes'],
		],
	),
	'wss://polygon-mainnet.infura.io/ws/v3/' . $keys['Infura'],
	137
);

$eth = new EthereumApiClient($blockbook);

$tx = $blockbook->getTx('<txid>');
echo "Txid: {$tx->txid}\n";
echo "Confirmations: {$tx->confirmations}\n";
foreach ($tx->outputs as $output) {
	echo "Output: {$output->index}\n";
	foreach ($output->assets as $asset) {
		echo "\tAsset: {$asset->type} {$asset->name} {$asset->getFrom()} -> {$asset->getTo()} {$asset->balance}\n";
	}
}

print_r($tx);