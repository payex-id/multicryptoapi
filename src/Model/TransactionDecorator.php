<?php

namespace MultiCryptoApi\Model;

use MultiCryptoApi\Blockchain\Amount;
use MultiCryptoApi\Blockchain\Asset;
use MultiCryptoApi\Blockchain\Transaction;
use MultiCryptoApi\Contract\TransactionDecoratorInterface;
use MultiCryptoApi\Model\Enum\TransactionDirection;
use Override;

class TransactionDecorator implements TransactionDecoratorInterface
{
	private TransactionDirection $direction;
	private float $transferred = 0;
	private ?string $to;
	private ?string $from;
	private array $toAll = [];
	private array $fromAll = [];
	private array $transferredAssets = [];
	private array $_assets;

	public function __construct(public readonly string $address, public readonly Transaction $tx)
	{
		foreach ($tx->inputs as $input) {
			$this->fromAll[$input->address] = $input->address;
			if ($this->isSameAddr($input->address)) {
				$this->direction = TransactionDirection::Outgoing;

				if ($input->amount->satoshi > 0) {
					$this->transferred -= (float)$input->amount->toBtc();
				}
			} else {
				$this->from ??= $input->address;
			}
		}

		foreach ($tx->outputs as $output) {
			$this->toAll[$output->address] = $output->address;

			if ($this->isSameAddr($output->address)) {
				$this->direction ??= TransactionDirection::Incoming;
				if ($output->amount->satoshi > 0) {
					$this->transferred += (float)$output->amount->toBtc();
				}
			} else {
				$this->to ??= $output->address;
			}

			foreach ($output->assets as $asset) {
				$this->toAll[$asset->getTo()] = $asset->getTo();
				$this->fromAll[$asset->getFrom()] = $asset->getFrom();

				$this->to ??= $asset->getTo();
				$this->from ??= $asset->getFrom();

				$this->_assets[] = $asset;
				$this->transferredAssets[$asset->tokenId] ??= 0;

				if ($this->isSameAddr($asset->getTo())) {
					$this->direction ??= TransactionDirection::Incoming;
					$this->transferredAssets[$asset->tokenId] += (float)$asset->balance->toBtc();
				}

				if ($this->isSameAddr($asset->getFrom())) {
					$this->direction ??= TransactionDirection::Outgoing;
					$this->transferredAssets[$asset->tokenId] -= (float)$asset->balance->toBtc();
				}
			}
		}

		$this->makeTransferredAssetsAsObjects();

		// tron related
		if ($this->tx->type == 'WithdrawBalanceContract') {
			$this->to = $this->tx->originData['internalTXs']['0']['to'];
			$this->transferred = Amount::satoshi($this->tx->originData['internalTXs']['0']['value'], 6)->toBtc();
		}

		if ($this->tx->type == 'UnfreezeBalanceV2Contract') {
			$this->to = $this->tx->originData['fromAddress'];
			$this->transferred = Amount::satoshi($this->tx->originData['value'], 6)->toBtc();
		}

		if ($this->tx->type == 'DelegateResourceContract') {
			$this->transferred = Amount::satoshi($this->tx->originData['delegateInfo']['amount'], 6)->toBtc();
		}

		if ($this->tx->type == 'UnDelegateResourceContract') {
			$this->transferred = Amount::satoshi($this->tx->originData['undelegateInfo']['amount'], 6)->toBtc();
		}
	}

	private function isSameAddr(string $address): bool
	{
		return strtolower($this->address) === strtolower($address);
	}

	#[Override] public function getTo(): string
	{
		return $this->to;
	}

	#[Override] public function getFrom(): string
	{
		return $this->from;
	}

	private function makeTransferredAssetsAsObjects(): void
	{
		// Изменим массив с ассетами в список с объектами типа Asset
		foreach ($this->transferredAssets as $id => $value) {
			/** @var Asset $asset */
			$asset = array_values(array_filter($this->_assets, fn(Asset $_asset) => strtolower($_asset->tokenId) == strtolower($id)))[0];
			$this->transferredAssets[$id] = $asset->withAmount(Amount::value($value, $asset->balance->decimals));
		}
	}

	#[Override] public function getDirection(): TransactionDirection
	{
		return $this->direction;
	}

	#[Override] public function getFromAll(): array
	{
		return $this->fromAll;
	}

	#[Override] public function getToAll(): array
	{
		return $this->toAll;
	}

	#[Override] public function getTransferredValue(): float
	{
		return $this->transferred;
	}

	#[Override] public function getTransferredAssets(): array
	{
		return $this->transferredAssets;
	}

}