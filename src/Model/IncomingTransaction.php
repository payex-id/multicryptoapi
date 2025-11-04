<?php

namespace MultiCryptoApi\Model;

use MultiCryptoApi\Blockchain\Amount;

class IncomingTransaction
{
	public function __construct(
		readonly public string  $txid,
		readonly public string  $from,
		readonly public string  $to,
		public Amount           $amount,
		public ?int             $blockNumber = null,
		readonly public ?string $contractAddress = null,
		readonly public ?int    $index = null,
		public ?int             $confirmations = null,
		public ?bool            $isSuccess = null,
	)
	{
	}
}