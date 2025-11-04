<?php

namespace MultiCryptoApi\Contract;


use MultiCryptoApi\Blockchain\Asset;
use MultiCryptoApi\Blockchain\Block;
use MultiCryptoApi\Blockchain\Transaction;

interface BlockchainDataResolver
{
	public function resolveBlock($data): Block;

	public function resolveTx($data): Transaction;

	/**
	 * @param array $data
	 * @return Asset[]
	 */
	public function resolveAssets(array $data): array;

	public function resolveAsset($data): Asset;
}