<?php

namespace MultiCryptoApi\Contract;


use MultiCryptoApi\Blockchain\Address;
use MultiCryptoApi\Blockchain\AddressCredentials;
use MultiCryptoApi\Blockchain\Amount;
use MultiCryptoApi\Blockchain\Transaction;

/**
 * Interface for networks that require an address to be activated (like Tron)
 */
interface AddressActivator
{
	public function activateAddress(
		AddressCredentials $from,
		string             $address,
	): Transaction;

	public function isAddressActive(Address|string $address): bool;

	public function getActivationFee(): Amount;
}