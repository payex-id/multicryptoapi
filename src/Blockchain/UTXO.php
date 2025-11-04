<?php

namespace MultiCryptoApi\Blockchain;

readonly class UTXO
{

	private AddressCredentials $credentials;

	public function __construct(
		public string $txid,
		public int $vout,
		public string $value,
		public int $height,
		public int $confirmations,
	) {
	}

	public function credentials(): ?AddressCredentials
	{
		return $this->credentials ?? null;
	}

	public function setCredentials(AddressCredentials $credentials): self
	{
		$this->credentials = $credentials;

		return $this;
	}

	public function toTxVInOut(int $index): TxvInOut
	{
		return new TxvInOut($this->credentials()?->address, $this->value, $index);
	}
}