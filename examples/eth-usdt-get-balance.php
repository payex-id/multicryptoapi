<?php

error_reporting(E_ALL ^ E_DEPRECATED);

require_once __DIR__ . "/../vendor/autoload.php";

use MultiCryptoApi\Api\EthereumApiClient;
use MultiCryptoApi\Provider\EthereumBlockbook;
use MultiCryptoApi\Blockchain\Amount;
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
$address = '0xdAC17F958D2ee523a2206206994597C13D831ec7';

$balance = $blockbook->evmCall($address, 'balanceOf(address)', ['0x9567703f900E0027dF7008D632E85F3bDda3D747'], ['address']);
$balance = hexdec($balance);
$amount = Amount::satoshi($balance, 6)->toBtc();

echo "USDT balance: $amount\n";