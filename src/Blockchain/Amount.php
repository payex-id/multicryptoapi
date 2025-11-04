<?php

namespace MultiCryptoApi\Blockchain;

use JsonSerializable;
use Override;

class Amount implements JsonSerializable
{
	public function __construct(
		public string       $satoshi,
		public readonly int $decimals = 8,
	)
	{
	}

	public static function value(mixed $amount, int $decimals = 8): self
	{
		if (preg_match('/(.*)E-(.*)/', str_replace(".", "", $amount), $matches)) {
			$amount = sprintf("%.{$decimals}f", $amount);
		}

		return self::satoshi(bcmul($amount, 10 ** $decimals, 0), $decimals);
	}

	public static function satoshi(string $satoshi, int $decimals = 8): self
	{
//		$satoshi = number_format($satoshi, 0, ".", ""); # it leads that 238921900000000000000 become 238921900000000016384
		return new self($satoshi, $decimals);
	}

	public static function import(mixed $json): self
	{
		if (is_string($json)) {
			$json = ['satoshi' => $json, 'decimals' => 8];
		}

		return new self($json['satoshi'], $json['decimals']);
	}

	public function add(Amount $amount): Amount
	{
		$this->satoshi = bcadd($this->satoshi, $amount->satoshi, $this->decimals);

		return $amount;
	}

	public function sub(Amount $amount): Amount
	{
		$this->satoshi = bcsub($this->satoshi, $amount->satoshi, $this->decimals);

		return $this;
	}

	public function mul(float $multiply): Amount
	{
		$this->satoshi = bcmul($this->satoshi, (string) $multiply, $this->decimals);

		return $this;
	}

	public function __toString(): string
	{
		return $this->toBtc();
	}

	public function toBtc(): string
	{
		return bcdiv($this->satoshi, bcpow(10, (string) $this->decimals), $this->decimals);
	}

	#[Override] public function jsonSerialize(): mixed
	{
		return [
			"satoshi"  => $this->satoshi,
			"decimals" => $this->decimals,
		];
	}
}