<?php

namespace MultiCryptoApi\Exception;

use Bundles\Wallet\CryptoApi\CryptoApiException;
use Bundles\Wallet\CryptoApi\UnPushedTx;
use MultiCryptoApi\Blockchain\RawTransaction;

class IncorrectTxException extends MultiCryptoApiException
{
	public readonly RawTransaction $tx;

	public static function factory(string $message, RawTransaction $tx): IncorrectTxException
	{
		$self = new self($message);
		$self->tx = $tx;

		return $self;
	}
}