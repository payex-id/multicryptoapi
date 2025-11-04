<?php

error_reporting(E_ALL ^ E_DEPRECATED);

require_once __DIR__ . "/../vendor/autoload.php";

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
// getting the number of decimals for USDT Ethereum
$address = '0xdac17f958d2ee523a2206206994597c13d831ec7';
$time = microtime(true);
$token = $blockbook->getTokenInfo($address);
echo "Contract {$token->contract}: name '{$token->name}' symbol '{$token->symbol}' has {$token->decimals} decimals\n";
$time = microtime(true) - $time;

echo "Loading for " . round($time, 6) . " s.";

$token = $blockbook->getTokenInfo('0x4838B106FCe9647Bdf1E7877BF73cE8B0BAD5f97');
var_dump($token);