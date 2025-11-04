<?php

namespace MultiCryptoApi\Api;

use BitWasp\Bitcoin\Address\AddressCreator;
use BitWasp\Bitcoin\Address\PayToPubKeyHashAddress;
use BitWasp\Bitcoin\Address\SegwitAddress;
use BitWasp\Bitcoin\Crypto\Random\Random;
use BitWasp\Bitcoin\Key\Factory\PrivateKeyFactory;
use BitWasp\Bitcoin\Network\Networks\Bitcoin;
use BitWasp\Bitcoin\Script\WitnessProgram;
use MultiCryptoApi\Api\TxBuilder\BitcoinTxBuilder;
use MultiCryptoApi\Provider\BitcoinBlockbook;
use MultiCryptoApi\Blockchain\AddressCredentials;
use MultiCryptoApi\Blockchain\Fee;
use MultiCryptoApi\Blockchain\RawTransaction;
use MultiCryptoApi\Blockchain\Transaction;
use MultiCryptoApi\Exception\IncorrectTxException;
use MultiCryptoApi\Exception\MultiCryptoApiException;
use MultiCryptoApi\Contract\ApiClientInterface;
use MultiCryptoApi\Contract\ManyInputsInterface;
use MultiCryptoApi\Contract\StreamableInterface;
use MultiCryptoApi\Stream\BitcoinStream;
use Exception;
use GuzzleHttp\Exception\ClientException;
use Override;

class BitcoinApiClient implements ApiClientInterface, ManyInputsInterface
{
	public function __construct(
		private readonly BitcoinBlockbook $blockbook,
	) {
	}

	#[\Override] public function stream(): ?StreamableInterface
	{
		return match (get_class($this->blockbook->network)) {
			Bitcoin::class => new BitcoinStream($this->blockbook),
			default => null
		};
	}

	#[\Override] public function createWallet(): AddressCredentials
	{
		$privKeyFactory = new PrivateKeyFactory();
		$privateKey = $privKeyFactory->generateCompressed(new Random());
		$publicKey = $privateKey->getPublicKey();

		if (in_array($this->blockbook->network->getSegwitBech32Prefix(), ['bc', 'tb'])) {
			$p2wpkhWP = WitnessProgram::v0($publicKey->getPubKeyHash());
			$p2wpkh = new SegwitAddress($p2wpkhWP);
			$address = $p2wpkh->getAddress($this->blockbook->network);
		} else {
			$address = new PayToPubKeyHashAddress($publicKey->getPubKeyHash());
			$address = $address->getAddress($this->blockbook->network);
		}

		return new AddressCredentials($address, $privateKey->getHex(), [
			'wif' => $privateKey->toWif($this->blockbook->network),
		]);
	}

	#[\Override] public function createFromPrivateKey(string $privateKey): AddressCredentials
	{
		$privKeyFactory = new PrivateKeyFactory();
		$privateKey = $privKeyFactory->fromHexCompressed($privateKey);
		$publicKey = $privateKey->getPublicKey();

		if (in_array($this->blockbook->network->getSegwitBech32Prefix(), ['bc', 'tb'])) {
			$p2wpkhWP = WitnessProgram::v0($publicKey->getPubKeyHash());
			$p2wpkh = new SegwitAddress($p2wpkhWP);
			$address = $p2wpkh->getAddress($this->blockbook->network);
		} else {
			$address = new PayToPubKeyHashAddress($publicKey->getPubKeyHash());
			$address = $address->getAddress($this->blockbook->network);
		}

		return new AddressCredentials($address, $privateKey->getHex(), [
			'wif' => $privateKey->toWif($this->blockbook->network),
		]);
	}

	public function sendCoins(
		AddressCredentials $from,
		string $addressTo,
		string $amount,
		?Fee $fee = null,
	): Transaction {
		$builder = new BitcoinTxBuilder($this->blockbook);
		$tx = $builder->singleOutput($from, $addressTo, $amount, $fee);

		return $this->pushTx($tx);
	}

	/**
	 * @param RawTransaction $tx
	 * @return Transaction
	 * @throws IncorrectTxException
	 * @throws MultiCryptoApiException
	 */
	public function pushTx(RawTransaction $tx): Transaction
	{
		try {
			$pushed = $this->blockbook->pushRawTransaction($tx);
		} catch (ClientException $exception) {
			$msg = $exception->getResponse()->getBody()->getContents();
			throw IncorrectTxException::factory($msg, $tx);
		} catch (Exception $e) {
			throw new MultiCryptoApiException($e->getMessage());
		}

		return $tx->toTransaction($pushed);
	}

	/**
	 * @inheritdoc
	 */
	#[Override] public function sendMany(array $from, array $to, ?Fee $fee = null): Transaction
	{
		$builder = new BitcoinTxBuilder($this->blockbook);
		$tx = $builder->manyInputOutput($from, $to, $fee);

		return $this->pushTx($tx);
	}

	public function sendAsset(
		AddressCredentials $from,
		string $assetId,
		string $addressTo,
		string $amount,
		?int $decimals = null,
		?Fee $fee = null,
	): Transaction {
		throw new Exception('Bitcoin isn\'t supports tokens');
	}

	#[Override] public function blockbook(): BitcoinBlockbook
	{
		return $this->blockbook;
	}

	public function validateAddress(string $address): bool
	{
		try {
			(new AddressCreator())->fromString($address);
			return true;
		} catch (Exception $e) {
			return false;
		}
	}
}