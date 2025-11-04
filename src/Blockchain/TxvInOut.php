<?php

namespace MultiCryptoApi\Blockchain;

readonly class TxvInOut
{
	public Amount $amount;
	public function __construct(
		public ?string $address,
		string|Amount $amount,
		public int $index = 0,
		/** @var Asset[] */
		public array $assets = [],
		public array $originData = [],
	) {
		if (is_string($amount)) {
			$amount = Amount::value($amount);
		}

		$this->amount = $amount;
	}
}