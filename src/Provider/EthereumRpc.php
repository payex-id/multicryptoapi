<?php

namespace MultiCryptoApi\Provider;


use MultiCryptoApi\Blockchain\Address;
use MultiCryptoApi\Blockchain\Amount;
use MultiCryptoApi\Blockchain\Asset;
use MultiCryptoApi\Blockchain\Block;
use MultiCryptoApi\Blockchain\RpcCredentials;
use MultiCryptoApi\Blockchain\Transaction;
use MultiCryptoApi\Blockchain\TransactionList;
use MultiCryptoApi\Blockchain\TxvInOut;
use MultiCryptoApi\Exception\MultiCryptoApiException;
use MultiCryptoApi\Model\TokenInfo;
use MultiCryptoApi\Util\EthUtil;
use Override;

/**
 * @property  ?RpcCredentials $credentials
 */
class EthereumRpc extends EthereumBlockbook
{
	protected ?array $allowedTokens = null;

	#[Override] public function getAddressTransactions(
		string $address,
		int    $page = 1,
		int    $pageSize = 1000,
	): TransactionList
	{
		// here we use Etherscan api to avoid hell with eth_getLogs rpc
		$address = strtolower($address);

		$tokens = $this->getAllowedTokens();
		$list = [];
		foreach ($tokens as $token) {
			$list = array_merge($list, $this->getErc20Txs($token, $address, $page, $pageSize));
		}

		$list = array_merge($list, $this->getNormalAddressTxs($address, $page, $pageSize));

		usort($list, function ($a, $b) {
			return $b->blockNumber <=> $a->blockNumber;
		});

		return new TransactionList(
			$list,
			$page,
			count($list) == $pageSize ? $page + 1 : $page
		);
	}

	#[Override] public function getBlock(string $hash = 'latest'): Block
	{
		if ($hash == 'latest' || is_numeric($hash)) {
			if (is_numeric($hash)) {
				$hash = "0x" . dechex($hash);
			}
			$method = 'eth_getBlockByNumber';
		} else {
			$method = 'eth_getBlockByHash';
		}
		$data = $this->jsonRpcWithRetry($method, [$hash, true]);

		return new Block(
			hexdec($data['number']),
			$data['hash'],
			array_column($data['transactions'] ?? [], 'hash'),
			$data['parentHash'],
			$data,
		);
	}

	#[Override] public function getTx(string $txid): ?Transaction
	{
		$data = $this->jsonRpcWithRetry('eth_getTransactionByHash', [$txid]);

		$util = new EthUtil();

		$outputs = $inputs = [];
		$receipt = $this->jsonRpcWithRetry('eth_getTransactionReceipt', [$txid]);
		$success = hexdec($receipt['status'] ?? "0x") > 0;

		$token = $this->getAllowedTokens()[$data['to'] ?? ''] ?? null;
		if ($token) {
			if (!$success) {
				$error = "unsuccessful";
			}

			foreach ($receipt['logs'] ?? [] as $log) {
				if (!$transferByLog = $util->getTransferByLog($token, $log)) {
					continue;
				}

				$outputs[] = new TxvInOut(
					$transferByLog->to,
					"0",
					hexdec($data['transactionIndex'] ?? "0x0"),
					$_assets = [
						$token->toAssetByLog($transferByLog),
					],
					$log
				);

				$inputs[] = new TxvInOut(
					$transferByLog->from,
					"0",
					hexdec($data['transactionIndex'] ?? "0x0"),
					$_assets,
					$log
				);
			}
		} else {
			$value = $util->hexToDec($data['value']);

			$outputs[] = new TxvInOut(
				$data['from'],
				$value,
				hexdec($data['transactionIndex']),
				[],
				$data
			);

			$inputs[] = new TxvInOut(
				$data['to'],
				$value,
				hexdec($data['transactionIndex']),
				[],
				$data
			);
		}

		return new Transaction(
			$txid,
			hexdec($data['blockNumber'] ?? '0x'),
			0,
			0,
			$inputs,
			$outputs,
			0,
			['tx' => $data, 'receipt' => $receipt ?? null],
			$success,
			$error ?? null
		);
	}

	#[Override] public function getAddress(string $address, bool $loadAssets = false): Address
	{
		$data = $this->jsonRpc('eth_getBalance', [$address, 'latest']);

		$balance = EthUtil::hexToDec($data ?? "0x");
		$balance = Amount::satoshi($balance, $this->getDecimals());

		$assets = $loadAssets ? $this->getAssets($address) : [];

		return new Address($address, $balance, $assets);
	}

	#[Override] public function getAssets(string $address): array
	{
		$assets = [];
		foreach ($this->getAllowedTokens() as $asset) {
			if (!$balance = $this->getErc20Balance($asset->contract, $address)) {
				continue;
			}
			$assets[$asset->contract] = new Asset(
				$asset->type,
				$asset->contract,
				$balance,
				$asset->name,
				$asset->symbol
			);
		}

		return $assets;
	}

	#[Override] public function getUTXO(string $address, bool $confirmed = true): array
	{
		return [];
	}

	protected function etherscanApiQuery(array $params): array
	{
		if (!$token = $this->getOption('etherscanApiKey')) {
			throw new MultiCryptoApiException("EtherscanApiKey is required");
		}

		$params = array_merge([
			'chainId' => $this->chainId,
			'apikey'  => $token,
		], $params);

		$url = "https://api.etherscan.io/v2/api?" . http_build_query($params);

		$response = $this->http()->get($url)->getBody()->getContents();

		$data = json_decode($response, true);

		if ($data['status'] == 1) {
			return $data['result'] ?? [];
		}

		if (str_contains($data['message'], 'No transactions found')) {
			return [];
		}

		throw new \Exception("Etherscan API error: " . $data['message']);
	}

	/**
	 * @return TokenInfo[]
	 */
	protected function getAllowedTokens(): array
	{
		if (isset($this->allowedTokens)) {
			return $this->allowedTokens;
		}

		$tokens = $this->getOption('tokens') ?? [];
		if (empty($tokens)) {
			throw new MultiCryptoApiException("tokens is not configured");
		}
		$_assets = [];
		foreach ($tokens as $token) {
			$info = $this->getTokenInfo($token);
			$_assets[$token] = $info;
		}

		return $this->allowedTokens = $_assets;
	}

	private function getErc20Txs(TokenInfo $tokenInfo, string $address, int $page, int $pageSize): array
	{
		$data = $this->etherscanApiQuery([
			'module'          => 'account',
			'action'          => 'tokentx',
			'contractaddress' => $tokenInfo->contract,
			'address'         => $address,
			'page'            => $page,
			'offset'          => $pageSize,
			'startblock'      => 0,
			'endblock'        => 'latest',
			'sort'            => 'desc',
		]);

		$txs = [];
		foreach ($data as $tx) {
			$asset = $tokenInfo->toAsset($tx['value'], $tx['from'], $tx['to']);

			$txs[] = new Transaction(
				$tx['hash'],
				$tx['blockNumber'],
				$tx['confirmations'],
				$tx['timeStamp'],
				[
					new TxvInOut(
						$tx['from'],
						Amount::satoshi("0", $this->getDecimals()),
						$tx['transactionIndex'],
						[$asset],
					),
				],
				[
					new TxvInOut(
						$tx['to'],
						Amount::satoshi("0", $this->getDecimals()),
						$tx['transactionIndex'],
						[$asset],
					),
				],
				"0",
				$tx
			);
		}

		return $txs;
	}

	private function getNormalAddressTxs(string $address, int $page, int $pageSize): array
	{
		$data = $this->etherscanApiQuery([
			'module'     => 'account',
			'action'     => 'txlist',
			'address'    => $address,
			'page'       => $page,
			'offset'     => $pageSize,
			'startblock' => 0,
			'endblock'   => 'latest',
			'sort'       => 'desc',
		]);

		$txs = [];
		foreach ($data as $tx) {
			if (!empty($tx['functionName'])) {
				continue;
			}
			$amount = Amount::satoshi($tx['value'], $this->getDecimals());

			$txs[] = new Transaction(
				$tx['hash'],
				$tx['blockNumber'],
				$tx['confirmations'],
				$tx['timeStamp'],
				[
					new TxvInOut(
						$tx['from'],
						$amount,
						$tx['transactionIndex'],
					),
				],
				[
					new TxvInOut(
						$tx['to'],
						$amount,
						$tx['transactionIndex'],
					),
				],
				"0",
				$tx,
				!$tx['isError']
			);
		}

		return $txs;
	}
}