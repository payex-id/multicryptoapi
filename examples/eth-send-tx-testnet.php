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
		'https://eth-sepolia.nownodes.io/' . $keys['NowNodes'],
		'https://ethbook-sepolia.nownodes.io',
		[
			'api-key' => $keys['NowNodes'],
		]
	)
);

$eth = new EthereumApiClient($blockbook);

$wallet = new AddressCredentials(
	'926cf745f1cc22c2817ad5f6396a640b0f176442',
	'67cc871b4016d8e325fd7fe26fdae46044483dd68a58d509818d801f469b618b'
);

$wallet2 = new AddressCredentials(
	'48db137f2b6ef55af2aa90b216172e86a5c10845',
	'0a16116bc0ac1ef71f81836cae187f9b379f4c3d8f8c827afe50f0ed01631577a'
);

$addr = $blockbook->getAddress($wallet->address);
echo "Address {$addr->address} balance {$addr->balance}\n";

$fee = new Fee(null, Amount::satoshi(21000));
$tx = $eth->sendCoins($wallet, $wallet2->address, "0.05", $fee);

echo "Transaction sent {$tx->txid}\n";