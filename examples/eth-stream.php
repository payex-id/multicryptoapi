<?php

error_reporting(E_ALL ^ E_DEPRECATED);

require_once __DIR__ . "/../vendor/autoload.php";

use MultiCryptoApi\Provider\EthereumBlockbook;
use MultiCryptoApi\Blockchain\RpcCredentials;
use MultiCryptoApi\Model\IncomingBlock;
use MultiCryptoApi\Model\IncomingTransaction;
use MultiCryptoApi\Stream\EthereumStream;

$keys = include_once __DIR__ . '/keys.php';

$blockbook = new EthereumBlockbook(
	new RpcCredentials(
		'https://eth.nownodes.io/' . $keys['NowNodes'],
		'https://eth-blockbook.nownodes.io',
		[
			'api-key' => $keys['NowNodes'],
		]
	),
//	'wss://mainnet.infura.io/ws/v3/' . $keys['Infura']
);

$blockbook->debug = false;

class StringCounter
{
	private array $counts = [];

	public function percent(string $input): float
	{
		$this->counts[$input] ??= 0;
		$this->counts[$input]++;

		return ($this->counts[$input] / array_sum($this->counts)) * 100;
	}
}

$counter = new StringCounter();

$eth = new EthereumStream($blockbook);
$eth->setStaleTimeout(20);
$eth->debug = true;

$time = time();
$blocks = 0;

$eth->subscribeToAnyBlock(function (IncomingBlock $block) use(&$blocks, $time) {
	$blocks ++ ;
	$_time = time() - $time;
	$mem = round(memory_get_usage() / 1024 / 1024, 2);
	echo "New block {$block->blockNumber} mined, " . count($block->txs) . " " .
		"txs [{$blocks} blocks | {$_time} s. | {$mem} mb.]\n";
});

//$i = 0;
//$eth->subscribeToAnyTransaction(function (IncomingTransaction $tx, bool $fromBlock = false) use (&$i, $counter) {
//	$i++;
//	$percent = round($counter->percent($tx->to), 5);
//	echo "Tx {$tx->txid}:{$tx->index} {$tx->from} -> {$tx->to} {$tx->amount} {$tx->contractAddress} [{$percent}]";
//	echo($fromBlock ? " [FROM BLOCK]" : "");
//	echo "\n";
//});
//

$eth->subscribeToAddresses(
	['0x6cc5f688a315f3dc28a7781717a9a798a59fda7b'],
	function (IncomingTransaction $tx) use (&$i) {
		echo "\n\n\n======\n\n\n";
		echo "\t\tNEW TRANSACTION [$tx->txid] : {$tx->index}\n";
		echo "\t\tTO: {$tx->to}\n";
		echo "\t\tFROM: {$tx->from}\n";
		echo "\t\tAMOUNT: {$tx->amount}\n";
		echo "\t\tCONTRACT: {$tx->contractAddress}\n";
		echo "\n\n\n======\n\n\n";
	}
);