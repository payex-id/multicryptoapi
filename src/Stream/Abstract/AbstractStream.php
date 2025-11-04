<?php

namespace MultiCryptoApi\Stream\Abstract;

use MultiCryptoApi\Contract\StreamableInterface;
use MultiCryptoApi\Model\IncomingBlock;
use MultiCryptoApi\Model\IncomingTransaction;
use Closure;
use Override;
use Psr\Log\LoggerAwareTrait;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use RuntimeException;

abstract class AbstractStream implements StreamableInterface
{

	public bool $debug = false;
	protected Closure $txCallback;
	protected Closure $blockCallback;
	protected Closure $addressCallback;
	protected array $addresses = [];
	protected LoopInterface $loop;

	use LoggerAwareTrait;

	#[Override] public function reConsiderBlock(int $blockNumber, Closure $callback): StreamableInterface
	{
		throw new RuntimeException("Not yet implemented");
	}

	#[Override] public function updateSubscribedAddress(array $addresses): StreamableInterface
	{
		$this->addresses = $addresses;

		return $this;
	}

	#[Override] public function subscribeToAddresses(array $addresses, Closure $callback): self
	{
		$this->addressCallback = $callback;
		$this->updateSubscribedAddress($addresses);

		$this->start();

		return $this;
	}

	#[Override] public function subscribeToAnyBlock(Closure $callback): self
	{
		$this->blockCallback = $callback;

		$this->start();

		return $this;
	}

	#[Override] public function subscribeToAnyTransaction(Closure $callback): self
	{
		$this->txCallback = $callback;

		$this->start();

		return $this;
	}

	abstract protected function start(): void;

	protected function triggerTx(IncomingTransaction $incomingTransaction, bool $fromBlock = false): void
	{
		if (isset($this->txCallback)) {
			call_user_func($this->txCallback, $incomingTransaction, $fromBlock);
		}

		if (in_array($incomingTransaction->to, $this->addresses)) {
			call_user_func($this->addressCallback, $incomingTransaction, $fromBlock);
		}
	}

	protected function triggerBlock(IncomingBlock $block): void
	{
		if (!isset($this->blockCallback)) {
			return;
		}

		call_user_func($this->blockCallback, $block);
	}

	protected function debug(string $message): void
	{
		$this->logger?->debug($message);
		if ($this->debug && empty($this->logger)) {
			echo $message . "\n";
		}
	}

	protected function info(string $message): void
	{
		$this->logger?->info($message);
		if ($this->debug && empty($this->logger)) {
			echo $message . "\n";
		}
	}

	protected function getLoop(): LoopInterface
	{
		return $this->loop ??= Loop::get();
	}

	#[Override] public function setLoop(LoopInterface $loop): StreamableInterface
	{
		$this->loop = $loop;

		return $this;
	}

}