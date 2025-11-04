<?php

namespace MultiCryptoApi\Contract;


use MultiCryptoApi\Blockchain\Address;
use MultiCryptoApi\Blockchain\Asset;
use MultiCryptoApi\Blockchain\Block;
use MultiCryptoApi\Blockchain\PushedTX;
use MultiCryptoApi\Blockchain\RawTransaction;
use MultiCryptoApi\Blockchain\Transaction;
use MultiCryptoApi\Blockchain\TransactionList;
use MultiCryptoApi\Blockchain\UTXO;

interface BlockbookInterface
{
	public function getOption(string $key): mixed;

	public function getDecimals(): int;

	public function getName(): string;

	public function getSymbol(): string;

	public function getBlock(string $hash = 'latest'): Block;

	public function getTx(string $txId): ?Transaction;

	public function getAddress(string $address): Address;

	public function getAddressTransactions(string $address, int $page = 1, int $pageSize = 1000): TransactionList;

	/**
	 * @param string $address
	 * @return Asset[]
	 */
	public function getAssets(string $address): array;

	public function pushRawTransaction(RawTransaction $hex): PushedTX;

	/**
	 * @param string $address
	 * @param bool $confirmed
	 * @return UTXO[]
	 */
	public function getUTXO(string $address, bool $confirmed = true): array;
}