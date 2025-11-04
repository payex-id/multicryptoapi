<?php

namespace MultiCryptoApi\Blockchain;

readonly class Block
{

	public function __construct(
		public int $height,
		public string $hash,
		public array $txids,
		public string $previousHash,
		public array $payload = []
	) {
	}

}