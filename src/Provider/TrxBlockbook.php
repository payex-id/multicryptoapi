<?php

namespace MultiCryptoApi\Provider;

use MultiCryptoApi\Provider\Abstract\BlockbookAbstract;
use MultiCryptoApi\Blockchain\Address;
use MultiCryptoApi\Blockchain\Amount;
use MultiCryptoApi\Blockchain\Asset;
use MultiCryptoApi\Blockchain\RpcCredentials;
use MultiCryptoApi\Blockchain\Transaction;
use MultiCryptoApi\Blockchain\TxvInOut;
use MultiCryptoApi\Contract\TokenAwareInterface;
use MultiCryptoApi\Contract\UnconfirmedBalanceFeatureInterface;
use MultiCryptoApi\Model\TokenInfo;
use IEXBase\TronAPI\Provider\HttpProvider;
use IEXBase\TronAPI\Tron;
use Override;
use RuntimeException;

class TrxBlockbook extends BlockbookAbstract implements UnconfirmedBalanceFeatureInterface, TokenAwareInterface
{
	public readonly Tron $tron;
	public bool $debug = false;
	protected int $decimals = 6;
	protected string $name = 'Tron';
	protected string $symbol = 'TRX';
	private string $cacheDir = '/tmp';
	private array $tokens;

	public function __construct(
		protected RpcCredentials $credentials,
		protected string         $tronGridApiKey = '',
		int                               $httpTimeout = 10,
	)
	{
		$http = new HttpProvider(
			"https://api.trongrid.io",
			$httpTimeout,
			false,
			false,
			[
				'TRON-PRO-API-KEY' => $tronGridApiKey,
			]
		);

		$this->tron = new Tron($http, $http, $http);
	}

	public function setCacheDir(string $path): self
	{
		$this->cacheDir = $path;
		return $this;
	}

	public function getTokenInfo(string $address): ?TokenInfo
	{
		$token = $this->getToken($address);

		if (!$token) {
			return null;
		}

		return new TokenInfo(
			$address,
			$token['name'] ?? 'Unknown',
			$token['abbr'] ?? 'Unknown',
			(int) ($token['decimal'] ?? 18),
			is_numeric($address) ? 'trc10' : 'trc20'
		);
	}

	#[Override] public function getAssets(string $address): array
	{
		// nownodes blockbook returns only trc10 tokens - this is because we use tronscan api instead
		$response = $this->http()->get(
			'https://apilist.tronscanapi.com/api/account/tokens?address=' . $address
		);
		$data = json_decode($response->getBody()->getContents(), true);
		if (!$data || empty($data['data'])) {
			return [];
		}

		foreach ($data['data'] as $token) {
			if ($token['tokenId'] == '_') {
				continue;
			}

			$result[] = new Asset(
				$token['tokenType'],
				$token['tokenId'],
				Amount::satoshi($token['balance'], $token['tokenDecimal']),
				$token['tokenName'],
				$token['tokenAbbr'],
				$token
			);
		}

		return $result ?? [];
	}

	/**
	 * @param array $data
	 * @return Asset
	 */
	public function resolveAsset(mixed $data): Asset
	{
		$id = $data['contract'] ?? $data['token'];
		$decimals = !empty($data['decimals']) ? $data['decimals']
			: ($this->getToken($id)['decimal'] ?? 18);

		$name = $data['name'] ?? $this->getToken($id)['name'] ?? "Unknown";
		if ($name === $id) {
			$name = $this->getToken($id)['name'] ?? $id;
		}

		$abbr = !empty($data['symbol']) ? $data['symbol']
			: ($this->getToken($id)['abbr'] ?? "Unknown");

		return new Asset(
			$data['type'],
			$id,
			Amount::satoshi($data['balance'] ?? $data['value'], $decimals),
			$name,
			$abbr,
			$data
		);
	}

	/**
	 * @return string
	 */
	public function getCacheFilename(): string
	{
		return $this->cacheDir . '/' . __CLASS__ . '_tokens.json';
	}

	public function resolveTx(mixed $data): Transaction
	{
		if (isset($data['tokenTransfers']) && in_array($data['contract_type'], [2, 31])) {
			$_address = $data['tokenTransfers'][0]['to'];
		} else {
			$_address = $data['toAddress'];
		}

		$value = $data['value'];
		if (in_array($data['contract_type'], [57, 58])) {
			$value = "0";
		}

		$_assets = $this->resolveAssets($data['tokenTransfers'] ?? []);
		$vIn = [
			new TxvInOut($data['fromAddress'], $value = Amount::satoshi($value, $this->getDecimals()), 0, $_assets),
		];

		$vOut = [
			new TxvInOut($_address, $value, 0, $_assets),
		];

		$height = empty($data['blockHeight']) ? null : (int) $data['blockHeight'];
		return new Transaction(
			// replace 0x in the beginning
			str_replace('0x', '', $data['txid']),
			$height,
			$data['confirmations'],
			$data['blockTime'],
			$vIn,
			$vOut,
			Amount::satoshi($value, $this->getDecimals()),
			$data,
		);
	}

	public function getToken(string $id): ?array
	{
		if (!isset($this->tokens)) {
			$this->loadTokens();
		}

		if (!isset($this->tokens[$id])) {
			$this->loadToken($id);
		}

		return $this->tokens[$id] ?? null;
	}

	public function loadUnconfirmedFromTrongrid(string $address): Address
	{
		$url = 'https://api.trongrid.io/v1/accounts/%s?only_confirmed=false&only_unconfirmed=true';
		$url = sprintf($url, $address);

		$response = $this->http()->get($url, [
			'headers' => [
				'TRON-PRO-API-KEY' => $this->tronGridApiKey,
			],
		]);
		$json = json_decode($jsonString = $response->getBody()->getContents(), true);
		if ($json['success'] === false) {
			throw new RuntimeException("Trongrid API error: {$jsonString}");
		}

		$data = $json['data'][0];

		$result = [];

		foreach ($data['trc20'] ?? [] as $item) {
			$assetId = array_key_first($item);
			$asset = $this->getToken($assetId) + [
					'balance'  => $item[$assetId],
					'type'     => 'trc20',
					'contract' => $assetId,
				];

			$result[$assetId] = $this->resolveAsset($asset);
		}

		$balance = Amount::satoshi($data['balance'], $this->getDecimals());

		return new Address($address, $balance, $result, []);
	}

	public function getUnconfirmedBalance(string $address, bool $withAssets = false): Address
	{
		return $this->loadUnconfirmedFromTrongrid($address);
	}

	private function loadTokens(): array
	{
		if (isset($this->tokens)) {
			return $this->tokens;
		}

		$cacheFile = $this->getCacheFilename();
		if (file_exists($cacheFile) && json_validate($_content = file_get_contents($cacheFile))) {
			return $this->tokens = json_decode($_content, true);
		}

		// we load only first 500 tokens, and further we will load tokens by one
		$response = $this->http()->get(
			"https://apilist.tronscanapi.com/api/tokens/overview?start=0&limit=500&type=trc20&verifier=robot&showAll=2"
		);
		$data = json_decode($response->getBody()->getContents(), true);
		$tokens = array_column($data['tokens'], null, 'contractAddress');
		$tokens = array_map(fn($token) => [
			'decimal' => $token['decimal'],
			'name'    => $token['name'],
			'abbr'    => $token['abbr'],
		], $tokens);

		file_put_contents($cacheFile, json_encode($tokens));

		return $this->tokens = $tokens;
	}

	private function loadToken(string $id): void
	{
		if (!isset($this->tokens)) {
			$this->loadTokens();
		}

		if (isset($this->tokens[$id])) {
			return;
		}

		if (is_numeric($id)) {
			// trc10
			$response = $this->throttle(
				fn() => $this->http()->get("https://apilist.tronscanapi.com/api/token?id={$id}"),
				5
			);
			$data = json_decode($response->getBody()->getContents(), true);
			$token = [
				'abbr'    => $data['data']['abbr'] ?? null,
				'name'    => $data['data']['name'] ?? null,
				'decimal' => $data['data']['precision'] ?? null,
			];
		} else {
			// trc20
			$response = $this->throttle(
				fn() => $this->http()->get("https://apilist.tronscanapi.com/api/token_trc20?contract={$id}"),
				5
			);
			$data = json_decode($response->getBody()->getContents(), true);
			$token = [
				'abbr'    => $data['trc20_tokens'][0]['symbol'] ?? null,
				'name'    => $data['trc20_tokens'][0]['name'] ?? null,
				'decimal' => $data['trc20_tokens'][0]['decimals'] ?? 18,
			];
		}

		$this->tokens[$id] = $token;

		if ($this->debug) {
			echo "Loaded token: {$id} - {$token['name']} ({$token['abbr']})\n";
		}

		file_put_contents($this->getCacheFilename(), json_encode($this->tokens));
	}

	public function getErc20Balance(string $contractAddress, string $holderAddress): ?Amount
	{
		return null;
	}
}