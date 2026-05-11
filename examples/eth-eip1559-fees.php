<?php

/**
 * EIP-1559 fee suggestion for Ethereum-like RPC (eth_feeHistory, fallback to eth_gasPrice).
 * Same pattern works for other EVM chains: change RpcCredentials URI and optional chainId
 * (e.g. EthereumRpc with chainId 56 for BSC inherits getEIP1559Fees()).
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

$fees = $blockbook->getEIP1559Fees();

echo "ETHEREUM EIP-1559 fees (gwei):\n";
printf(
	"  maxPriorityFeePerGas: %.6f\n  maxFeePerGas:         %.6f\n  baseFeePerGas:        %.6f\n",
	$fees['maxPriorityFeePerGas'] / 1e9,
	$fees['maxFeePerGas'] / 1e9,
	$fees['baseFeePerGas'] / 1e9
);

echo "\nRaw wei:\n";
var_dump($fees);
