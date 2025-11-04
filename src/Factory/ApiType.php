<?php

namespace MultiCryptoApi\Factory;

enum ApiType
{
	case Bitcoin;
	case Litecoin;
	case Dogecoin;
	case Dash;
	case Tron;
	case Zcash;

	case Ethereum;
}