<?php

namespace Chikiday\MultiCryptoApi\Interface;

use Chikiday\MultiCryptoApi\Blockchain\AddressCredentials;
use Chikiday\MultiCryptoApi\Blockchain\Transaction;

interface ResourceRentableInterface
{
	public function delegateResource(
		AddressCredentials $from,
		string $type,
		string $addressTo,
		int $amount,
	): Transaction;

	public function cancelDelegateResource(
		AddressCredentials $from,
		string $type,
		string $addressTo,
		int $amount,
	): Transaction;

	public function getResourcePrices(string $address): array;

	/**
	 * Max TRX amount (in sun) the address can still delegate for the given resource type.
	 */
	public function getMaxDelegatableBalance(string $address, string $type): int;
}