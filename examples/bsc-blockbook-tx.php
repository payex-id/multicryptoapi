<?php

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
			'api-key' => $keys['NowNodes']
		]
	),
	'wss://bsc-mainnet.infura.io/ws/v3/' . $keys['Infura'],
	56
);

$txid = $argv[1] ?? "0x1f7d99a11f60134ef12a81978e144560e5b73142ccccf8cfbf3e9285f7d06b48";
$tx = $blockbook->getTx($txid);
echo "TX {$tx->txid} mined in block {$tx->blockNumber}, \n
\t{$tx->inputs[0]->address} {$tx->outputs[0]->amount} to {$tx->outputs[0]->address}\n";

foreach ($tx->outputs[0]->assets as $asset) {
	echo "\t\tAnd {$asset->type} {$asset->name} {$asset->balance} {$asset->abbr}\n";
}
