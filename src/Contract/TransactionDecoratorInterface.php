<?php

namespace MultiCryptoApi\Contract;

use MultiCryptoApi\Blockchain\Asset;
use MultiCryptoApi\Model\Enum\TransactionDirection;

interface TransactionDecoratorInterface
{
	public function getDirection(): TransactionDirection;

	/**
	 * Get first input address
	 *
	 * @return string
	 */
	public function getFrom(): string;

	/**
	 * Get all input addresses
	 *
	 * @return array
	 */
	public function getFromAll(): array;

	/**
	 * Get first output address
	 *
	 * @return string
	 */
	public function getTo(): string;

	/**
	 * Get all output addresses
	 *
	 * @return array
	 */
	public function getToAll(): array;

	/**
	 * Get sent base coin amount
	 *
	 * @return float
	 */
	public function getTransferredValue(): float;

	/**
	 * @return Asset[]
	 */
	public function getTransferredAssets(): array;
}