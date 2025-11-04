<?php

namespace MultiCryptoApi\Provider;

use MultiCryptoApi\Provider\Abstract\BlockbookAbstract;
use MultiCryptoApi\Blockchain\Amount;
use MultiCryptoApi\Blockchain\RpcCredentials;
use MultiCryptoApi\Exception\MultiCryptoApiException;
use MultiCryptoApi\Contract\EvmLikeInterface;
use MultiCryptoApi\Contract\TokenAwareInterface;
use MultiCryptoApi\Model\TokenInfo;
use MultiCryptoApi\Util\EthUtil;
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

		$cnt = hexdec($this->jsonRpc('eth_getTransactionCount', [$address, 'pending']));

		return $cnt;
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