<?php

error_reporting(E_ALL ^ E_DEPRECATED);

require_once __DIR__ . "/../vendor/autoload.php";

use Chikiday\MultiCryptoApi\Blockbook\EthereumRpcBlockbook;
use Chikiday\MultiCryptoApi\Blockchain\RpcCredentials;

$keys = include_once __DIR__ . '/keys.php';

$blockbook = new EthereumRpcBlockbook(
	new RpcCredentials(
		'https://bsc.nownodes.io/' . $keys['NowNodes'],
		'https://bsc-blockbook.nownodes.io',
		[
			'api-key' => $keys['NowNodes'],
		]
	),
	null,
	56,
	[
		'tokens' => [
			$contractAddress = '0x55d398326f99059ff775485246999027b3197955',
		],
	]

);

//$block = $blockbook->getBlock('52069123');
//echo "Last block number: {$block->height} hash {$block->hash}, ".count($block->txids)." txs\n";

$tx = $blockbook->getTx("0xf681c1ba205f1b0e325277578e6d6d75d2af993d158b32fcbba9be5aa2505723");
if (!$tx->isSuccess) {
	echo "*** WARNING *** Transaction is not successful! \n";
}
echo "TX {$tx->txid} mined in block {$tx->blockNumber}, \n
\t{$tx->inputs[0]->address} {$tx->outputs[0]->amount} to {$tx->outputs[0]->address}\n";

foreach ($tx->outputs[0]->assets as $asset) {
	echo "\t\tAnd {$asset->type} {$asset->name} {$asset->balance} {$asset->abbr}\n";
}