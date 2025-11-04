<?php

namespace MultiCryptoApi\Provider\Abstract;


use MultiCryptoApi\Blockchain\Address;
use MultiCryptoApi\Blockchain\Amount;
use MultiCryptoApi\Blockchain\Asset;
use MultiCryptoApi\Blockchain\Block;
use MultiCryptoApi\Blockchain\PushedTX;
use MultiCryptoApi\Blockchain\RawTransaction;
use MultiCryptoApi\Blockchain\RpcCredentials;
use MultiCryptoApi\Blockchain\Transaction;
use MultiCryptoApi\Blockchain\TransactionList;
use MultiCryptoApi\Blockchain\TxvInOut;
use MultiCryptoApi\Blockchain\UTXO;
use MultiCryptoApi\Contract\BlockbookInterface;
use MultiCryptoApi\Contract\BlockchainDataResolver;
use MultiCryptoApi\Util\Throttler;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Override;

/**
 * @property  ?RpcCredentials $credentials
 */
abstract class BlockbookAbstract implements BlockbookInterface, BlockchainDataResolver
{
	protected Client $http;

	protected int $decimals = 8;
	protected string $name = "Bitcoin";
	protected string $symbol = "BTC";

	protected array $options = [];
	protected Throttler $throttler;

	public function __construct(
		protected RpcCredentials $credentials,
		array $options = []
	)
	{
		$this->options = $options;
	}

	public function getOption(string $key): mixed
	{
		return $this->options[$key] ?? null;
	}

	#[Override] public function getName(): string
	{
		return $this->name;
	}

	#[Override] public function getSymbol(): string
	{
		return $this->symbol;
	}

	#[Override] public function getAddressTransactions(
		string $address,
		int    $page = 1,
		int    $pageSize = 1000,
	): TransactionList
	{
		$data = $this->loadAddress($address, $page, $pageSize, 'txs');

		$result = [];
		foreach ($data['transactions'] ?? [] as $tx) {
			$result[] = $this->resolveTx($tx);
		}

		return new TransactionList($result, $data['page'], $data['totalPages']);
	}

	public function resolveTx(mixed $data): Transaction
	{
		$vInOutFactory = fn($v) => new TxvInOut(
			$v['addresses'][0] ?? null,

			Amount::satoshi($v['value'] ?? $data['value'] ?? "0", $this->getDecimals()),

			$v['n'] ?? 0,
			$this->resolveAssets($data['tokenTransfers'] ?? []),
			$v
		);

		$vin = array_map($vInOutFactory, $data['vin']);
		$vout = array_map($vInOutFactory, $data['vout']);

		return new Transaction(
			$data['txid'],
			$data['blockHeight'] ?? '',
			$data['confirmations'],
			$data['blockTime'],
			$vin,
			$vout,
			Amount::satoshi($data['fees'], $this->getDecimals()),
			$data
		);
	}

	#[Override] public function getDecimals(): int
	{
		return $this->decimals;
	}

	public function resolveAssets(array $data): array
	{
		return array_map(fn($token) => $this->resolveAsset($token), $data);
	}

	/**
	 * @param mixed $data
	 * @return Asset
	 */
	public function resolveAsset(mixed $data): Asset
	{
		return new Asset(
			$data['type'],
			$data['contract'],
			Amount::satoshi($data['balance'] ?? $data['value'] ?? "0", $data['decimals'] ?? $this->decimals),
			$data['name'] ?? "",
			$data['symbol'] ?? "",
			$data
		);
	}

	#[Override] public function getBlock(string $hash = 'latest'): Block
	{
		$data = $this->loadBlock($hash);

		return $this->resolveBlock($data);
	}

	#[Override] public function getAddress(string $address, bool $loadAssets = false): Address
	{
		$data = $this->loadAddress($address, 1, 0);

		$assets = $loadAssets ? $this->getAssets($address) : [];
		$balance = Amount::satoshi($data['balance'] ?? 0, $this->getDecimals());

		return new Address($address, $balance, $assets, $data);
	}

	#[Override] public function getAssets(string $address): array
	{
		$response = $this->loadAddress($address, 1, 0, 'tokenBalances');
		$tokens = array_filter(
			$response['tokens'] ?? [],
			fn($token) => isset($token['balance']) && $token['balance'] > 0
		);

		return $this->resolveAssets($tokens);
	}

	#[Override] public function getTx(string $txid): ?Transaction
	{
		return $this->resolveTx($this->loadTx($txid));
	}

	#[Override] public function pushRawTransaction(RawTransaction $hex): PushedTX
	{
		$uri = $this->credentials->blockbookUri . '/api/v2/sendtx/' . $hex->payload;
		$response = $this->http()->get($uri)->getBody()->getContents();
		$data = json_decode($response, true);

		return new PushedTX($data['result'], $hex->payload, $response);
	}

	#[Override] public function getUTXO(string $address, bool $confirmed = true): array
	{
		$uri = $this->credentials->blockbookUri . '/api/v2/utxo/' . $address . '?confirmed=' . ($confirmed ? 'true' : 'false');
		$response = $this->http()->get($uri)->getBody()->getContents();
		$data = json_decode($response, true);

		foreach ($data as $utxo) {
			$result[] = new UTXO($utxo['txid'], $utxo['vout'], $utxo['value'], $utxo['height'], $utxo['confirmations']);
		}

		return $result ?? [];
	}

	/**
	 * @param $data
	 * @return Block
	 */
	#[Override] public function resolveBlock($data): Block
	{
		return new Block(
			$data['height'],
			$data['hash'],
			array_column($data['txs'] ?? [], 'txid'),
			$data['previousBlockHash'],
			$data,
		);
	}

	protected function throttle(callable $fn, int $maxRps): mixed
	{
		if (!isset($this->throttler)) {
			$this->throttler = new Throttler();
		}

		return $this->throttler->execute($fn, $maxRps);
	}

	protected function loadAddress(
		string $address,
		int    $page = 1,
		int    $pageSize = 1000,
		string $details = 'basic',
	): mixed
	{
		$uri = $this->credentials->blockbookUri . '/api/v2/address/' . $address . '?page=' . $page . '&pageSize=' . $pageSize . '&details=' . $details;
		$response = $this->http()->get($uri);

		$json = json_decode($response->getBody()->getContents(), true);

		return $json ?? [];
	}

	protected function http(): Client
	{
		if (!isset($this->http)) {
			$this->http = new Client([
				'headers' => $this->credentials?->getHeaders(),
				'username' => $this->credentials?->username,
				'password' => $this->credentials?->password,
				'timeout' => 10,
			]);
		}

		return $this->http;
	}

	protected function loadBlock(string $hash = 'latest', ?int $try = 0): mixed
	{
		if ($hash == 'latest') {
			$data = $this->http()->get($this->credentials->blockbookUri . '/api/')->getBody()->getContents();
			$data = json_decode($data, true);
			$blockHash = $data['backend']['bestBlockHash'];
		}

		$blockHash ??= $hash;

		$uri = $this->credentials->blockbookUri . '/api/v2/block/' . $blockHash;
		try {
			$response = $this->http()->get($uri);
		} catch (GuzzleException $e) {
			if (!str_contains(strtolower($e->getMessage()), 'block not found') || $try > 5) {
				throw $e;
			}

			usleep(100_000);

			return $this->loadBlock($hash, ++$try);
		}

		return json_decode($response->getBody()->getContents(), true);
	}

	protected function loadTx(string $txId)
	{
		$uri = $this->credentials->blockbookUri . '/api/v2/tx/' . $txId;
		$response = $this->http()->get($uri);

		return json_decode($response->getBody()->getContents(), true);
	}
}