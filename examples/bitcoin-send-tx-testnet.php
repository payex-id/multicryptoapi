<?php

require_once __DIR__ . "/../vendor/autoload.php";

use BitWasp\Bitcoin\Network\Networks\BitcoinTestnet;
use MultiCryptoApi\Api\BitcoinApiClient;
use MultiCryptoApi\Provider\BitcoinBlockbook;
use MultiCryptoApi\Blockchain\AddressCredentials;
use MultiCryptoApi\Blockchain\Amount;
use MultiCryptoApi\Blockchain\Fee;
use MultiCryptoApi\Blockchain\RpcCredentials;

$keys = include_once __DIR__ . '/keys.php';

require __DIR__ . "/../vendor/autoload.php";

$transport = new BitcoinBlockbook(
	new RpcCredentials(
		'https://btc-testnet.nownodes.io',
		'https://btcbook-testnet.nownodes.io',
		[
			'api-key' => $keys['NowNodes'],
		]
	),
	new BitcoinTestnet(),
);
$api = new BitcoinApiClient($transport);

$wallet = new AddressCredentials(
	"tb1qks3utqal5g424quu50xqg9xnr5wk7f5gjfrja6",
	"c4f9f3d2e4f3df7bf053b99e7f792a946ac61d5a42978e755b1715d8bd5c459d",
);

$wallet2 = new AddressCredentials(
	"tb1qz9jv2vjk0tjgfdfnv4fkzdga0gvtjd3hwjxgmj",
	"afeab8bb2eb62c685c4387913a5da9cd0e9d8c2e8d8431f0413dd45cfd7d6484"
);

if (!isset($wallet)) {
	$wallet = $api->createWallet();
	var_dump($wallet);

	die;
}

echo "Wallet: " . $wallet->address . "\n";
$balance = $transport->getAddress($wallet->address)->balance;

echo "Balance (satoshi): " . $balance->satoshi . "\n";
echo "Balance (btc): " . $balance->toBtc() . "\n";

$result = $api->sendCoins($wallet, $wallet2->address, "0.0000075", new Fee(Amount::satoshi(300)));

echo "Tx sent: {$result->txid}\n";
$balance = $transport->getAddress($wallet2->address)->balance;
var_dump($result);
