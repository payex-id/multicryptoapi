<?php

namespace MultiCryptoApi\Stream;

use MultiCryptoApi\Provider\TrxBlockbook;
use MultiCryptoApi\Blockchain\Amount;
use MultiCryptoApi\Exception\MultiCryptoApiException;
use MultiCryptoApi\Contract\StreamableInterface;
use MultiCryptoApi\Model\IncomingBlock;
use MultiCryptoApi\Model\IncomingTransaction;
use MultiCryptoApi\Stream\Abstract\AbstractStream;
use Closure;
use IEXBase\TronAPI\Exception\TronException;
use kornrunner\Ethereum\Contract;
use Override;
use React\EventLoop\TimerInterface;

class TronStream extends AbstractStream
{

	private int $lastBlock = 0;

	private TimerInterface $timer;

	public function __construct(private readonly TrxBlockbook $blockbook)
	{
	}

	#[Override] public function reConsiderBlock(int $blockNumber, Closure $callback): self
	{
		$this->blockCallback = $callback;
		$this->restoreBlocks($blockNumber, $blockNumber);

		return $this;
	}

	#[Override] public function cancelSubscriptions(): StreamableInterface
	{
		$this->getLoop()->cancelTimer($this->timer);

		return $this;
	}


	protected function start(): void
	{
		if (isset($this->timer)) {
			// already started
			return;
		}

		$callback = function () {
			$block = $this->loadLastBlock();
			$blockId = (int)$block['block_header']['raw_data']['number'];
			if ($this->lastBlock == $blockId) {
				return;
			}

			$this->logger?->debug('Got new block {block}', ['block' => $blockId]);

			if ($this->lastBlock > 0 && $blockId - $this->lastBlock > 1) {
				$this->restoreBlocks($this->lastBlock + 1, $blockId - 1);
			}

			$this->lastBlock = $blockId;

			$this->resolveBlock($block);
		};

		$this->timer = $this->getLoop()->addPeriodicTimer(3, $callback);
		$this->getLoop()->futureTick($callback);
	}

	/**
	 * @return array
	 * @throws TronException
	 */
	private function loadLastBlock(?int $blockNumber = null): array
	{
		$params = ['detail' => true];
		if ($blockNumber) {
			$params['id_or_num'] = (string)$blockNumber;
		}

		return $this->blockbook->tron->getManager()->request('/wallet/getblock', $params);
	}

	/**
	 * @param array $block
	 * @return void
	 * @throws TronException
	 */
	private function resolveBlock(array $block): void
	{
		$txes = array_column($block['transactions'] ?? [], null, 'txID');
		$supportedTxs = [];

		foreach ($txes as $tx) {
			if (false === $this->isSupportedTx($tx)) {
				continue;
			}

			if (!$incomingTransaction = $this->getIncomingTransaction($tx, $block)) {
				continue;
			}

			$this->triggerTx($incomingTransaction);
			$supportedTxs[] = $incomingTransaction;
		}

		$block = new IncomingBlock(
			$block['block_header']['raw_data']['number'],
			$block['blockID'],
			$block['block_header']['raw_data']['parentHash'],
			$supportedTxs,
		);

		$this->triggerBlock($block);
	}

	private function getIncomingTransaction(array $tx, array $block): ?IncomingTransaction
	{
		$callData = $tx['raw_data']['contract'][0]['parameter']['value'];
		$from = $this->hex2addr($callData['owner_address']);

		if ($to = $callData['to_address'] ?? null) {
			// coins trx
			$amount = Amount::satoshi($callData['amount'], 6);
		} else {
			[$to, $value] = $this->parseContractTransferArguments($callData['data']);
			$contractAddress = $this->hex2addr($callData['contract_address']);
			$token = $this->blockbook->getToken($contractAddress);
			$amount = Amount::satoshi($value, $token['decimal'] ?? 18);
		}

		$blockNumber = $block['block_header']['raw_data']['number'];
		$confirmations = 1 + $this->lastBlock - $blockNumber;

		$isSuccess = (bool) $tx['ret'][0]['contractRet'];

		return new IncomingTransaction(
			$tx['txID'], $from, $this->hex2addr($to), $amount,
			$blockNumber ?? null,
			$contractAddress ?? null,
			null,
			$confirmations,
			$isSuccess
		);
	}

	/**
	 * @param array $lastBlock
	 * @return array
	 * @throws TronException
	 */
	private function resolveTxs(array $lastBlock): array
	{
		$txs = array_column($lastBlock['transactions'] ?? [], null, 'txID');
		$logsLoader = fn() => $this->blockbook->tron->getManager()->request(
			'/wallet/gettransactioninfobyblocknum',
			['num' => $lastBlock['block_header']['raw_data']['number']]
		);

		$i = 0;
		while (empty($logs = $logsLoader())) {
			usleep(500000);
			if ($i++ >= 5) {
				throw new MultiCryptoApiException('Can not load transactions (logs) from tron api');
			}
		}

		$logs = array_column($logs, null, 'id');

		return array_merge_recursive($txs, $logs);
	}

	private function hex2addr(string $addr): string
	{
		return $this->blockbook->tron->hexString2Address($addr);
	}

	private function isSupportedTx(mixed $tx): bool
	{
		// успешна ли транзакция
		if (($tx['result'] ?? "") == "FAILED") {
			return false;
		}

		// нас интересуют только транзакции с переводом средств
		$type = $tx['raw_data']['contract'][0]['type'];
		if (!in_array($type, ['TriggerSmartContract', 'TransferContract'])) {
			return false;
		}

		// это перевод трона - все гуд
		if ($type === 'TransferContract') {
			return true;
		}

		// это вызов контракта
		$data = $tx['raw_data']['contract'][0]['parameter']['value']['data'] ?? '';
		// нас интересует использование только метода transfer
		if (false === str_starts_with($data, Contract::SIGNATURE_TRANSFER)) {
			return false;
		}

		return true;
	}

	private function restoreBlocks(int $start, int $to)
	{
		$this->logger?->info('Restore blocks from {start} to {to}', ['start' => $start, 'to' => $to]);

		for ($i = $start; $i <= $to; $i++) {
			$j = 0;
			while (empty($block = $this->loadLastBlock($i))) {
				usleep(500000);
				if ($j++ == 5) {
					$this->logger?->error('Too much retries to load #{block}...', ['block' => $i]);
					throw new MultiCryptoApiException("Can not load block #{$i} (logs) from tron api");
				}
				$this->logger?->warning('Can\'t load {block}, try one more time...', ['block' => $i]);
			}
			$this->logger?->debug('Loaded block {block}', ['block' => $i]);

			$this->lastBlock = $i;
			$this->resolveBlock($block);
		}
	}

	private function parseContractTransferArguments(string $_input): array
	{
		// берем адрес перевода (64 padded string) и сумму перевода (64 padded string)
		$_addr = substr($_input, 32, 40);
		$_value = substr($_input, 34 + 40, 64);

		return [
			"41{$_addr}",
			base_convert($_value, 16, 10),
		];
	}
}