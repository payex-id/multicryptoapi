<?php

error_reporting(E_ALL ^ E_DEPRECATED);

require_once __DIR__ . "/../vendor/autoload.php";

use BitWasp\Bitcoin\Network\Networks\LitecoinTestnet;
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
		'https://ltc-testnet.nownodes.io',
		'https://ltcbook-testnet.nownodes.io',
		[
			'api-key' => $keys['NowNodes'],
		]
	),
	new LitecoinTestnet(),
);

$api = new BitcoinApiClient($transport);

$wallet1 = new AddressCredentials(
	'n2p8kkaGcRKQfGVWbqcUZZTHru7pgRsu33',
	'37fa30e3edfd9ee3277295558f6f82a6af3ec2ca306d88c1ae099dbe4000be7c'
);
$wallet2 = new AddressCredentials(
	'n4HVtH3qu2D9EVJqNPpyVTrMBim1HgrwaB',
	'cQjF1vhMW34t1HaAmrfgZQMEGTXUL3LupofgdY7nikLzaU3rd2i3'
);

$fee = new Fee(null);
$fee->setMinFee(Amount::satoshi(250))->setSubtractFromAmount(true);
$result = $api->sendCoins($wallet1, $wallet2->address, "0.00001", $fee);

echo "Tx sent: {$result->txid}\n";
var_dump($result);
