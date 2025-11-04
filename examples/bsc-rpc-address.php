<?php

error_reporting(E_ALL ^ E_DEPRECATED);

require_once __DIR__ . "/../vendor/autoload.php";

use MultiCryptoApi\Provider\EthereumRpc;
use MultiCryptoApi\Blockchain\RpcCredentials;

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
$address = $blockbook->getAddress("0xcbf593bfb22aa8b4dc561616b2d10dbe0dbe0666", true);
$time = microtime(true) - $time;

echo "Address: " . $address->address . " has balance {$address->balance->toBtc()} an assets:\n";
foreach ($address->assets as $asset) {
	echo "\t\t{$asset->name} {$asset->balance->toBtc()}\n";
}