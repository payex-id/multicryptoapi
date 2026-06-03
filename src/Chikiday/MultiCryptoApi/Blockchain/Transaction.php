<?php

namespace Chikiday\MultiCryptoApi\Blockchain;

use Chikiday\MultiCryptoApi\Interface\TransactionDecoratorInterface;
use Chikiday\MultiCryptoApi\Model\IncomingTransaction;
use Chikiday\MultiCryptoApi\Model\TransactionDecorator;

readonly class Transaction
{
	public bool $isSuccess;
	public string $type;

	public function __construct(
		public string  $txid,
		public ?int    $blockNumber,
		public int     $confirmations,
		public int     $time,
		/** @var TxvInOut[] */
		public array   $inputs = [],
		/** @var TxvInOut[] */
		public array   $outputs = [],
		public string|Fee $fee = "0",
		public array   $originData = [],
		?bool          $isSuccess = null,
		public ?string $error = null,
	)
	{
		$this->isSuccess = self::resolveIsSuccess($this->originData, $isSuccess);
		$this->type = $this->originData['contract_name'] ?? 'tx';
	}

	/**
	 * Resolves on-chain success from Blockbook payload without cross-chain fallbacks.
	 *
	 * TRX txs often omit tronTXReceipt; falling through to ethereumSpecific.status (=0) wrongly marks them failed.
	 */
	public static function resolveIsSuccess(array $originData, ?bool $isSuccess = null): bool
	{
		if ($isSuccess !== null) {
			return $isSuccess;
		}

		if (array_key_exists('tronTXReceipt', $originData)) {
			$status = $originData['tronTXReceipt']['status'] ?? null;

			return $status === null || (int) $status > 0;
		}

		$chainResult = $originData['chainExtraData']['payload']['result'] ?? null;
		if ($chainResult !== null) {
			return strtoupper((string) $chainResult) === 'SUCCESS';
		}

		if (array_key_exists('ethereumSpecific', $originData)) {
			$status = $originData['ethereumSpecific']['status'] ?? null;

			return $status === null || (int) $status > 0;
		}

		return true;
	}

	/**
	 * Debug context for logs when isSuccess is false (which Blockbook fields were used).
	 *
	 * @return array<string, mixed>
	 */
	public static function explainIsSuccess(array $originData, ?bool $isSuccess = null): array
	{
		if ($isSuccess !== null) {
			return [
				'source'     => 'explicit',
				'is_success' => $isSuccess,
			];
		}

		if (array_key_exists('tronTXReceipt', $originData)) {
			$status = $originData['tronTXReceipt']['status'] ?? null;

			return [
				'source'              => 'tronTXReceipt',
				'is_success'          => $status === null || (int) $status > 0,
				'tron_receipt_status' => $status,
				'tron_tx_receipt'     => $originData['tronTXReceipt'],
			];
		}

		$chainResult = $originData['chainExtraData']['payload']['result'] ?? null;
		if ($chainResult !== null) {
			return [
				'source'              => 'chainExtraData.result',
				'is_success'          => strtoupper((string) $chainResult) === 'SUCCESS',
				'chain_extra_result'  => $chainResult,
				'chain_extra_payload' => $originData['chainExtraData']['payload'] ?? null,
			];
		}

		if (array_key_exists('ethereumSpecific', $originData)) {
			$status = $originData['ethereumSpecific']['status'] ?? null;

			return [
				'source'                     => 'ethereumSpecific',
				'is_success'                 => $status === null || (int) $status > 0,
				'ethereum_specific_status'   => $status,
				'ethereum_specific'          => $originData['ethereumSpecific'],
			];
		}

		return [
			'source'     => 'default_true',
			'is_success' => true,
		];
	}

	public function getDecorator(string $address): TransactionDecoratorInterface
	{
		return new TransactionDecorator($address, $this);
	}

	/**
	 * @return IncomingTransaction[]
	 */
	public function getRelatedTransactions(string $address): array
	{
		/** @var Asset[] $assets */
		$assets = [];
		$from = $this->inputs[0]->address;
		$_addr = strtolower($address);
		foreach ($this->outputs as $output) {
			if (strtolower($output->address) == $_addr && $output->amount->satoshi > 0) {
				$result[] = new IncomingTransaction(
					$this->txid,
					$from,
					$address,
					$output->amount,
					$this->blockNumber,
					null,
					$output->index,
					$this->confirmations,
					$this->isSuccess
				);
			}

			foreach ($output->assets as $asset) {
				if (strtolower($asset->getTo()) != $_addr && strtolower($asset->getFrom()) != $_addr) {
					continue;
				}

				$assets[$asset->tokenId] = $asset->withAmount(
					isset($assets[$asset->tokenId])
						? $assets[$asset->tokenId]->balance->add($asset->balance)
						: $asset->balance
				);
			}
		}

		foreach ($assets as $asset) {
			$result[] = new IncomingTransaction(
				$this->txid,
				$asset->getFrom(),
				$asset->getTo(),
				$asset->balance,
				$this->blockNumber,
				$asset->tokenId,
				0,
				$this->confirmations,
				$this->isSuccess
			);
		}

		return $result ?? [];
	}
}