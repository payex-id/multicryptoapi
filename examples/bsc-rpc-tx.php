<?php

error_reporting(E_ALL ^ E_DEPRECATED);

require_once __DIR__ . "/../vendor/autoload.php";

use Chikiday\MultiCryptoApi\Blockbook\EthereumRpc;
use Chikiday\MultiCryptoApi\Blockchain\RpcCredentials;

$keys = include_once __DIR__ . '/keys.php';

$blockbook = new EthereumRpc(
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
		'tokens' => [
			$contractAddress = '0x55d398326f99059ff775485246999027b3197955',
		],
	]

);

$time = microtime(true);
$tx = $blockbook->getTx("0x1f7d99a11f60134ef12a81978e144560e5b73142ccccf8cfbf3e9285f7d06b48");
$time = microtime(true) - $time;

echo "TX: " . $tx->txid . " loaded for {$time} s.\n";

echo "Tx mined in {$tx->blockNumber} block\n";
