<?php

namespace MultiCryptoApi\Stream;

use MultiCryptoApi\Api\TxBuilder\EthereumTxBuilder;
use MultiCryptoApi\Provider\EthereumBlockbook;
use MultiCryptoApi\Blockchain\Amount;
use MultiCryptoApi\Exception\MultiCryptoApiException;
use MultiCryptoApi\Contract\StreamableInterface;
use MultiCryptoApi\Model\IncomingBlock;
use MultiCryptoApi\Model\IncomingTransaction;
use MultiCryptoApi\Stream\Abstract\AbstractStream;
use MultiCryptoApi\Util\EthUtil;
use Closure;
use kornrunner\Ethereum\Contract;
use Ratchet\Client\WebSocket;
use React\EventLoop\TimerInterface;
use Web3\Utils;

use function Ratchet\Client\connect;

class EthereumStream extends AbstractStream
{
	/**
	 * @var true
	 */
	private bool $started;
	private int $nonce = 0;
	private array $callbacks;
	private WebSocket $conn;
	private float $lastMessageTime;
	private int $staleTimeout = 3;
	private TimerInterface $timerId;
	private array $subscriptions = [];
	protected EthUtil $util;

	public function __construct(public readonly EthereumBlockbook $blockbook)
	{
		$this->util = new EthUtil();
	}

	public function cancelSubscriptions(): StreamableInterface
	{
		$this->getLoop()->cancelTimer($this->timerId);
		foreach ($this->subscriptions as $id) {
			$this->unsubscribe($id);
		}

		return $this;
	}

	public function setStaleTimeout(int $staleTimeout): self
	{
		$this->staleTimeout = $staleTimeout;
		return $this;
	}

	protected function sub(): void
	{
		$this->lastMessageTime = microtime(true);
		$loop = $this->getLoop();
		connect($this->resolveStreamUri(), [], [], $loop)->then(function (WebSocket $conn) {
			$this->conn = $conn;

			$conn->on('message', function ($msg) {
				$this->processIncomingMessage($msg);
			});

			$this->subscribeToBlocks();
//			$this->subscribeToLogs();

			$this->lastMessageTime = microtime(true);
		}, function ($e) {
			throw new MultiCryptoApiException("Could not connect: {$e->getMessage()}");
		});
	}

	/**
	 * @return void
	 */
	protected function subscribeToLogs(): void
	{
		$transferSignature = Utils::sha3('Transfer(address,address,uint256)');
		$logsSubRequest = ['logs', ['topics' => [$transferSignature]]];
		$this->subscribe(
			$logsSubRequest,
			fn($log) => $this->resolveLog($log),
		);
	}

	/**
	 * @return void
	 */
	protected function subscribeToBlocks(): void
	{
		$this->subscribe(['newHeads'], function ($result) {
			$this->processBlock($result);
		});
	}

	protected function start(): void
	{
		if ($this->started ?? false) {
			// already started
			return;
		}

		$this->started = true;
		$loop = $this->getLoop();
		$this->timerId = $loop->addPeriodicTimer(1, function () {
			if ($this->isStale()) {
				$this->stop();
				$this->start();
			}
		});
		$this->sub();
	}

	protected function subscribe(array $params, callable $callback)
	{
		$this->callMethod(
			'eth_subscribe',
			$params,
			function ($result) use ($callback, $params) {
				if (isset($result['error'])) {
					$errorText = "Call " . json_encode($params) . ' returned error: ' . $result['error']['message'];
					throw new MultiCryptoApiException($errorText, $result['error']['code']);
				}

				if (isset($this->callbacks[$result['result']])) {
					throw new MultiCryptoApiException("Already subscribed to " . json_encode($params));
				}

				$this->callbacks[$result['result']] = [
					fn($msg) => $callback($msg['params']['result']),
					false,
					json_encode($params),
				];

				$this->subscriptions[] = $result['result'];

				$this->debug("Subscribed to " . json_encode($params) . " with id: " . $result['result']);
				$this->debug("Callbacks now: " . implode(', ', array_keys($this->callbacks)));
			},
			true
		);
	}

	protected function unsubscribe(string $id): void
	{
		if (!isset($this->callbacks[$id])) {
			return;
		}

		unset($this->subscriptions[$id]);

		$this->callMethod(
			'eth_unsubscribe',
			[$id],
			function ($result) use ($id) {
				if (isset($result['error'])) {
					$errorText = "Unsubscribe '{$id}' error: {$result['error']['message']}";
					$this->debug($errorText);
				} else {
					if (isset($this->callbacks[$id])) {
						unset($this->callbacks[$id]);
					}
					$this->debug("Unsubscribed {$id}");
				}
			},
			true
		);
	}

	protected function callMethod(string $method, array $params, callable $callback, bool $once)
	{
		$data = [
			'method'  => $method,
			'params'  => $params,
			'id'      => $id = ++$this->nonce,
			'jsonrpc' => '2.0',
		];

		$this->callbacks[$id] = [
			$callback,
			$once,
		];

		$payload = json_encode($data);
		$this->conn->send($payload);
	}

	private function processIncomingMessage($message): void
	{
		$this->lastMessageTime = microtime(true);
//		$this->debug("New msg: " . mb_substr($message, 0, 120));
		$json = json_decode($message, true);

		$id = $json['id'] ?? $json['params']['subscription'] ?? null;
		if (!$cb = $this->callbacks[$id] ?? null) {
			throw new MultiCryptoApiException("Unknown response: {$message}");
		}

		if (!empty($json['params']['subscription'])) {
//			$this->debug("Subscription message to " . $this->callbacks[$id][2]);
		}

		call_user_func($cb[0], $json);

		if ($cb[1]) {
			unset($this->callbacks[$json['id']]);
		}
	}

	private function processBlock(array $data): void
	{
		$blockNumber = hexdec($data['number']);
		$block = new IncomingBlock($blockNumber, $data['hash'], $data['parentHash'], []);

//		$this->debug("Block {$blockNumber} received, loading txs...");

		$this->loadBlockTxes($block, function (array $txes) use ($block) {
			$block->txs = $txes;
			$this->triggerBlock($block);
		});
	}

	private function loadBlockTxes(IncomingBlock $uncompletedBlock, Closure $callback, int $try = 0): void
	{
		$time = microtime(true);
		$this->callMethod(
			'eth_getBlockByHash',
			[$uncompletedBlock->hash, true],
			function ($data) use ($uncompletedBlock, $try, $callback, $time) {
				$blockLoadTime = microtime(true) - $time;
				if (empty($data['result'] ?? null)) {
					$msg = "Block {$uncompletedBlock->blockNumber} {$blockLoadTime} #{$try} fail load txs";
//					$this->info($msg);
					$this->loadBlockTxes($uncompletedBlock, $callback, $try + 1);
					return;
				}

				$size = strlen(serialize($data)) / 1024;
//				$this->info("Block {$uncompletedBlock->blockNumber} {$blockLoadTime} tx loading time (size {$size} kb.)");

				$time = microtime(true);

				$txs = [];
				foreach ($data['result']['transactions'] ?? [] as $tx) {
					if (!$tx = $this->getIncomingTransactionsFromBlock($tx)) {
						continue;
					}

					array_map(fn($tx) => $this->triggerTx($tx, true), $tx);
					$txs = array_merge($txs, $tx);
				}

				$txPreparedTime = microtime(true) - $time;

				$time = microtime(true);
				$callback($txs);

				$time = microtime(true) - $time;
//				$this->debug("Block {$uncompletedBlock->blockNumber} {$txPreparedTime} prep / callback {$time}");
			},
			true
		);
	}

	/**
	 * @param array $log
	 * @return IncomingTransaction|null
	 */
	private function getIncomingTransactionFromLog(array $log): ?IncomingTransaction
	{
		if (false === $this->isValidLog($log)) {
			return null;
		}

		$token = $this->blockbook->getTokenInfo($log['address']);
		$wei = hexdec($log['topics'][3] ?? $log['data']);
		$amount = Amount::satoshi($wei, $token?->decimals ?? 18);

		// todo: isSuccess нужно вывлять из лога
		return new IncomingTransaction(
			$log['transactionHash'],
			$from = $this->parsePaddedAddr($log['topics'][1]),
			$to = $this->parsePaddedAddr($log['topics'][2]),
			$amount,
			hexdec($log['blockNumber']),
			$log['address'],
			0,
			0
		);
	}

	private function resolveStreamUri(): ?string
	{
		if ($this->blockbook->infuraWssUrl) {
			// free with infura api key
			return $this->blockbook->infuraWssUrl;
		}

		// otherwise we will use nownodes wss
		$domain = parse_url($this->blockbook->credentials->uri, PHP_URL_HOST);

		return "wss://{$domain}/wss/{$this->blockbook->credentials->getHeaders()['api-key']}";
	}

	private function parsePaddedAddr(string $address): string
	{
		return "0x" . substr($address, 26);
	}

	private function getIncomingTransactionsFromBlock(array $tx): array
	{
		if (empty($tx['to'])) {
			// contract creation? skip
			return [];
		}

		$value = isset($tx['value']) ? EthereumTxBuilder::bchexdec($tx['value']) : 0;
		$txs = [];
		if ($value > 0) {
			$txs[] = new IncomingTransaction(
				$tx['hash'],
				$tx['from'],
				$tx['to'],
				Amount::satoshi($value, 18),
				hexdec($tx['blockNumber']),
				null,
				0,
				1
			);
		}

		if ($input = $this->util->getTransferByInput($tx['input'])) {
			$amount = Amount::satoshi($input->value, 18);
			$txs[] = new IncomingTransaction(
				$tx['hash'],
				$tx['from'],
				$input->to,
				$amount,
				hexdec($tx['blockNumber']),
				$tx['to'],
				0,
				1
			);
		}

		return $txs;
	}

	private function parseEthInput(string $input): array
	{
		$to = substr($input, 34, 40);
		$valueHex = substr($input, 34 + 40, 64);
		$value = EthereumTxBuilder::bchexdec($valueHex);
		return [
			'to'    => $to,
			'value' => $value,
		];
	}

	private function resolveLog(array $log): void
	{
		if (!$tx = $this->getIncomingTransactionFromLog($log)) {
			return;
		}

		$this->triggerTx($tx);
	}

	private function isValidLog(array $log): bool
	{
		if (!isset($log['topics'][1])) {
			return false;
		}

		if (!isset($log['topics'][2])) {
			return false;
		}

		if (!isset($log['topics'][3]) && !isset($log['data'])) {
			return false;
		}

		return true;
	}

	private function isStale(): bool
	{
		if (!$this->lastMessageTime) {
			return false;
		}
		$currentTime = microtime(true);
		return $currentTime - $this->lastMessageTime > $this->staleTimeout;
	}

	private function stop(): void
	{
		$this->info("*** RECONNECT ***: Stale connection detected, stopping process...");
		$this->cancelSubscriptions();
		$this->started = false;
//		$this->callbacks = [];
	}
}