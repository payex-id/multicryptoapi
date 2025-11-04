<?php

namespace MultiCryptoApi\Blockchain;

readonly class Address
{
	public function __construct(
		public string $address,
		public Amount $balance,
		/** @var Asset[] */
		public array $assets = [],
		public array $originData = [],
	) {
	}
}