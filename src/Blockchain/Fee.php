<?php

namespace MultiCryptoApi\Blockchain;

class Fee
{
	private Amount $minFee;
	private bool $subtractFromAmount;

	/**
	 * @param Amount|null $fee null is equivalent to calculate by default
	 * @param Amount|null $gasLimit
	 */
	public function __construct(
		public ?Amount $fee,
		public ?Amount $gasLimit = null,
	) {
	}

	public function isSubtractFromAmount(): bool
	{
		return $this->subtractFromAmount ?? false;
	}

	public function setSubtractFromAmount(bool $subtractFromAmount): self
	{
		$this->subtractFromAmount = $subtractFromAmount;

		return $this;
	}

	public function gasLimit(): ?Amount
	{
		return $this->gasLimit ?? null;
	}

	public function getMinFee(): ?Amount
	{
		return $this->minFee ?? null;
	}

	public function setMinFee(Amount $minFee): self
	{
		$this->minFee = $minFee;

		return $this;
	}
}