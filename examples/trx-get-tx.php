<?php

error_reporting(E_ALL ^ E_DEPRECATED);

require_once __DIR__ . "/../vendor/autoload.php";

use Chikiday\MultiCryptoApi\Blockbook\TrxBlockbook;
use Chikiday\MultiCryptoApi\Blockchain\RpcCredentials;
use Chikiday\MultiCryptoApi\Blockchain\Transaction;

$keys = include_once __DIR__ . '/keys.php';

$txid = $argv[1] ?? '<txid>';

$blockbook = new TrxBlockbook(
	new RpcCredentials(
		'https://trx.nownodes.io',
		'https://trx-blockbook.nownodes.io',
		[
			'api-key'          => $keys['NowNodes'],
			'TRON-PRO-API-KEY' => $keys['TronScan'],
		]
	),
);

$tx = $blockbook->getTx($txid);
if (!$tx instanceof Transaction) {
	echo "Transaction {$txid} not found\n";
	exit(1);
}

echo "TX {$tx->txid}\n";
echo "  block: {$tx->blockNumber}, confirmations: {$tx->confirmations}\n";
echo "  isSuccess: " . ($tx->isSuccess ? 'yes' : 'no') . "\n";
echo "  success debug: " . json_encode(Transaction::explainIsSuccess($tx->originData), JSON_UNESCAPED_UNICODE) . "\n";
echo "  from: {$tx->inputs[0]->address}\n";
echo "  to: {$tx->outputs[0]->address}\n";
echo "  amount: {$tx->outputs[0]->amount}\n";

foreach ($tx->outputs[0]->assets as $asset) {
	echo "  asset: {$asset->name} ({$asset->abbr}) {$asset->balance}\n";
}
