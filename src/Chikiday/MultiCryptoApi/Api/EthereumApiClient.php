<?php

namespace Chikiday\MultiCryptoApi\Api;

use Chikiday\MultiCryptoApi\Api\TxBuilder\EthereumTxBuilder;
use Chikiday\MultiCryptoApi\Blockbook\EthereumBlockbook;
use Chikiday\MultiCryptoApi\Blockchain\AddressCredentials;
use Chikiday\MultiCryptoApi\Blockchain\Fee;
use Chikiday\MultiCryptoApi\Blockchain\Transaction;
use Chikiday\MultiCryptoApi\Interface\ApiClientInterface;
use Chikiday\MultiCryptoApi\Interface\StreamableInterface;
use Chikiday\MultiCryptoApi\Stream\EthereumStream;
use kornrunner\Ethereum\Address;
use Override;
use Web3\Utils;

class EthereumApiClient implements ApiClientInterface
{

	public function __construct(
		private readonly EthereumBlockbook $blockbook,
	) {
	}

	#[Override] public function stream(): ?StreamableInterface
	{
		return new EthereumStream($this->blockbook);
	}

	public function createWallet(): AddressCredentials
	{
		$address = new Address();

		return new AddressCredentials("0x" . $address->get(), $address->getPrivateKey(), [
			'public' => $address->getPublicKey(),
		]);
	}

	#[Override] public function createFromPrivateKey(string $privateKey): AddressCredentials
	{
		$address = new Address($privateKey);

		return new AddressCredentials("0x" . $address->get(), $address->getPrivateKey(), [
			'public' => $address->getPublicKey(),
		]);
	}

	#[Override] public function blockbook(): EthereumBlockbook
	{
		return $this->blockbook;
	}

	#[Override] public function sendCoins(
		AddressCredentials $from,
		string $addressTo,
		string $amount,
		?Fee $fee = null,
	): Transaction {
		$builder = new EthereumTxBuilder($this->blockbook);
		$tx = $builder->ethTx($from, $addressTo, $amount, $fee);

		$tx = $this->blockbook->pushRawTransaction($tx);

		return new Transaction($tx->txid, null, 0, time(), [], [], 0, [$tx->payload]);
	}

	#[Override] public function sendAsset(
		AddressCredentials $from,
		string $assetId,
		string $addressTo,
		string $amount,
		?int $decimals = null,
		?Fee $fee = null,
	): Transaction {
		$builder = new EthereumTxBuilder($this->blockbook);
		$tx = $builder->assetTx($from, $addressTo, $assetId, $amount, $decimals, $fee);

		$tx = $this->blockbook->pushRawTransaction($tx);

		return new Transaction($tx->txid, null, 0, time(), [], [], 0, [$tx->payload]);
	}

	public function validateAddress(string $address): bool
	{
		return Utils::isAddress($address);
	}
}