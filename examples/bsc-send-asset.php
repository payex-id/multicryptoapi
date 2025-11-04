<?php

error_reporting(E_ALL ^ E_DEPRECATED);

require_once __DIR__ . "/../vendor/autoload.php";

use MultiCryptoApi\Api\EthereumApiClient;
use MultiCryptoApi\Provider\EthereumBlockbook;
use MultiCryptoApi\Blockchain\AddressCredentials;
use MultiCryptoApi\Blockchain\Amount;
use MultiCryptoApi\Blockchain\Fee;
use MultiCryptoApi\Blockchain\RpcCredentials;

$keys = include_once __DIR__ . '/keys.php';

$blockbook = new EthereumBlockbook(
	new RpcCredentials(
		'https://bsc.nownodes.io/' . $keys['NowNodes'],
		'https://bsc-blockbook.nownodes.io',
		[
			'api-key' => $keys['NowNodes']
		]
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

$usdt = '0x55d398326f99059ff775485246999027b3197955';
$addr = $blockbook->getAddress($wallet->address);
echo "Address {$addr->address} balance {$addr->balance}\n";

$assets = $blockbook->getAssets($wallet->address);

foreach ($assets as $asset) {
	echo "\tAsset {$asset->type} {$asset->name} ($asset->abbr) {$asset->balance} [{$asset->tokenId}]\n";
}


$fee = new Fee(null, Amount::satoshi(200000));
$tx = $eth->sendAsset($wallet, $usdt, $wallet2->address, "1", 18, $fee);

echo "Transaction sent {$tx->txid}\n";