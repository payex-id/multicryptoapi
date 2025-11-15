<?php

namespace Chikiday\MultiCryptoApi\Stream;

use BitWasp\Bitcoin\Network\Networks\Bitcoin;
use Chikiday\MultiCryptoApi\Blockbook\BitcoinBlockbook;
use Chikiday\MultiCryptoApi\Blockchain\Amount;
use Chikiday\MultiCryptoApi\Interface\StreamableInterface;
use Chikiday\MultiCryptoApi\Model\IncomingBlock;
use Chikiday\MultiCryptoApi\Model\IncomingTransaction;
use Chikiday\MultiCryptoApi\Stream\Abstract\AbstractStream;
use Throwable;

/**
 * todo: need to refactor to ratchet
 * wip. not works now.
 */
class BitcoinStream extends AbstractStream
{
	/**
	 * @var true
	 */
	private bool $started;

	public function __construct(public readonly BitcoinBlockbook $blockbook)
	{
	}

	protected function start(): void
	{
		if (isset($this->started)) {
			// already started
			return;
		}

		$this->started = true;
		$this->sub();
	}

	private function processIncomingMessage(Text $message): void
	{
		$json = json_decode($message->getContent(), true);

		if ($json['op'] === 'utx') {
			foreach ($this->getIncomingTransactions($json) as $tx) {
				$this->triggerTx($tx);
			}
		}

		if ($json['op'] === 'block') {
			$this->resolveBlock($json);
		}
	}

	private function sub(): void
	{
		try {
			$client = new Client($this->resolveStreamUri());
			$client
				->addMiddleware(new CloseHandler())
				->addMiddleware(new PingResponder());
			$client->setTimeout(10);
			$client->setPersistent(true);

			$client->onConnect(function ($client, $connection, $handshake) {
				$client->text('{"op": "unconfirmed_sub"}');
				$client->text('{"op": "blocks_sub"}');
			})->onDisconnect(function (Client $client, $connection) {
				echo "> [{$connection->getRemoteName()}] Server disconnected\n";
				$client->disconnect();
				$client->connect();
			})->onText(function ($client, $connection, $message) {
				$this->processIncomingMessage($message);
			})->onBinary(function ($client, $connection, $message) {
				echo "> [{$connection->getRemoteName()}] Received [{$message->getOpcode()}]\n";
			})->onPing(function ($client, $connection, $message) {
				$connection->pong($message->getPayload());
				echo "> [{$connection->getRemoteName()}] Received [{$message->getOpcode()}]\n";
			})->onPong(function ($client, $connection, $message) {
				echo "> [{$connection->getRemoteName()}] Received [{$message->getOpcode()}]\n";
			})->onClose(function ($client, $connection, $message) {
				echo "> [{$connection->getRemoteName()}] Received [{$message->getOpcode()}] {$message->getCloseStatus()}\n";
			})->onError(function ($client, $connection, $exception) {
				$name = $connection ? "[{$connection->getRemoteName()}]" : "[-]";
				echo "> {$name} Error: {$exception->getMessage()}\n";
			})->start();
		} catch (Throwable $e) {
			echo "> ERROR: {$e->getMessage()}\n";
		}
	}

	/**
	 * @param array $block
	 * @return void
	 */
	private function resolveBlock(array $block): void
	{
		$block = new IncomingBlock(
			$block['x']['height'],
			$block['x']['hash'],
			$block['x']['prevBlockIndex'],
			$block['x']['txIndexes'],
		);

		$this->triggerBlock($block);
	}

	/**
	 * @param array $tx
	 * @return IncomingTransaction[]
	 */
	private function getIncomingTransactions(array $tx): array
	{
		if ($tx['op'] !== 'utx') {
			return [];
		}
		if ($tx['data']['subscribed'] ?? false) {
			return [];
		}

		$result = [];
		foreach ($tx['x']['out'] as $out) {
			if (!isset($out['addr'])) {
				continue;
			}

			$result[] = new IncomingTransaction(
				$tx['x']['hash'], '', $out['addr'], Amount::satoshi($out['value']),
				null, null, $out['n'], 0, true
			);
		}

		return $result;
	}

	private function resolveStreamUri(): ?string
	{
		if ($this->blockbook->network instanceof Bitcoin) {
			// free blockchain.info socs
			return "wss://ws.blockchain.info/inv";
		}
		$domain = parse_url($this->blockbook->credentials->uri, PHP_URL_HOST);

		// nownodes wss
		return "wss://{$domain}/wss/{$this->blockbook->credentials->getHeaders()['api-key']}";
	}

	public function cancelSubscriptions(): StreamableInterface
	{
		// TODO: Implement cancelSubscriptions() method.
		return $this;
	}
}