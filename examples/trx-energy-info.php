<?php

/**
 * Debug TRON energy/bandwidth data for consolidator troubleshooting.
 *
 * Usage:
 *   php examples/trx-energy-info.php TTJf8VKkcJHmKJ5tPxCH9JYUc4F5LnMfMh
 */

error_reporting(E_ALL ^ E_DEPRECATED);

require_once __DIR__ . '/../vendor/autoload.php';

use Chikiday\MultiCryptoApi\Api\TronApiClient;
use Chikiday\MultiCryptoApi\Blockbook\TrxBlockbook;
use Chikiday\MultiCryptoApi\Blockchain\RpcCredentials;

$address = $argv[1] ?? 'TTJf8VKkcJHmKJ5tPxCH9JYUc4F5LnMfMh';
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

$api = new TronApiClient($blockbook);
$addr = $blockbook->getAddress($address, false);
$details = $addr->originData['details'] ?? [];
$payload = $addr->originData['chainExtraData']['payload'] ?? null;

echo "Address: {$address}\n";
echo "Balance: {$addr->balance->toBtc()} TRX\n\n";

echo "=== Raw Blockbook chainExtraData.payload ===\n";
echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

echo "=== Normalized details (used by consolidator) ===\n";
echo json_encode($details, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

$availableEnergy = ($details['energyTotal'] ?? 0) - ($details['energyUsed'] ?? 0);
$availableBandwidth = ($details['bandwidthTotal'] ?? 0) - ($details['bandwidthUsed'] ?? 0);

echo "Available energy: {$availableEnergy}\n";
echo "Available bandwidth: {$availableBandwidth}\n";
echo 'isActive: ' . json_encode($details['isActive'] ?? null) . "\n\n";

$prices = $api->getResourcePrices($address);
echo "Resource price (energy per TRX): {$prices[0]}\n";
echo "Resource price (bandwidth per TRX): {$prices[1]}\n\n";

$requiredEnergy = 66000;
$lendTrx = (int) ceil(max(0, $requiredEnergy - $availableEnergy) / $prices[0]);

echo "=== Consolidator simulation (1 USDT transfer) ===\n";
echo "Required energy: {$requiredEnergy}\n";
echo "Would lend energy (TRX): {$lendTrx}\n";

if ($availableEnergy >= $requiredEnergy) {
	echo "Status: OK, enough energy already available\n";
} else {
	echo "Status: NEED LEND (this is what consolidator would try)\n";
}
