<?php

/**
 * TRX Blockbook compatibility healthcheck for PayEx-critical paths.
 *
 * Usage:
 *   php examples/trx-blockbook-healthcheck.php <address> [creditor_address]
 *
 * Examples:
 *   php examples/trx-blockbook-healthcheck.php TTJf8VKkcJHmKJ5tPxCH9JYUc4F5LnMfMh
 *   php examples/trx-blockbook-healthcheck.php TTJf8VKkcJHmKJ5tPxCH9JYUc4F5LnMfMh TPbBbfxwJrNGhMMx7REusV5xjgXvvSwrrX
 */

error_reporting(E_ALL ^ E_DEPRECATED);

require_once __DIR__ . '/../vendor/autoload.php';

use Chikiday\MultiCryptoApi\Blockbook\TrxBlockbook;
use Chikiday\MultiCryptoApi\Blockchain\RpcCredentials;
use Chikiday\MultiCryptoApi\Blockchain\Transaction;

$address = $argv[1] ?? 'TTJf8VKkcJHmKJ5tPxCH9JYUc4F5LnMfMh';
$creditor = $argv[2] ?? null;
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

$checks = [];
$failures = 0;
$warnings = 0;

function report(string $name, bool $ok, string $message, bool $warning = false): void
{
	global $checks, $failures, $warnings;
	$status = $ok ? 'OK' : ($warning ? 'WARN' : 'FAIL');
	if (!$ok) {
		$warning ? $warnings++ : $failures++;
	}
	$checks[] = compact('name', 'status', 'message');
	printf("[%s] %s: %s\n", $status, $name, $message);
}

echo "TRX Blockbook healthcheck\n";
echo "Address: {$address}\n";
if ($creditor) {
	echo "Creditor: {$creditor}\n";
}
echo str_repeat('-', 72) . "\n";

// 1. Address energy normalization (consolidator giveResources)
$addr = $blockbook->getAddress($address, true);
$details = $addr->originData['details'] ?? [];
$hasPayload = isset($addr->originData['chainExtraData']['payload']);
$availableEnergy = ($details['energyTotal'] ?? 0) - ($details['energyUsed'] ?? 0);
report(
	'address.energy_normalized',
	$hasPayload ? isset($details['energyTotal'], $details['energyUsed']) : true,
	$hasPayload
		? "availableEnergy={$availableEnergy}, total={$details['energyTotal']}, used={$details['energyUsed']}"
		: 'legacy details format (no chainExtraData)'
);
report(
	'address.is_active',
	($details['isActive'] ?? null) !== null,
	'value=' . json_encode($details['isActive'] ?? null),
	($details['isActive'] ?? null) === null
);

// 2. Assets via Tronscan (consolidator getMoveableAssets input)
$usdt = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';
$usdtAsset = null;
foreach ($addr->assets as $asset) {
	if (strcasecmp((string) $asset->tokenId, $usdt) === 0) {
		$usdtAsset = $asset;
		break;
	}
}
report(
	'address.usdt_balance',
	$usdtAsset !== null,
	$usdtAsset
		? "USDT balance={$usdtAsset->balance->toBtc()}"
		: 'USDT not found in Tronscan assets list'
);

// 3. Tx parsing on recent address history
$txs = $blockbook->getAddressTransactions($address, 1, 100);
$stats = [
	'total'              => count($txs->transactions),
	'type_mismatch'      => 0,
	'missing_delegate'   => 0,
	'missing_undelegate' => 0,
	'usdt_ok'            => 0,
	'usdt_bad'           => 0,
	'success_bad'        => 0,
	'delegate_detected'  => 0,
	'undelegate_detected'=> 0,
];

foreach ($txs->transactions as $tx) {
	$payload = $tx->originData['chainExtraData']['payload'] ?? [];
	$payloadType = $payload['contractType'] ?? null;

	if ($payloadType !== null && $tx->type !== $payloadType) {
		$stats['type_mismatch']++;
	}

	if ($payloadType === 'DelegateResourceContract') {
		$stats['delegate_detected']++;
		if ($tx->type !== 'DelegateResourceContract') {
			$stats['type_mismatch']++;
		}
		if (!isset($tx->originData['delegateInfo']['delegateTo'], $tx->originData['delegateInfo']['amount'])) {
			$stats['missing_delegate']++;
		}
	}

	if ($payloadType === 'UnDelegateResourceContract') {
		$stats['undelegate_detected']++;
		if ($tx->type !== 'UnDelegateResourceContract') {
			$stats['type_mismatch']++;
		}
		if (!isset($tx->originData['undelegateInfo']['prevDelegate'], $tx->originData['undelegateInfo']['amount'])) {
			$stats['missing_undelegate']++;
		}
	}

	foreach ($tx->originData['tokenTransfers'] ?? [] as $transfer) {
		if (strcasecmp((string) ($transfer['contract'] ?? ''), $usdt) !== 0) {
			continue;
		}
		$parsedOk = $tx->inputs[0]->address !== ''
			&& $tx->outputs[0]->address !== ''
			&& count($tx->outputs[0]->assets) > 0;
		$parsedOk ? $stats['usdt_ok']++ : $stats['usdt_bad']++;
	}

	if (($payload['result'] ?? null) === 'SUCCESS' && !$tx->isSuccess) {
		$stats['success_bad']++;
	}
}

report(
	'tx.contract_type',
	$stats['type_mismatch'] === 0,
	"type mismatches={$stats['type_mismatch']} / {$stats['total']} (LendingList, TransactionDecorator)"
);
report(
	'tx.delegate_info',
	$stats['missing_delegate'] === 0,
	"missing delegateInfo={$stats['missing_delegate']} / delegate txs={$stats['delegate_detected']} (Energy UI, cancel lend)"
);
report(
	'tx.undelegate_info',
	$stats['missing_undelegate'] === 0,
	"missing undelegateInfo={$stats['missing_undelegate']} / undelegate txs={$stats['undelegate_detected']}"
);
report(
	'tx.usdt_parsing',
	$stats['usdt_bad'] === 0,
	"ok={$stats['usdt_ok']}, bad={$stats['usdt_bad']} (invoice detection, consolidator)"
);
report(
	'tx.is_success',
	$stats['success_bad'] === 0,
	"false negatives on SUCCESS payload={$stats['success_bad']} (TxStatusChecker, pending txs)"
);

// 4. Reclaim path (consolidator reclaimLentResources)
if ($creditor) {
	$creditorAddr = $blockbook->getAddress($creditor);
	$frozen = $creditorAddr->originData['details']['frozenListV2'] ?? [];
	$forInvoice = array_values(array_filter(
		$frozen,
		fn(array $item) => ($item['delegateTo'] ?? '') === $address
	));
	report(
		'reclaim.frozen_list',
		$forInvoice !== [],
		count($forInvoice) . ' active delegation(s) to invoice: ' . json_encode($forInvoice, JSON_UNESCAPED_UNICODE)
	);
} else {
	report(
		'reclaim.frozen_list',
		true,
		'skipped (pass creditor address as 2nd arg)',
		true
	);
}

// 5. Sample tx debug
$sampleTx = null;
foreach ($txs->transactions as $tx) {
	$payloadType = $tx->originData['chainExtraData']['payload']['contractType'] ?? null;
	if ($payloadType === 'DelegateResourceContract' || $payloadType === 'TriggerSmartContract') {
		$sampleTx = $tx;
		break;
	}
}
if ($sampleTx) {
	echo "\nSample tx debug ({$sampleTx->txid}):\n";
	echo '  type=' . $sampleTx->type . "\n";
	echo '  isSuccess=' . ($sampleTx->isSuccess ? 'yes' : 'no') . "\n";
	echo '  success=' . json_encode(Transaction::explainIsSuccess($sampleTx->originData), JSON_UNESCAPED_UNICODE) . "\n";
	echo '  delegateInfo=' . json_encode($sampleTx->originData['delegateInfo'] ?? null) . "\n";
}

echo str_repeat('-', 72) . "\n";
echo "Summary: {$failures} failure(s), {$warnings} warning(s)\n";
exit($failures > 0 ? 1 : 0);
