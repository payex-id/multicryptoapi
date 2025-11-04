<?php

error_reporting(E_ALL ^ E_DEPRECATED);

require_once __DIR__ . "/../vendor/autoload.php";

use MultiCryptoApi\Provider\TrxBlockbook;
use MultiCryptoApi\Blockchain\RpcCredentials;
use MultiCryptoApi\Model\IncomingTransaction;
use MultiCryptoApi\Stream\TronStream;
use MultiCryptoApi\Stream\Logger\FileLogger;
use MultiCryptoApi\Stream\TrxStream;

$keys = include_once __DIR__ . '/keys.php';

$blockbook = new TrxBlockbook(
	new RpcCredentials(
		'https://trx.nownodes.io',
		'https://trx-blockbook.nownodes.io',
		[
			'api-key' => $keys['NowNodes'], // for now nodes
			'TRON-PRO-API-KEY' => $keys['TronScan'], // for tronscan
		]
	),
	$keys['TronGridApiKey']
);
$stream = new TronStream($blockbook);


$logger = new \MultiCryptoApi\Log\StdoutLogger();
$stream->setLogger($logger);

$stream->subscribeToAnyTransaction(function (IncomingTransaction $tx) {

});