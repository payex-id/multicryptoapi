<?php

namespace Chikiday\MultiCryptoApi\Blockbook;

use Chikiday\MultiCryptoApi\Blockbook\Abstract\BlockbookAbstract;
use Chikiday\MultiCryptoApi\Blockchain\Address;
use Chikiday\MultiCryptoApi\Blockchain\Amount;
use Chikiday\MultiCryptoApi\Blockchain\Asset;
use Chikiday\MultiCryptoApi\Blockchain\RpcCredentials;
use Chikiday\MultiCryptoApi\Blockchain\Transaction;
use Chikiday\MultiCryptoApi\Blockchain\TxvInOut;
use Chikiday\MultiCryptoApi\Interface\TokenAwareInterface;
use Chikiday\MultiCryptoApi\Interface\UnconfirmedBalanceFeatureInterface;
use Chikiday\MultiCryptoApi\Model\TokenInfo;
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

	#[Override] public function getAddress(string $address, bool $loadAssets = false): Address
	{
		$result = parent::getAddress($address, $loadAssets);
		$originData = $result->originData;
		$originData['details'] = $this->normalizeAddressDetails($originData);

		return new Address(
			$result->address,
			$result->balance,
			$result->assets,
			$originData,
		);
	}

	/**
	 * Blockbook TRX address API migrated from details.* to chainExtraData.payload.*.
	 * Normalize both formats into legacy details keys used by PayEx consolidator.
	 */
	private function normalizeAddressDetails(array $data): array
	{
		$details = $data['details'] ?? [];
		if (isset($details['energyTotal'], $details['energyUsed'])) {
			return $details;
		}

		$payload = $data['chainExtraData']['payload'] ?? null;
		if ($payload === null) {
			return $details;
		}

		$energyTotal = (int) ($payload['totalEnergy'] ?? 0);
		$energyAvailable = (int) ($payload['availableEnergy'] ?? 0);
		$bandwidthTotal = (int) ($payload['totalStakedBandwidth'] ?? 0) + (int) ($payload['totalFreeBandwidth'] ?? 0);
		$bandwidthAvailable = (int) ($payload['availableStakedBandwidth'] ?? 0) + (int) ($payload['availableFreeBandwidth'] ?? 0);
		$stakingInfo = $payload['stakingInfo'] ?? [];

		$normalized = [
			'energyTotal'    => $energyTotal,
			'energyUsed'     => max(0, $energyTotal - $energyAvailable),
			'bandwidthTotal' => $bandwidthTotal,
			'bandwidthUsed'  => max(0, $bandwidthTotal - $bandwidthAvailable),
			'isActive'       => ($data['txs'] ?? 0) > 0 || (int) ($data['balance'] ?? 0) > 0,
			'frozenListV2'   => $details['frozenListV2'] ?? [],
		];

		if ($normalized['frozenListV2'] === [] && $this->hasOutgoingDelegations($stakingInfo)) {
			$normalized['frozenListV2'] = $this->loadOutgoingDelegations($data['address']);
		}

		return $normalized;
	}

	private function hasOutgoingDelegations(array $stakingInfo): bool
	{
		return (int) ($stakingInfo['delegatedBalanceEnergy'] ?? 0) > 0
			|| (int) ($stakingInfo['delegatedBalanceBandwidth'] ?? 0) > 0;
	}

	/**
	 * @return array<int, array{type: string, amount: int, delegateTo: string}>
	 */
	private function loadOutgoingDelegations(string $address): array
	{
		try {
			$index = $this->tron->getManager()->request('/wallet/getdelegatedresourceaccountindexv2', [
				'value'   => $address,
				'visible' => true,
			]);
		} catch (\Throwable) {
			return [];
		}

		$result = [];
		foreach ($index['toAccounts'] ?? [] as $toAddress) {
			try {
				$delegated = $this->tron->getManager()->request('/wallet/getdelegatedresourcev2', [
					'fromAddress' => $address,
					'toAddress'   => $toAddress,
					'visible'     => true,
				]);
			} catch (\Throwable) {
				continue;
			}

			foreach ($delegated['delegatedResource'] ?? [] as $item) {
				if (!empty($item['frozen_balance_for_energy'])) {
					$result[] = [
						'type'       => 'ENERGY',
						'amount'     => (int) $item['frozen_balance_for_energy'],
						'delegateTo' => $toAddress,
					];
				}
				if (!empty($item['frozen_balance_for_bandwidth'])) {
					$result[] = [
						'type'       => 'BANDWIDTH',
						'amount'     => (int) $item['frozen_balance_for_bandwidth'],
						'delegateTo' => $toAddress,
					];
				}
			}
		}

		return $result;
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
		$data = $this->normalizeTrxBlockbookTxData($data);
		$contractType = $data['contract_type'] ?? null;
		$tokenTransfers = $data['tokenTransfers'] ?? [];
		$firstTransfer = $tokenTransfers[0] ?? null;

		$fromAddress = $this->resolveTrxFromAddress($data, $firstTransfer);
		$toAddress = $this->resolveTrxToAddress($data, $firstTransfer, $contractType);
		$value = $this->resolveTrxValue($data, $firstTransfer, $contractType);

		$_assets = $this->resolveAssets($tokenTransfers);
		$valueAmount = Amount::satoshi($value, $this->getDecimals());
		$vIn = [
			new TxvInOut($fromAddress, $valueAmount, 0, $_assets),
		];

		$vOut = [
			new TxvInOut($toAddress, $valueAmount, 0, $_assets),
		];

		$height = empty($data['blockHeight']) ? null : (int) $data['blockHeight'];

		return new Transaction(
			str_replace('0x', '', $data['txid']),
			$height,
			$data['confirmations'],
			$data['blockTime'],
			$vIn,
			$vOut,
			$valueAmount,
			$data,
			$this->resolveTrxIsSuccess($data),
		);
	}

	/**
	 * Blockbook TRX tx API migrated contract/delegate fields into chainExtraData.payload.
	 */
	private function normalizeTrxBlockbookTxData(array $data): array
	{
		$payload = $data['chainExtraData']['payload'] ?? null;
		if ($payload === null) {
			return $data;
		}

		if (!isset($data['contract_name']) && !empty($payload['contractType'])) {
			$data['contract_name'] = $payload['contractType'];
		}

		$contractType = $payload['contractType'] ?? null;
		$resourceType = strtoupper((string) ($payload['resource'] ?? 'BANDWIDTH'));

		if ($contractType === 'DelegateResourceContract' && !isset($data['delegateInfo'])) {
			$data['delegateInfo'] = [
				'delegateTo' => $payload['delegateTo'] ?? '',
				'type'       => $resourceType,
				'amount'     => (string) ($payload['delegateAmount'] ?? '0'),
			];
		}

		if ($contractType === 'UnDelegateResourceContract' && !isset($data['undelegateInfo'])) {
			$data['undelegateInfo'] = [
				'prevDelegate' => $payload['delegateTo'] ?? '',
				'type'           => $resourceType,
				'amount'         => (string) ($payload['delegateAmount'] ?? '0'),
			];
		}

		return $data;
	}

	/**
	 * Blockbook TRX txs use vin/vout and tokenTransfers; legacy fields toAddress/fromAddress are often absent.
	 */
	private function resolveTrxFromAddress(array $data, ?array $firstTransfer): string
	{
		return $data['fromAddress']
			?? $firstTransfer['from']
			?? $this->resolveVinAddress($data)
			?? '';
	}

	private function resolveTrxToAddress(array $data, ?array $firstTransfer, mixed $contractType): string
	{
		if ($firstTransfer !== null) {
			return (string) ($firstTransfer['to'] ?? '');
		}

		if (isset($data['toAddress'])) {
			return (string) $data['toAddress'];
		}

		if (in_array($contractType, [2, 31], true) && !empty($data['tokenTransfers'][0]['to'])) {
			return (string) $data['tokenTransfers'][0]['to'];
		}

		return $this->resolveVoutRecipientAddress($data) ?? '';
	}

	private function resolveTrxValue(array $data, ?array $firstTransfer, mixed $contractType): string
	{
		if (in_array($contractType, [57, 58], true)) {
			return '0';
		}

		if ($firstTransfer !== null && (string) ($data['value'] ?? '0') === '0') {
			return (string) ($firstTransfer['value'] ?? '0');
		}

		return (string) ($data['value'] ?? '0');
	}

	private function resolveVinAddress(array $data): ?string
	{
		return $data['vin'][0]['addresses'][0] ?? null;
	}

	/**
	 * Prefer a vout with non-zero value (native TRX); otherwise last vout (e.g. USDT contract call).
	 */
	private function resolveVoutRecipientAddress(array $data): ?string
	{
		foreach ($data['vout'] ?? [] as $vout) {
			if (!empty($vout['addresses'][0]) && (int) ($vout['value'] ?? 0) > 0) {
				return $vout['addresses'][0];
			}
		}

		$vouts = $data['vout'] ?? [];
		if ($vouts === []) {
			return null;
		}

		$last = $vouts[array_key_last($vouts)];

		return $last['addresses'][0] ?? null;
	}

	private function resolveTrxIsSuccess(array $data): ?bool
	{
		return Transaction::resolveTronIsSuccess($data);
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