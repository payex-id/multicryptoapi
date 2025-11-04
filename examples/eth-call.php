<?php

error_reporting(E_ALL ^ E_DEPRECATED);

require_once __DIR__ . "/../vendor/autoload.php";

use MultiCryptoApi\Provider\EthereumBlockbook;
use MultiCryptoApi\Blockchain\RpcCredentials;
use Web3\Contracts\Ethabi;

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
$decimals = hexdec($blockbook->evmCall($address, 'decimals()'));

$abi = new Ethabi();
$name = $blockbook->evmCall($address, 'name()');
$name = $abi->decodeParameter('string', $name);
$symbol = $blockbook->evmCall($address, 'symbol()');
$symbol = $abi->decodeParameter('string', $symbol);
echo "Contract {$address} has {$decimals} decimals, name: {$name}, symbol: {$symbol}\n";