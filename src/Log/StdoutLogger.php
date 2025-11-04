<?php

namespace MultiCryptoApi\Log;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

class StdoutLogger implements LoggerInterface
{
	use LoggerTrait;

	#[\Override] public function log($level, \Stringable|string $message, array $context = []): void
	{
		$message = (string)$message;

		// build a replacement array with braces around the context keys
		$replace = [];
		foreach ($context as $key => $val) {
			// check that the value can be cast to string
			if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
				$replace['{' . $key . '}'] = $val;
			}
		}

		// interpolate replacement values into the message
		$message = strtr($message, $replace);

		echo $message . "\n";
	}

}