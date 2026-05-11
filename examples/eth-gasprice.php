<?php

/**
 * Legacy-style gas price via eth_gasPrice (wei → gwei).
 * For EIP-1559 fields (maxFeePerGas / maxPriorityFeePerGas) see eth-eip1559-fees.php.
 */

error_reporting(E_ALL ^ E_DEPRECATED);

require_once __DIR__ . "/../vendor/autoload.php";

use Chikiday\MultiCryptoApi\Blockbook\EthereumBlockbook;
use Chikiday\MultiCryptoApi\Blockchain\RpcCredentials;

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

$gasPrice = $blockbook->getGasPrice();
var_dump($gasPrice / 1e9); // in gwei
