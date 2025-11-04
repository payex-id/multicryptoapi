<?php

namespace MultiCryptoApi\Blockchain;

readonly class AddressCredentials
{
	public function __construct(
		public string $address,
		public string $privateKey,
		public array $payload = [],
	) {
	}

}