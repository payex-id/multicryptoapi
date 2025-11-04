<?php

namespace MultiCryptoApi\Model;

class EthTransferLog
{
	public function __construct(
		public string $value,
		public string $to,
		public ?string $from = null,
	)
	{

	}
}