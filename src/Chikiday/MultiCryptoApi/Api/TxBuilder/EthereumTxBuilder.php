<?php

namespace Chikiday\MultiCryptoApi\Api\TxBuilder;

use Chikiday\MultiCryptoApi\Blockbook\EthereumBlockbook;
use Chikiday\MultiCryptoApi\Blockchain\AddressCredentials;
use Chikiday\MultiCryptoApi\Blockchain\Amount;
use Chikiday\MultiCryptoApi\Blockchain\Fee;
use Chikiday\MultiCryptoApi\Blockchain\RawTransaction;
use kornrunner\Ethereum\EIP1559Transaction;
use kornrunner\Ethereum\Token;
use kornrunner\Ethereum\Transaction;

readonly class EthereumTxBuilder
{
	public function __construct(
		private EthereumBlockbook $blockbook,
	) {
	}

	public function ethTx(
		AddressCredentials $from,
		string $to,
		string $amount,
		?Fee $fee = null,
	): RawTransaction {
		$gasPrice = $fee?->fee?->satoshi ?? $this->blockbook->getGasPrice();
		$cnt = $this->blockbook->getTransactionCount($from->address);

		$gasLimit = $fee?->gasLimit()?->satoshi ?? 21000;
		$feeAmount = bcmul($gasPrice, $gasLimit);
		$amount = Amount::value($amount, 18)->satoshi;

		if ($fee?->isSubtractFromAmount() ?? true) {
			$amount = bcsub($amount, $feeAmount);
		}

		$transaction = new EIP1559Transaction(
			dechex($cnt),
			dechex($gasPrice),
			dechex($gasPrice),
			dechex($gasLimit),
			"0x" . $to,
			'0x' . self::bcdechex($amount)
		);

		$hex = $transaction->getRaw($from->privateKey, $this->blockbook->chainId);
		return new RawTransaction("0x" . $hex, '', [], [], $feeAmount);
	}

	public function assetTx(
		AddressCredentials $from,
		string $to,
		string $assetId,
		string $amount,
		int $decimals = 18,
		?Fee $fee = null,
	): RawTransaction {
		$gasPrice = $fee?->fee?->satoshi ?? $this->blockbook->getGasPrice();
		$cnt = $this->blockbook->getTransactionCount($from->address);

		$gasLimit = $fee?->gasLimit()?->satoshi ?? 1000 * 1000;

		$token = new Token();

		$amount = Amount::value($amount, $decimals)->satoshi;
		$amount = '0x' . self::bcdechex($amount);
		$data = $token->getTransferData($to, $amount);

		if (!str_starts_with($assetId, '0x')) {
			$assetId = "0x{$assetId}";
		}

		$transaction = new EIP1559Transaction(
			dechex($cnt),
			dechex($gasPrice),
			dechex($gasPrice),
			dechex($gasLimit),
			$assetId,
			'',
			$data
		);

		$hex = $transaction->getRaw($from->privateKey, $this->blockbook->chainId);
		return new RawTransaction("0x" . $hex, '', [], [], bcmul($gasPrice, $gasLimit));
	}

	public static function bchexdec(string $hex): string
	{
		return base_convert($hex, 16, 10);
	}

	public static function bcdechex(string $dec): string
	{
		$end = bcmod($dec, '16');
		$remainder = bcdiv(bcsub($dec, $end), '16');
		return $remainder == 0 ? dechex((int)$end) : static::bcdechex($remainder) . dechex((int)$end);
	}

}