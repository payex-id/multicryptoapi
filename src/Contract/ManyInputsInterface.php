<?php

namespace MultiCryptoApi\Contract;


use MultiCryptoApi\Blockchain\AddressCredentials;
use MultiCryptoApi\Blockchain\Fee;
use MultiCryptoApi\Blockchain\Transaction;
use MultiCryptoApi\Blockchain\TxOutput;

interface ManyInputsInterface
{

	/**
	 * @param AddressCredentials[] $from
	 * @param TxOutput[] $to
	 * @param Fee|null $fee
	 * @return Transaction
	 */
	public function sendMany(array $from, array $to, ?Fee $fee = null): Transaction;

}