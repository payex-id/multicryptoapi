<?php

namespace MultiCryptoApi\Factory;

use BitWasp\Bitcoin\Network\NetworkFactory;
use BitWasp\Bitcoin\Network\NetworkInterface;
use MultiCryptoApi\Api\BitcoinApiClient;
use MultiCryptoApi\Api\EthereumApiClient;
use MultiCryptoApi\Api\TronApiClient;
use MultiCryptoApi\Provider\BitcoinBlockbook;
use MultiCryptoApi\Provider\EthereumBlockbook;
use MultiCryptoApi\Provider\TrxBlockbook;
use MultiCryptoApi\Blockchain\RpcCredentials;
use MultiCryptoApi\Contract\ApiClientInterface;

class ApiFactory
{
	public static function factory(ApiType $type, RpcCredentials $credentials): ApiClientInterface
	{
		return match ($type) {
			ApiType::Bitcoin => self::bitcoin($credentials),
			ApiType::Litecoin => self::litecoin($credentials),
			ApiType::Dogecoin => self::doge($credentials),
			ApiType::Dash => self::dash($credentials),
			ApiType::Tron => self::tron($credentials),
			ApiType::Zcash => self::zcash($credentials),
			ApiType::Ethereum => self::ethereum($credentials),
		};
	}

	public static function bitcoin(RpcCredentials $credentials): BitcoinApiClient
	{
		return self::bitcoinLike(NetworkFactory::bitcoin(), $credentials);
	}

	private static function bitcoinLike(NetworkInterface $network, RpcCredentials $credentials): BitcoinApiClient
	{
		return new BitcoinApiClient(
			new BitcoinBlockbook($credentials, $network)
		);
	}

	public static function litecoin(RpcCredentials $credentials): BitcoinApiClient
	{
		return self::bitcoinLike(NetworkFactory::litecoin(), $credentials);
	}

	public static function doge(RpcCredentials $credentials): BitcoinApiClient
	{
		return self::bitcoinLike(NetworkFactory::dogecoin(), $credentials);
	}

	public static function dash(RpcCredentials $credentials): BitcoinApiClient
	{
		return self::bitcoinLike(NetworkFactory::dash(), $credentials);
	}

	public static function tron(RpcCredentials $credentials): TronApiClient
	{
		return new TronApiClient(new TrxBlockbook($credentials));
	}

	public static function zcash(RpcCredentials $credentials): BitcoinApiClient
	{
		return self::bitcoinLike(NetworkFactory::zcash(), $credentials);
	}

	public static function ethereum(RpcCredentials $credentials): EthereumApiClient
	{
		return new EthereumApiClient(new EthereumBlockbook($credentials));
	}
}