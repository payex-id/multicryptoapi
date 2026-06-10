<?php

namespace Chikiday\MultiCryptoApi\Blockbook;

use Chikiday\MultiCryptoApi\Blockbook\Abstract\BlockbookAbstract;
use Chikiday\MultiCryptoApi\Blockchain\Amount;
use Chikiday\MultiCryptoApi\Blockchain\PushedTX;
use Chikiday\MultiCryptoApi\Blockchain\RawTransaction;
use Chikiday\MultiCryptoApi\Blockchain\RpcCredentials;
use Chikiday\MultiCryptoApi\Enum\BroadcastMode;
use Chikiday\MultiCryptoApi\Exception\MultiCryptoApiException;
use Override;
use Chikiday\MultiCryptoApi\Interface\EvmLikeInterface;
use Chikiday\MultiCryptoApi\Interface\TokenAwareInterface;
use Chikiday\MultiCryptoApi\Model\TokenInfo;
use Chikiday\MultiCryptoApi\Util\EthUtil;
use JsonRPC\Client;
use kornrunner\Keccak;
use Web3\Contracts\Ethabi;

class EthereumBlockbook extends BlockbookAbstract implements TokenAwareInterface, EvmLikeInterface
{
	public bool $debug = false;
	protected int $decimals = 18;
	protected string $name = 'Ethereum';
	protected string $symbol = 'ETH';
	protected Client $rpc;
	protected array $tokens;
	protected string $cacheDir;
	protected ?\Closure $nonceProvider = null;
	private ?Client $broadcastRpcClient = null;

	public function setNonceProvider(\Closure $provider): static
	{
		$this->nonceProvider = $provider;
		return $this;
	}

	#[Override]
	public function pushRawTransaction(RawTransaction $hex): PushedTX
	{
		$mode = $this->getBroadcastMode();
		if ($mode === BroadcastMode::Blockbook) {
			return parent::pushRawTransaction($hex);
		}

		try {
			return $this->pushViaRpc($hex);
		} catch (\Throwable $e) {
			if ($this->isNonRetryableBroadcastError($e) || $mode === BroadcastMode::Rpc) {
				throw $e;
			}

			return parent::pushRawTransaction($hex);
		}
	}

	public function __construct(
		public RpcCredentials $credentials,
		public ?string        $infuraWssUrl = '',
		public readonly int   $chainId = 1,
		array $options = [],
	)
	{
		$this->rpc = new Client($this->credentials->uri);
		$this->options = $options;
	}

	public function getGasPrice(): int
	{
		$data = $this->jsonRpc('eth_gasPrice', []);

		return hexdec($data);
	}

	/**
	 * EIP-1559 fee fields for transaction building (wei).
	 * Uses eth_feeHistory when available; falls back to eth_gasPrice with a multiplier.
	 *
	 * Options (constructor $options):
	 * - eip1559FeeHistoryBlocks (int, default 5): blocks for eth_feeHistory
	 * - eip1559RewardPercentiles (float[], default [50, 75]): reward percentiles for fee history
	 * - eip1559GasPriceFallbackMultiplier (float, default 1.2): used when eth_feeHistory fails
	 *
	 * @return array{maxPriorityFeePerGas: int, maxFeePerGas: int, baseFeePerGas: int}
	 */
	public function getEIP1559Fees(): array
	{
		try {
			$blocks = (int) ($this->getOption('eip1559FeeHistoryBlocks') ?? 5);
			$blocks = max(1, min(1024, $blocks));
			$percentiles = $this->getOption('eip1559RewardPercentiles');
			if (!is_array($percentiles) || $percentiles === []) {
				$percentiles = [50, 75];
			}
			$percentiles = array_values(array_map(static fn($p) => (float) $p, $percentiles));

			$result = $this->jsonRpc('eth_feeHistory', [
				'0x' . dechex($blocks),
				'latest',
				$percentiles,
			]);

			if (!is_array($result) || empty($result['baseFeePerGas']) || !is_array($result['baseFeePerGas'])) {
				throw new \RuntimeException('Invalid eth_feeHistory response');
			}

			$baseFeeHexes = $result['baseFeePerGas'];
			$lastKey = array_key_last($baseFeeHexes);
			$nextBaseFee = $this->hexQuantityToInt((string) $baseFeeHexes[$lastKey]);

			$maxPriority = $this->priorityTipFromFeeHistoryReward($result['reward'] ?? null, count($percentiles));
			if ($maxPriority <= 0) {
				$maxPriority = $this->tryMaxPriorityFeePerGasFromRpc() ?? 2_000_000_000;
			}

			$maxFee = 2 * $nextBaseFee + $maxPriority;

			return [
				'maxPriorityFeePerGas' => $maxPriority,
				'maxFeePerGas'         => $maxFee,
				'baseFeePerGas'        => $nextBaseFee,
			];
		} catch (\Throwable $e) {
			return $this->getEIP1559FeesFallback();
		}
	}

	/**
	 * @return array{maxPriorityFeePerGas: int, maxFeePerGas: int, baseFeePerGas: int}
	 */
	protected function getEIP1559FeesFallback(): array
	{
		$multiplier = (float) ($this->getOption('eip1559GasPriceFallbackMultiplier') ?? 1.2);
		if ($multiplier < 1.0) {
			$multiplier = 1.2;
		}
		$gasPrice = $this->getGasPrice();
		$maxFee = (int) round($gasPrice * $multiplier);
		$maxPriority = min((int) ($gasPrice / 10), 3_000_000_000);
		$maxPriority = max($maxPriority, 1_000_000_000);
		if ($maxFee <= $maxPriority) {
			$maxFee = $maxPriority + (int) max(1, $gasPrice / 2);
		}
		$baseFallback = max(1, $gasPrice - $maxPriority);

		return [
			'maxPriorityFeePerGas' => $maxPriority,
			'maxFeePerGas'         => $maxFee,
			'baseFeePerGas'        => $baseFallback,
		];
	}

	protected function tryMaxPriorityFeePerGasFromRpc(): ?int
	{
		try {
			$data = $this->jsonRpc('eth_maxPriorityFeePerGas', []);
			if ($data === null || $data === '') {
				return null;
			}
			return $this->hexQuantityToInt((string) $data);
		} catch (\Throwable $e) {
			return null;
		}
	}

	/**
	 * Use the highest percentile column (last index): max tip across recent blocks.
	 */
	protected function priorityTipFromFeeHistoryReward(mixed $reward, int $percentileCount): int
	{
		if (!is_array($reward) || $reward === [] || $percentileCount < 1) {
			return 0;
		}
		$idx = $percentileCount - 1;
		$tips = [];
		foreach ($reward as $blockRewards) {
			if (!is_array($blockRewards) || !array_key_exists($idx, $blockRewards)) {
				continue;
			}
			$cell = $blockRewards[$idx];
			if ($cell === null || $cell === '') {
				continue;
			}
			$tips[] = $this->hexQuantityToInt((string) $cell);
		}
		if ($tips === []) {
			return 0;
		}

		return max($tips);
	}

	protected function hexQuantityToInt(string $hex): int
	{
		$hex = strtolower(trim($hex));
		if (str_starts_with($hex, '0x')) {
			$hex = substr($hex, 2);
		}
		if ($hex === '') {
			return 0;
		}

		return (int) EthUtil::hexToDec($hex);
	}

	public function evmCall(
		string $contractAddress,
		string $method,
		?array $args = null,
		?array $argsTypes = null,
	): string
	{
		if ($args && count($args) != count($argsTypes)) {
			throw new \InvalidArgumentException('Invalid number of arguments and types');
		}

		return $this->jsonRpc('eth_call', [
			[
				'to'   => $contractAddress,
				'data' => $this->encodeMethod($method, $argsTypes, $args),
				'from' => '0x0000000000000000000000000000000000000000',
			],
			'latest',
		]);
	}

	public function getTransactionCount(string $address): int
	{
		if (substr($address, 0, 2) != '0x') {
			$address = "0x" . $address;
		}

		$networkNonce = hexdec($this->jsonRpc('eth_getTransactionCount', [$address, 'pending']));

		if ($this->nonceProvider) {
			$externalNonce = (int) ($this->nonceProvider)($address);
			return max($networkNonce, $externalNonce);
		}

		return $networkNonce;
	}

	public function getTokenInfo(string $address): ?TokenInfo
	{
		$this->tokens ??= $this->loadTokens();

		return $this->tokens[$address] ??= $this->loadToken($address);
	}

	public function setCacheDir(string $path): TokenAwareInterface
	{
		$this->cacheDir = $path;

		return $this;
	}

	protected function jsonRpc(string $method, array $params = []): mixed
	{
		$_headers = [];

		return $this->rpc->execute($method, $params, [], null, $_headers);
	}

	private function getBroadcastMode(): BroadcastMode
	{
		$mode = $this->getOption('broadcastMode');
		if (is_string($mode) && $mode !== '') {
			return BroadcastMode::tryFrom($mode) ?? BroadcastMode::Blockbook;
		}

		// @deprecated use broadcastMode instead
		$fallback = $this->getOption('broadcastFallbackToBlockbook');
		if ($fallback === false) {
			return BroadcastMode::Rpc;
		}
		if ($fallback === true) {
			return BroadcastMode::RpcWithFallback;
		}

		return BroadcastMode::Blockbook;
	}

	private function getBroadcastRpcClient(): Client
	{
		$broadcastUrl = $this->getOption('broadcastRpcUrl');
		if (!is_string($broadcastUrl) || $broadcastUrl === '' || $broadcastUrl === $this->credentials->uri) {
			return $this->rpc;
		}

		return $this->broadcastRpcClient ??= new Client($broadcastUrl);
	}

	private function pushViaRpc(RawTransaction $hex): PushedTX
	{
		$txid = $this->getBroadcastRpcClient()->execute('eth_sendRawTransaction', [$hex->payload]);

		if (empty($txid) || !is_string($txid)) {
			throw new MultiCryptoApiException(
				'eth_sendRawTransaction returned empty or non-string result: ' . json_encode($txid)
			);
		}

		return new PushedTX($txid, $hex->payload, $txid);
	}

	private function isNonRetryableBroadcastError(\Throwable $e): bool
	{
		$msg = strtolower($e->getMessage());

		return str_contains($msg, 'nonce too low')
			|| str_contains($msg, 'insufficient funds')
			|| str_contains($msg, 'already known')
			|| str_contains($msg, 'replacement transaction underpriced')
			|| str_contains($msg, 'transaction underpriced')
			|| str_contains($msg, 'max fee per gas less than block base fee');
	}

	protected function jsonRpcWithRetry(string $method, array $params = [], int $retries = 5): mixed
	{
		$try = 0;
		while (is_null($data = $this->jsonRpc($method, $params))) {
			if (++$try >= $retries) {
				throw new MultiCryptoApiException("JSON RPC CALL '{$method}' returned null more than {$retries} retries");
			}
		}

		return $data;
	}

	protected function getTokenInfoAtContract(string $address): ?TokenInfo
	{
		$abi = new Ethabi();
		try {
			$name = $this->evmCall($address, 'name()');
			$name = $abi->decodeParameter('string', $name);
			$symbol = $this->evmCall($address, 'symbol()');
			$symbol = $abi->decodeParameter('string', $symbol);

			$decimals = hexdec($this->evmCall($address, 'decimals()'));
		} catch (\Throwable $e) {
			return null;
		}

		if (!$name || !$symbol || !$decimals) {
			return null;
		}
		// just a hack to avoid exception
		if ($decimals == 'INF') {
			$decimals = 18;
		}

		return new TokenInfo($address, $name, $symbol, (int) $decimals, 'erc20');
	}

	protected function getCacheFilename(): string
	{
		$dir = $this->cacheDir ?? sys_get_temp_dir();
		$name = __CLASS__ . '_';
		if ($this->chainId != 1) {
			$name = '_' . $this->chainId;
		}
		$name .= '_tokens.json';

		return $dir . '/' . $name;
	}

	protected function encodeMethod(string $method, ?array $types = null, ?array $args = null): string
	{
		$hash = Keccak::hash($method, 256);
		// Берем первые 4 байта (8 символов) хеша
		$signature = '0x' . substr($hash, 0, 8);

		$data = $signature;

		// ABI-кодирование аргументов
		if ($types) {
			$abi = new Ethabi();
			$encodedArgs = $abi->encodeParameters($types, $args);

			$data .= substr($encodedArgs, 2); // Убираем '0x' префикс
		}

		return $data;
	}

	protected function loadToken(string $address): ?TokenInfo
	{
		$token = $this->getTokenInfoAtContract($address);

		file_put_contents($this->getCacheFilename(), json_encode([$address => $token] + $this->tokens));

		if ($this->debug) {
			$name = $token->name ?? "NoName";
			$symbol = $token->symbol ?? "NoSymbol";
			$decimals = $token->decimals ?? "NoDecimals";
			echo "New token loaded: " . $address . ": {$decimals} decimals / {$name} {$symbol}\n";
		}

		return $token;
	}

	protected function loadTokens(): array
	{
		$cacheFile = $this->getCacheFilename();
		if (!file_exists($cacheFile) || !json_validate($_content = file_get_contents($cacheFile))) {
			return [];
		}

		$content = json_decode($_content, true);
		$content = array_filter($content);

		return array_map(fn($token) => TokenInfo::import($token), $content);
	}

	public function getErc20Balance(string $contractAddress, string $holderAddress): ?Amount
	{
		$token = $this->getTokenInfo($contractAddress);
		if (!$token) {
			return null;
		}
		$balance = $this->evmCall(
			$contractAddress,
			'balanceOf(address)',
			[$holderAddress],
			["address"]
		);

		$balance = EthUtil::hexToDec($balance);

		return Amount::satoshi($balance, $token->decimals);
	}
}