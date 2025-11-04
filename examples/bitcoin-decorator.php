<?php

require_once __DIR__ . "/../vendor/autoload.php";

use BitWasp\Bitcoin\Bitcoin;
use MultiCryptoApi\Provider\BitcoinBlockbook;
use MultiCryptoApi\Blockchain\RpcCredentials;
use MultiCryptoApi\Model\Enum\TransactionDirection;
use MultiCryptoApi\Decorator\BitcoinDecorator;

$keys = include_once __DIR__ . '/keys.php';

$blockbook = new BitcoinBlockbook(
	new RpcCredentials(
		'https://btc.nownodes.io',
		'https://btcbook.nownodes.io',
		[
			'api-key' => $keys['NowNodes'],
		]
	),
	Bitcoin::getNetwork(),
);

$tx = $blockbook->getTx('7fcfc2020a2c55d50ffe9a03953a3f839bb1a70520404c661b3cc67177134d43');
$info = $tx->getDecorator('19Dq3dnqR9U5n8vP3EUHveohTsMvn5saXU');
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

