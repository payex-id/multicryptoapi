<?php

namespace MultiCryptoApi\Blockchain;

class TxOutput
{
	public function __construct(
		public readonly string $address,
		public string $value,
		public readonly int $index = 0)
	{
	}

	public function getIndex(): ?int
	{
		return $this->index;
	}

	public static function factoryMany(array $data)
	{
		$result = [];
		foreach ($data as $address => $satoshi) {
			$result[] = new self($address, $satoshi);
		}

		return $result;
	}

	public function getAddress(): string
	{
		return $this->address;
	}

	public function getValue(): string
	{
		return $this->value;
	}

	public function reduceValue(int $feeAmount): void
	{
		$this->value = bcsub($this->value, $feeAmount);
	}

	public function toTxVInOut(int $index): TxvInOut
	{
		return new TxvInOut($this->address, $this->value, $index);
	}
}