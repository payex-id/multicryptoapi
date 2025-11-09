<?php

namespace Chikiday\MultiCryptoApi\Util;

use Chikiday\MultiCryptoApi\Model\EthTransferLog;
use Chikiday\MultiCryptoApi\Model\TokenInfo;
use kornrunner\Ethereum\Contract;
use Web3\Formatters\AddressFormatter;
use Web3\Utils;

class EthUtil
{
	public static function hexToDec($hex): string
	{
		if (strlen($hex) < 5) {
			return hexdec($hex);
		}

		[$remain, $last] = [substr($hex, 0, -1), substr($hex, -1)];

		return bcadd(bcmul('16', self::hexToDec($remain)), hexdec($last));
	}

	public function getTransferByLog(TokenInfo $token, array $log): ?EthTransferLog
	{
		if (strtolower($token->contract) != strtolower($log['address'])) {
			return null;
		}

		$transferLogEvent = "0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef";
		if ($log['topics'][0] != $transferLogEvent) {
			return null;
		}

		$amount = self::hexToDec(Utils::stripZero($log['data']));
		$from = AddressFormatter::format($log['topics'][1]);
		$to = AddressFormatter::format($log['topics'][2]);

		return new EthTransferLog($amount, $to, $from);
	}

	public function getTransferByInput(string $input): ?EthTransferLog
	{
		if (!str_starts_with($input, "0x" . Contract::SIGNATURE_TRANSFER)) {
			return null;
		}

		[$to, $value] = $this->parseEthInput($input);

		return new EthTransferLog($value, "0x" . $to);
	}

	private function parseEthInput(string $input): array
	{
		$to = substr($input, 34, 40);
		$valueHex = substr($input, 34 + 40, 64);
		$value = self::hexToDec($valueHex);

		return [
			$to,
			$value,
		];
	}
}