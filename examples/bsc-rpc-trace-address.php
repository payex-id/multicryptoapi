<?php

error_reporting(E_ALL ^ E_DEPRECATED);

require_once __DIR__ . "/../vendor/autoload.php";

use Chikiday\MultiCryptoApi\Blockbook\EthereumRpc;
use Chikiday\MultiCryptoApi\Blockchain\RpcCredentials;

$keys = include_once __DIR__ . '/keys.php';

$blockbook = new EthereumRpc(
	new RpcCredentials(
		"https://side-omniscient-pine.bsc.quiknode.pro/{$keys["QuickNode"]}/",
		'https://bsc-blockbook.nownodes.io',
		[
			'api-key' => $keys['NowNodes'],
		]
	),
	null,
	56,
	[
		'tokens' => [
			$contractAddress = '0x55d398326f99059ff775485246999027b3197955',
		],
		'erc20Lookback' => 50000,
		'erc20BlocksPerQuery' => 10000
	]

);

$time = microtime(true);
$txs = $blockbook->getAddressTransactions("0x4b4737cc2b98e4fcd4dd39355a44cb4effb1b856");
$time = microtime(true) - $time;

echo "TXs: " . count($txs->transactions) . " loaded for {$time} s.\n";

foreach ($txs->transactions as $tx) {
	echo "TX: {$tx->txid} mined in block {$tx->blockNumber}, fee {$tx->fee}\n";
	foreach ($tx->inputs as $input) {
		echo "\tInput: {$input->address} {$input->amount->toBtc()}\n";
	}
	foreach ($tx->outputs as $output) {
		echo "\tOutput: {$output->address} {$output->amount->toBtc()}\n";
	}
}