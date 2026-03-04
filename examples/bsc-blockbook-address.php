<?php

error_reporting(E_ALL ^ E_DEPRECATED);

require_once __DIR__ . "/../vendor/autoload.php";

use Chikiday\MultiCryptoApi\Blockbook\EthereumBlockbook;
use Chikiday\MultiCryptoApi\Blockchain\RpcCredentials;

$keys = include_once __DIR__ . '/keys.php';

$blockbook = new EthereumBlockbook(
	new RpcCredentials(
		'https://bsc.nownodes.io/' . $keys['NowNodes'],
		'https://bsc-blockbook.nownodes.io',
		[
			'api-key' => $keys['NowNodes'],
		]
	),
	null,
	56,
	[
		'tokens'          => [
			$contractAddress = '0x55d398326f99059ff775485246999027b3197955',
		],
		'etherscanApiKey' => $keys['Etherscan'],
	]
);


$address = '0x85a32f91977724749bc61b37844ab101baaf4326';

try {
	$time = microtime(true);
	$list = $blockbook->getAddressTransactions($address);
	$time = round(microtime(true) - $time, 5);
	echo "Loaded " . count($list->transactions) . " txs from Blockbook api, for {$time} seconds.\n";
	foreach ($list->transactions as $transaction) {
		echo "TX {$transaction->txid}\n";
	}
} catch (\Exception $e) {
	echo "Cant load txs by Blockbook: {$e->getMessage()}\n";
}
