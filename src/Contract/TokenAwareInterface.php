<?php

namespace MultiCryptoApi\Contract;

use MultiCryptoApi\Blockchain\Amount;
use MultiCryptoApi\Model\TokenInfo;

interface TokenAwareInterface
{
	public function getTokenInfo(string $address): ?TokenInfo;

	public function getErc20Balance(string $contractAddress, string $holderAddress): ?Amount;

	public function setCacheDir(string $path): self;
}