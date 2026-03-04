<?php

namespace Chikiday\MultiCryptoApi\Blockbook;


use Chikiday\MultiCryptoApi\Blockbook\Trait\EtherscanTrait;
use Chikiday\MultiCryptoApi\Blockchain\Address;
use Chikiday\MultiCryptoApi\Blockchain\Amount;
use Chikiday\MultiCryptoApi\Blockchain\Asset;
use Chikiday\MultiCryptoApi\Blockchain\Block;
use Chikiday\MultiCryptoApi\Blockchain\RpcCredentials;
use Chikiday\MultiCryptoApi\Blockchain\Transaction;
use Chikiday\MultiCryptoApi\Blockchain\TransactionList;
use Chikiday\MultiCryptoApi\Blockchain\TxvInOut;
use Chikiday\MultiCryptoApi\Model\TokenInfo;
use Chikiday\MultiCryptoApi\Util\EthUtil;
use Override;

/**
 * @property  ?RpcCredentials $credentials
 */
class EthereumRpc extends EthereumBlockbook
{
	use EtherscanTrait;

	#[Override] public function getAddressTransactions(
		string $address,
		int    $page = 1,
		int    $pageSize = 1000,
	): TransactionList
	{
		try {
			$list = $this->fetchViaEtherscanAddressTxs($address, $page, $pageSize);

			return $this->buildTransactionList($list, $page, $pageSize, true);
		} catch (\Throwable $e) {
			$list = $this->fetchViaRpcAddressTxs($address, $page, $pageSize);

			return $this->buildTransactionList($list, $page, $pageSize, false, ['fallback' => 'rpc']);
		}
	}

	private function fetchViaRpcAddressTxs(string $address, int $page, int $pageSize): array
	{
		$address = strtolower($address);
		$tokens = $this->getAllowedTokens();
		$erc20 = [];
		foreach ($tokens as $token) {
			try {
				$erc20 = array_merge($erc20, $this->getErc20TxsRpc($token, $address, $page, $pageSize));
			} catch (\Throwable $e) {
				continue;
			}
		}
		
		try {
			$normal = $this->getNormalAddressTxsRpcTrace($address, $page, $pageSize);
		} catch (\Throwable $e) {
			$normal = [];
		}

		return array_merge($erc20, $normal);
	}


	private function getNormalAddressTxsRpcTrace(string $address, int $page, int $pageSize): array
	{
		$transactions = [];
		$addr = strtolower($address);

		$latest = $this->getLatestBlockNumber();
		$lookback = (int) ($this->getOption('traceLookback') ?? 10000);
		$blocksPerQuery = (int) ($this->getOption('traceBlocksPerQuery') ?? 10000);
		if ($blocksPerQuery < 1) {
			$blocksPerQuery = 10000;
		}
		$startBlock = max(0, $latest - $lookback + 1);

		$hashes = [];
		for ($to = $latest; $to >= $startBlock; $to -= $blocksPerQuery) {
			$from = max($startBlock, $to - $blocksPerQuery + 1);

			$base = [
				'count'     => $pageSize * 3, // some providers expect numeric uint64, not hex string
				'fromBlock' => '0x' . dechex($from),
				'toBlock'   => '0x' . dechex($to),
			];

			$paramsOut = $base + ['fromAddress' => [$addr]];
			$paramsIn = $base + ['toAddress' => [$addr]];

			$out = $this->jsonRpcWithRetry('trace_filter', [$paramsOut]);
			$in = $this->jsonRpcWithRetry('trace_filter', [$paramsIn]);

			foreach ([$out, $in] as $traces) {
				foreach ($traces as $t) {
					if (($t['type'] ?? '') !== 'call') {
						continue;
					}
					// top-level only (no internal transfers)
					if (!empty($t['traceAddress'])) {
						continue;
					}
					$valHex = $t['action']['value'] ?? '0x0';
					if (hexdec($valHex) <= 0) {
						continue;
					}
					if (!empty($t['transactionHash'])) {
						$hashes[$t['transactionHash']] = true;
					}
				}
			}
		}

		foreach (array_keys($hashes) as $hash) {
			if ($tx = $this->getTx($hash)) {
				$transactions[] = $tx;
			}
		}

		usort($transactions, function ($a, $b) {
			return $b->blockNumber <=> $a->blockNumber;
		});

		return $transactions;
	}

	private function buildTransactionList(
		array $txs, int $page, int $pageSize, bool $alreadyPaginated = false, array $originData = []
	): TransactionList
	{
		usort($txs, function ($a, $b) {
			return $b->blockNumber <=> $a->blockNumber;
		});

		if ($alreadyPaginated) {
			return new TransactionList(
				$txs,
				$page,
				count($txs) == $pageSize ? $page + 1 : $page,
				$originData
			);
		}

		$offset = max(0, ($page - 1) * $pageSize);
		$paged = array_slice($txs, $offset, $pageSize);
		return new TransactionList(
			$paged,
			$page,
			count($paged) == $pageSize ? $page + 1 : $page,
			$originData
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

		$token = $this->getAllowedTokens()[strtolower($data['to'] ?? '')] ?? null;
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
			$value = Amount::satoshi($value, $this->decimals);

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

	/**
	 * Fallback: fetch ERC-20 txs via RPC eth_getLogs
	 */
	private function getErc20TxsRpc(TokenInfo $tokenInfo, string $address, int $page, int $pageSize): array
	{
		$transactions = [];
		$latest = $this->getLatestBlockNumber();
		$lookback = (int) ($this->getOption('erc20Lookback') ?? 10000); // общее окно поиска по блокам

		// размер окна (кол-во блоков) за один запрос
		$blocksPerQuery = (int) ($this->getOption('erc20BlocksPerQuery') ?? 10000);
		if ($blocksPerQuery < 1) {
			$blocksPerQuery = 10000;
		}
		$startBlock = max(0, $latest - $lookback + 1);

		$transferSig = '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef';
		$topicAddress = '0x' . str_pad(substr(strtolower($address), 2), 64, '0', STR_PAD_LEFT);

		$hashes = [];
		// Идём чанками по блокам, чтобы избежать лимита 10k блоков за запрос
		for ($to = $latest; $to >= $startBlock; $to -= $blocksPerQuery) {
			$from = max($startBlock, $to - $blocksPerQuery + 1);

			$queries = [
				[
					'address'   => strtolower($tokenInfo->contract),
					'fromBlock' => '0x' . dechex($from),
					'toBlock'   => '0x' . dechex($to),
					'topics'    => [
						$transferSig,
						$topicAddress, // from == address
					],
				],
				[
					'address'   => strtolower($tokenInfo->contract),
					'fromBlock' => '0x' . dechex($from),
					'toBlock'   => '0x' . dechex($to),
					'topics'    => [
						$transferSig,
						null,
						$topicAddress, // to == address
					],
				],
			];

			foreach ($queries as $params) {
				try {
					$logs = $this->jsonRpcWithRetry('eth_getLogs', [$params]);
					foreach ($logs as $log) {
						if (!empty($log['transactionHash'])) {
							$hashes[$log['transactionHash']] = true;
						}
					}
				} catch (\Throwable $e) {
					// ignore log fetch errors per-query
				}
			}
		}

		$unique = array_keys($hashes);
		foreach ($unique as $hash) {
			try {
				if ($tx = $this->getTx($hash)) {
					$transactions[] = $tx;
				}
			} catch (\Throwable $e) {
				// ignore bad tx
			}
		}

		usort($transactions, function ($a, $b) {
			return $b->blockNumber <=> $a->blockNumber;
		});

		return $transactions;
	}

	private function getLatestBlockNumber(): int
	{
		$result = $this->jsonRpcWithRetry('eth_blockNumber', []);

		return hexdec($result);
	}
}