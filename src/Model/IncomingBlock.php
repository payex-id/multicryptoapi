<?php

namespace MultiCryptoApi\Model;

class IncomingBlock
{
	public function __construct(
		readonly public int $blockNumber,
		readonly public string $hash,
		readonly public string $previousHash,
		public array $txs,
	) {
	}
}