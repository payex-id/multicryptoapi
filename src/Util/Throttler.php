<?php

namespace MultiCryptoApi\Util;

class Throttler
{
	private array $currentRps = [];

	public function execute(callable $fn, int $maxRps): mixed
	{
		$time = time();

		$this->currentRps[$time] ??= 0;
		$this->currentRps[$time]++;

		$this->waitIfNecessary($maxRps);
//		echo "Current RPS: ". $this->currentRps[$time]. "\n";

		$result = $fn();

		// store last 600 seconds
		$this->currentRps = array_slice($this->currentRps, -600, 600, true);

		return $result;
	}

	public function getRpsHistory(): array
	{
		return $this->currentRps;
	}

	private function waitIfNecessary(int $maxRps): void
	{
		if ($this->currentRps[time()] >= $maxRps) {
			$waitTime = 1 - explode(" ", microtime())[0];

//			echo "Waiting for {$waitTime} seconds...". PHP_EOL;
			usleep(round($waitTime) * 1000000);
		}
	}
}