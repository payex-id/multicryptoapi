<?php

namespace MultiCryptoApi\Contract;


use Psr\Log\LoggerAwareInterface;
use React\EventLoop\LoopInterface;

interface StreamableInterface extends LoggerAwareInterface
{

	public function updateSubscribedAddress(array $addresses): self;

	public function subscribeToAddresses(array $addresses, \Closure $callback): self;

	public function subscribeToAnyBlock(\Closure $callback): self;

	public function subscribeToAnyTransaction(\Closure $callback): self;

	public function reConsiderBlock(int $blockNumber, \Closure $callback): self;

	public function cancelSubscriptions(): self;

	public function setLoop(LoopInterface $loop): self;
}