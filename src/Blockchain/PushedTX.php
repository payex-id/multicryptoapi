<?php

namespace MultiCryptoApi\Blockchain;

readonly class PushedTX
{
	public function __construct(
		public string $txid,
		public string $payload,
		public mixed $originResponse,
	) {
	}
}