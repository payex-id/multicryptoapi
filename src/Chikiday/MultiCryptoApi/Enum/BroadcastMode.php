<?php

namespace Chikiday\MultiCryptoApi\Enum;

enum BroadcastMode: string
{
	case Blockbook = 'blockbook';
	case Rpc = 'rpc';
	case RpcWithFallback = 'rpc_with_fallback';
}
