<?php

error_reporting(E_ALL ^ E_DEPRECATED);

require_once __DIR__ . "/../vendor/autoload.php";

use Chikiday\MultiCryptoApi\Api\EthereumApiClient;
use Chikiday\MultiCryptoApi\Blockbook\EthereumBlockbook;
use Chikiday\MultiCryptoApi\Blockchain\AddressCredentials;
use Chikiday\MultiCryptoApi\Blockchain\Amount;
use Chikiday\MultiCryptoApi\Blockchain\Fee;
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

$eth = new EthereumApiClient($blockbook);

$wallet = new AddressCredentials(
	'<put here your address>',
	'<put here your private-keu>'
);

$wallet2 = new AddressCredentials(
	'<put here your address>',
	'<put here your private-keu>'
);

$addr = $blockbook->getAddress($wallet->address);
echo "Address {$addr->address} balance {$addr->balance}\n";

$fee = new Fee(null, Amount::satoshi(21000));
$tx = $eth->sendCoins($wallet, $wallet2->address, "0.0001", $fee);

echo "Transaction sent {$tx->txid}\n";