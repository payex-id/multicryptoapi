<?php

error_reporting(E_ALL ^ E_DEPRECATED);

require_once __DIR__ . "/../vendor/autoload.php";

use MultiCryptoApi\Api\EthereumApiClient;
use MultiCryptoApi\Provider\EthereumBlockbook;
use MultiCryptoApi\Blockchain\RpcCredentials;

$keys = include_once __DIR__ . '/keys.php';

$blockbook = new EthereumBlockbook(
	new RpcCredentials(
		'https://eth.nownodes.io/' . $keys['NowNodes'],
		'https://eth-blockbook.nownodes.io',
		[
			'api-key' => $keys['NowNodes'],
		]
	)
);

$eth = new EthereumApiClient($blockbook);

$tx = $blockbook->getTx('0x0c79ff5926f1d7231bcc65c5b98e34efc66a69cb94b4dc62afdf36e9a0a67b97');
echo "Txid: {$tx->txid}\n";
foreach ($tx->outputs as $output) {
	echo "Output: {$output->index}\n";
	foreach ($output->assets as $asset) {
		echo "\tAsset: {$asset->type} {$asset->name} {$asset->getFrom()} -> {$asset->getTo()} {$asset->balance}\n";
	}
}


$txs = $blockbook->getAddressTransactions('0x9642b23Ed1E01Df1092B92641051881a322F5D4E');
foreach ($txs->transactions as $tx) {
	echo "Transaction: {$tx->txid}\n";
    foreach ($tx->outputs as $output) {
        echo "\tOutput: {$output->index}\n";
        foreach ($output->assets as $asset) {
            echo "\t\tAsset: {$asset->type} {$asset->name} {$asset->getFrom()} -> {$asset->getTo()} {$asset->balance}\n";
        }
    }
}