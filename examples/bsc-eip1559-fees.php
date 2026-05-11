<?php
/**
 * EIP-1559 fee suggestion for BSC (BNB Smart Chain) via RPC.
 * Same logic as eth-eip1559-fees.php: eth_feeHistory with fallback to eth_gasPrice.
 */
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
		],
	),
	'wss://bsc-mainnet.infura.io/ws/v3/' . $keys['Infura'],
	56
);
$fees = $blockbook->getEIP1559Fees();
echo "BNB SMART CHAIN EIP-1559 fees (gwei):\n";
printf(
	"  maxPriorityFeePerGas: %.6f\n  maxFeePerGas:         %.6f\n  baseFeePerGas:        %.6f\n",
	$fees['maxPriorityFeePerGas'] / 1e9,
	$fees['maxFeePerGas'] / 1e9,
	$fees['baseFeePerGas'] / 1e9
);
echo "\nRaw wei:\n";
var_dump($fees);