<?php

/**
 * Debug TRON energy reclaim path used by Consolidator::reclaimLentResources.
 *
 * Usage:
 *   php examples/trx-reclaim-info.php <creditor_address> <invoice_address>
 *
 * Example:
 *   php examples/trx-reclaim-info.php TPbBbfxwJrNGhMMx7REusV5xjgXvvSwrrX TTJf8VKkcJHmKJ5tPxCH9JYUc4F5LnMfMh
 */

error_reporting(E_ALL ^ E_DEPRECATED);

require_once __DIR__ . '/../vendor/autoload.php';

use Chikiday\MultiCryptoApi\Blockbook\TrxBlockbook;
use Chikiday\MultiCryptoApi\Blockchain\RpcCredentials;

$creditor = $argv[1] ?? null;
$invoice = $argv[2] ?? 'TTJf8VKkcJHmKJ5tPxCH9JYUc4F5LnMfMh';
$keys = include_once __DIR__ . '/keys.php';

$blockbook = new TrxBlockbook(
	new RpcCredentials(
		'https://trx.nownodes.io',
		'https://trx-blockbook.nownodes.io',
		[
			'api-key'          => $keys['NowNodes'],
			'TRON-PRO-API-KEY' => $keys['TronScan'],
		]
	),
	$keys['TronGridApiKey'] ?? '',
);

if (!$creditor) {
	$txs = $blockbook->getAddressTransactions($invoice, 1, 5);
	$creditor = $txs->transactions[0]->inputs[0]->address ?? null;
	echo "Creditor auto-detected from latest delegate tx: {$creditor}\n\n";
}

if (!$creditor) {
	echo "Usage: php examples/trx-reclaim-info.php <creditor_address> <invoice_address>\n";
	exit(1);
}

echo "=== Creditor {$creditor} ===\n";
$creditorAddr = $blockbook->getAddress($creditor);
$details = $creditorAddr->originData['details'] ?? [];
$payload = $creditorAddr->originData['chainExtraData']['payload'] ?? null;

echo "stakingInfo.delegatedBalanceEnergy: " . ($payload['stakingInfo']['delegatedBalanceEnergy'] ?? 'n/a') . "\n";
echo "stakingInfo.delegatedBalanceBandwidth: " . ($payload['stakingInfo']['delegatedBalanceBandwidth'] ?? 'n/a') . "\n\n";

echo "frozenListV2 entries: " . count($details['frozenListV2'] ?? []) . "\n";
echo json_encode($details['frozenListV2'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

$forInvoice = array_values(array_filter(
	$details['frozenListV2'] ?? [],
	fn(array $item) => ($item['delegateTo'] ?? '') === $invoice
));

echo "=== Reclaim candidates for invoice {$invoice} ===\n";
if ($forInvoice === []) {
	echo "NONE — consolidator will NOT call cancelDelegateResource\n";
	exit(1);
}

foreach ($forInvoice as $item) {
	$trx = round($item['amount'] / 1_000_000, 2);
	echo "- {$item['type']}: {$item['amount']} sun (~{$trx} TRX) -> {$item['delegateTo']}\n";
}

echo "\nConsolidator would issue " . count($forInvoice) . " reclaim tx(s).\n";
