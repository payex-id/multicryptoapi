<?php

namespace Chikiday\MultiCryptoApi\Interface;


use Chikiday\MultiCryptoApi\Blockchain\Address;

interface UnconfirmedBalanceFeatureInterface
{
	/**
	 * @param string $address
	 * @param bool $withAssets
	 * @return Address
	 */
	public function getUnconfirmedBalance(string $address, bool $withAssets = false): Address;
}