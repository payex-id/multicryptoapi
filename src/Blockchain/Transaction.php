<?php

namespace MultiCryptoApi\Blockchain;

use MultiCryptoApi\Contract\TransactionDecoratorInterface;
use MultiCryptoApi\Model\IncomingTransaction;
use MultiCryptoApi\Model\TransactionDecorator;

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
		$this->isSuccess = $isSuccess ?? (
			$this->originData['tronTXReceipt']['status'] ??
			$this->originData['ethereumSpecific']['status'] ??
			true
		) > 0;
		$this->type = $this->originData['contract_name'] ?? 'tx';
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