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
	]

);

$time = microtime(true);
$tx = $blockbook->getTx("0x9def784b5e613d67423a4a0440f5245d181b266fe26342eb19d811cdddb35e69");
$time = microtime(true) - $time;

echo "TX: " . $tx->txid . " loaded for {$time} s.\n";

echo "Tx mined in {$tx->blockNumber} block\n";
