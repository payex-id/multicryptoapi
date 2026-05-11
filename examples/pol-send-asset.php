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
		'https://matic.nownodes.io/' . $keys['NowNodes'],
		'https://maticbook.nownodes.io',
		[
			'api-key' => $keys['NowNodes'],
		],
	),
	'wss://polygon-mainnet.infura.io/ws/v3/' . $keys['Infura'],
	137
);

$eth = new EthereumApiClient($blockbook);

$wallet = new AddressCredentials(
	'<put your address here>',
	'<put your private key here>'
);

$to = "<put your to address here>";
$amount = "<put your amount here>";

// USDT on Polygon PoS (bridged)
$usdt = '0xc2132d05d31c914a87c6611c10748aeb04b58e8f';
$addr = $blockbook->getAddress($wallet->address);
echo "Address {$addr->address} balance {$addr->balance}\n";

$assets = $blockbook->getAssets($wallet->address);

foreach ($assets as $asset) {
	echo "\tAsset {$asset->type} {$asset->name} ($asset->abbr) {$asset->balance} [{$asset->tokenId}]\n";
}

$tx = $eth->sendAsset($wallet, $usdt, $to, $amount, 6);

echo "Transaction sent {$tx->txid}\n";
