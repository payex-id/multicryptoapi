<?php

error_reporting(E_ALL ^ E_DEPRECATED);

require_once __DIR__ . "/../vendor/autoload.php";

use MultiCryptoApi\Provider\TrxBlockbook;
use MultiCryptoApi\Blockchain\RpcCredentials;
use MultiCryptoApi\Model\Enum\TransactionDirection;
use MultiCryptoApi\Decorator\TrxDecorator;

$keys = include_once __DIR__ . '/keys.php';

$blockbook = new TrxBlockbook(
	new RpcCredentials(
		'https://trx.nownodes.io',
		'https://trx-blockbook.nownodes.io',
		[
			'api-key'          => $keys['NowNodes'], // for now nodes
			'TRON-PRO-API-KEY' => $keys['TronScan'], // for tronscan
		]
	)
);


$block = $blockbook->getBlock();
echo "Last block number: {$block->height} hash {$block->hash}, " . count($block->txids) . " txs\n";

$tx = $blockbook->getTx('091cf9da6ebf34f9b01efb93539f0deec20aaf6f26af570f6ed046fafcd5d9f6');
$info = $tx->getDecorator('TGhgyWc61Ek5vuRsAA9anF1bkTqbD6YtV4');
echo "TX {$tx->txid} {$tx->type} ";

if ($info->getDirection() == TransactionDirection::Incoming) {
	echo "from {$info->getFrom()} ";
} else {
	echo "to {$info->getTo()} ";
}
if ($value = $info->getTransferredValue()) {
	echo "{$info->getTransferredValue()} {$blockbook->getSymbol()}";
}

echo "\n";

foreach ($info->getTransferredAssets() as $asset) {
	echo "\t Transferred {$asset->name} {$asset->balance} {$asset->abbr}\n";
}

