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
			'api-key' => $keys['NowNodes'],
		]
	),
	'wss://bsc-mainnet.infura.io/ws/v3/' . $keys['Infura'],
	56
);
$block = $blockbook->getBlock();
echo "Last block number: {$block->height} hash {$block->hash}, " . count($block->txids) . " txs\n";
