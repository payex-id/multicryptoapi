<?php

namespace MultiCryptoApi\Blockchain;

readonly class RawTransaction
{
	public function __construct(
		public string $payload,
		public string $txid,

		/** @var UTXO[] */
		public array $inputs,
		/** @var TxOutput[] */
		public array $outputs,
		public string $fee,
	) {
	}

	public function toTransaction(PushedTX $pushedTX): Transaction
	{
		$vin = array_map(fn($i, $n) => $i->toTxVInOut($n), $this->inputs, array_keys(array_values($this->inputs)));
		$vout = array_map(fn($i, $n) => $i->toTxVInOut($n), $this->outputs, array_keys($this->outputs));

		return new Transaction($pushedTX->txid, null, 0, time(), $vin, $vout, $this->fee, [
			'payload' => $this->payload
		]);
	}
}