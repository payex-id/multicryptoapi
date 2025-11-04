<?php

namespace MultiCryptoApi\Api;

use MultiCryptoApi\Provider\TrxBlockbook;
use MultiCryptoApi\Blockchain\Address as BlockchainAddress;
use MultiCryptoApi\Blockchain\AddressCredentials;
use MultiCryptoApi\Blockchain\Amount;
use MultiCryptoApi\Blockchain\Fee;
use MultiCryptoApi\Blockchain\Transaction;
use MultiCryptoApi\Blockchain\TxvInOut;
use MultiCryptoApi\Exception\IncorrectTxException;
use MultiCryptoApi\Exception\NotEnoughFundsException;
use MultiCryptoApi\Contract\AddressActivator;
use MultiCryptoApi\Contract\ApiClientInterface;
use MultiCryptoApi\Contract\BlockbookInterface;
use MultiCryptoApi\Contract\ResourceRentableInterface;
use MultiCryptoApi\Contract\StreamableInterface;
use MultiCryptoApi\Stream\TronStream;
use IEXBase\TronAPI\Exception\TronException;
use InvalidArgumentException;
use Override;
use Tron\Address;
use Tron\Exceptions\TronErrorException;
use Tron\Support\Key;

class TronApiClient implements ApiClientInterface, ResourceRentableInterface, AddressActivator
{
	private TronStream $stream;

	public function __construct(
		private readonly TrxBlockbook $blockbook,
	)
	{
	}

	#[Override] public function stream(): ?StreamableInterface
	{
		if (!isset($this->stream)) {
			$this->stream = new TronStream($this->blockbook);
		}

		return $this->stream;
	}

	public function createWallet(): AddressCredentials
	{
		$generateAddress = $this->blockbook->tron->generateAddress();

		return new AddressCredentials(
			$generateAddress->getAddress(true),
			$generateAddress->getPrivateKey(),
			[
				'raw' => $generateAddress->getRawData(),
			]
		);
	}

	#[Override] public function createFromPrivateKey(string $privateKey): AddressCredentials
	{
		try {
			$addressHex = Address::ADDRESS_PREFIX . Key::privateKeyToAddress($privateKey);
			$addressBase58 = Key::getBase58CheckAddress($addressHex);
		} catch (InvalidArgumentException $e) {
			throw new TronErrorException($e->getMessage());
		}
		$address = new Address($addressBase58, $privateKey, $addressHex);
		$validAddress = $this->blockbook->tron->validateAddress($address->address);
		if (!$validAddress) {
			throw new TronErrorException('Invalid private key');
		}

		return new AddressCredentials(
			$addressBase58,
			$privateKey
		);
	}

	public function sendCoins(
		AddressCredentials $from,
		string             $addressTo,
		string             $amount,
		?Fee               $fee = null,
	): Transaction
	{
		$tron = $this->blockbook->tron;
		$tron->setPrivateKey($from->privateKey);

		// Получаем детальную информацию об аккаунте отправителя
		$accountInfo = $tron->getManager()->request('/wallet/getaccount', [
			'address' => $from->address,
			'visible' => true,
		]);

		// Проверяем доступный баланс (не замороженный)
		$availableBalance = $accountInfo['balance'] ?? 0;
		$frozenBalance = 0;

		// Учитываем замороженные средства
		if (isset($accountInfo['frozen'])) {
			foreach ($accountInfo['frozen'] as $frozen) {
				$frozenBalance += $frozen['frozen_balance'];
			}
		}

		$actualAvailableBalance = $availableBalance - $frozenBalance;

		// Проверяем ресурсы аккаунта
		$resources = $tron->getManager()->request('/wallet/getaccountresource', [
			'address' => $from->address,
			'visible' => true,
		]);

		$netLimit = ($resources['freeNetLimit'] ?? 0) + ($resources['NetLimit'] ?? 0);

		// Резервируем TRX для комиссий если недостаточно ресурсов
		$reserveForFees = 0;
		if ($netLimit < 300) { // Минимальная Bandwidth
			$reserveForFees += 1000000; // ~1 TRX в SUN
		}

		// Проверяем активность адреса получателя
		$activationFee = 0;
		try {
			$recipientInfo = $tron->getManager()->request('/wallet/getaccount', [
				'address' => $addressTo,
				'visible' => true,
			]);

			// Если аккаунт не существует или не активен, нужна активация
			if (empty($recipientInfo) || !isset($recipientInfo['address'])) {
				$activationFee = 1100000; // 1.1 TRX в SUN
			}
		} catch (TronException $e) {
			// Если не удалось получить информацию об адресе, считаем что он неактивен
			$activationFee = 1100000; // 1.1 TRX в SUN
		}

		$requiredAmount = $tron->toTron($amount) + $reserveForFees + $activationFee;

		if ($actualAvailableBalance < $requiredAmount) {
			$errorMessage = "Not enough funds. Available: " . ($actualAvailableBalance / 1000000) . " TRX, " .
				"Required: " . ($requiredAmount / 1000000) . " TRX";

			if ($activationFee > 0) {
				$errorMessage .= " (including activation fee: " . ($activationFee / 1000000) . " TRX)";
			}

			if ($reserveForFees > 0) {
				$errorMessage .= " (including reserves for fees: " . ($reserveForFees / 1000000) . " TRX)";
			}

			throw new NotEnoughFundsException($errorMessage);
		}

		try {
			$transfer = $tron->sendTransaction($addressTo, $amount, $from->address);
		} catch (TronException $e) {
			throw new IncorrectTxException($e->getMessage(), $e->getCode(), $e);
		}

		$vin = new TxvInOut($from->address, $amount, 0);
		$vout = new TxvInOut($addressTo, $amount, 0);

		$isSuccess = $transfer['result'] ?? false;
		if (!$isSuccess) {
			$error = $transfer['code'] . ": " . $tron->fromHex($transfer['message']);
		}

		return new Transaction(
			$transfer['txid'],
			0,
			0,
			time(),
			[$vin],
			[$vout],
			"0",
			$transfer,
			$isSuccess,
			$error ?? null
		);
	}

	public function sendAsset(
		AddressCredentials $from,
		string             $assetId,
		string             $addressTo,
		string             $amount,
		?int               $decimals = null,
		?Fee               $fee = null,
	): Transaction
	{
		$tron = $this->blockbook->tron;
		$tron->setPrivateKey($from->privateKey);
		$contract = $tron->contract($assetId);

		if ($fee?->gasLimit) {
			$contract->setFeeLimit((int) $fee->gasLimit->toBtc());
		}

		try {
			$transfer = $contract->transfer($addressTo, $amount, $from->address);
		} catch (TronException $e) {
			throw new IncorrectTxException($e->getMessage(), $e->getCode(), $e);
		}

		$isSuccess = (bool) ($transfer['result'] ?? false);

		$tx = new Transaction(
			$transfer['txid'],
			0,
			0,
			time(),
			[],
			[],
			$fee,
			$transfer,
			$isSuccess,
			$transfer['code'] ?? null
		);

		return $tx;
	}

	#[Override] public function blockbook(): BlockbookInterface
	{
		return $this->blockbook;
	}

	public function delegateResource(
		AddressCredentials $from,
		string             $type,
		string             $addressTo,
		int                $amount,
	): Transaction
	{
		$tron = $this->blockbook->tron;
		$tron->setPrivateKey($from->privateKey);

		try {
			$unsignedTx = $tron->getManager()->request('/wallet/delegateresource', [
				'owner_address'    => $from->address,
				'receiver_address' => $addressTo,
				'balance'          => $tron->toTron($amount),
				'resource'         => strtoupper($type),
				'lock'             => false,
				'visible'          => true,
			]);
			if (isset($unsignedTx['Error'])) {
				[, $error] = explode(' : ', $unsignedTx['Error']);
				throw new TronErrorException($error);
			}

			$signedTx = $tron->signTransaction($unsignedTx);
			$response = $tron->sendRawTransaction($signedTx);
			$signedTx = array_merge($response, $signedTx);
		} catch (TronException $e) {
			throw new IncorrectTxException($e->getMessage(), $e->getCode(), $e);
		}

		return new Transaction($signedTx['txid'], null, 0, time(), [], [], 0, $signedTx);
	}

	public function cancelDelegateResource(
		AddressCredentials $from,
		string             $type,
		string             $addressTo,
		int                $amount,
	): Transaction
	{
		$tron = $this->blockbook->tron;
		$tron->setPrivateKey($from->privateKey);

		try {
			$unsignedTx = $this->blockbook->tron->getManager()->request('/wallet/undelegateresource', [
				'owner_address'    => $from->address,
				'receiver_address' => $addressTo,
				'balance'          => $amount,
				'resource'         => strtoupper($type),
				'visible'          => true,
			]);
			if (isset($unsignedTx['Error'])) {
				[, $error] = explode(' : ', $unsignedTx['Error']);
				throw new TronErrorException($error);
			}

			$signedTx = $tron->signTransaction($unsignedTx);
			$response = $tron->sendRawTransaction($signedTx);
			$signedTx = array_merge($response, $signedTx);
		} catch (TronException $e) {
			throw new IncorrectTxException($e->getMessage(), $e->getCode(), $e);
		}

		return new Transaction($signedTx['txid'], null, 0, time(), [], [], 0, $signedTx);
	}

	public function getResourcePrices(string $address): array
	{
		$response = $this->blockbook->tron->getManager()->request("/wallet/getaccountresource", [
			'address' => $address,
			'visible' => true,
		]);

		return [
			round($response['TotalEnergyLimit'] / ($response['TotalEnergyWeight'] ?: 1), 5),
			round($response['TotalNetLimit'] / ($response['TotalNetWeight'] ?: 1), 5),
		];
	}

	public function activateAddress(
		AddressCredentials $from,
		string             $address,
	): Transaction
	{
		$tron = $this->blockbook->tron;
		$tron->setPrivateKey($from->privateKey);

		try {
			$unsignedTx = $this->blockbook->tron->getManager()->request('/wallet/createaccount', [
				'owner_address'   => $from->address,
				'account_address' => $address,
				'visible'         => true,
			]);
			if (isset($unsignedTx['Error'])) {
				[, $error] = explode(' : ', $unsignedTx['Error']);
				throw new TronErrorException($error);
			}

			$signedTx = $tron->signTransaction($unsignedTx);
			$response = $tron->sendRawTransaction($signedTx);
			$signedTx = array_merge($response, $signedTx);
		} catch (TronException $e) {
			throw new IncorrectTxException($e->getMessage(), $e->getCode(), $e);
		}

		return new Transaction($signedTx['txid'], null, 0, time(), [], [], 0, $signedTx);
	}

	public function isAddressActive(string|BlockchainAddress $address): bool
	{
		if ($address instanceof BlockchainAddress) {
			return $address->originData['details']['isActive'];
		}

		return $this->isAddressActiveByApi($address);
	}

	public function getActivationFee(): Amount
	{
		return Amount::value(1.1, $this->blockbook->getDecimals());
	}

	public function validateAddress(string $address): bool
	{
		return $this->blockbook->tron->isAddress($address);
	}

	/**
	 * Проверяет активность адреса через прямой запрос к TRON API
	 */
	private function isAddressActiveByApi(string $address): bool
	{
		try {
			$accountInfo = $this->blockbook->tron->getManager()->request('/wallet/getaccount', [
				'address' => $address,
				'visible' => true,
			]);

			// Адрес считается активным если существует в сети
			return !empty($accountInfo) && isset($accountInfo['address']);
		} catch (TronException $e) {
			// Если не удалось получить информацию, считаем адрес неактивным
			return false;
		}
	}
}