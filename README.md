# Multi Crypto API
Universal PHP library for working with multiple cryptocurrency blockchains through a unified API interface.

## 🚀 Features

- **Multi-blockchain support**: Bitcoin, Litecoin, Dogecoin, Dash, Zcash, Ethereum, BSC, Tron
- **Unified API interface** for all supported blockchains
- **Transaction operations**: retrieval, sending, tracking
- **Token support**: ERC-20, TRC-20 and other standards
- **Real-time data streaming** (for EVM blockchains and Tron)
- **Fallback mechanisms**: automatic switching between data sources (Blockbook → RPC → Etherscan)
- **Flexible configuration**: customizable search parameters and limits

## 📦 Installation

```bash
composer require payex-id/multicryptoapi
```

## 🔧 Requirements

- PHP 8.0+
- `bcmath` extension
- API keys from providers (NowNodes, Etherscan, TronScan, etc.)

## 📚 Supported Blockchains

### UTXO-based blockchains
- **Bitcoin** (BTC)
- **Litecoin** (LTC)
- **Dogecoin** (DOGE)
- **Dash** (DASH)
- **Zcash** (ZEC)

### EVM-based blockchains
- **Ethereum** (ETH)
- **BNB Smart Chain** (BSC/BNB)

### Others
- **Tron** (TRX)

## 🎯 Quick Start

### Ethereum Example

```php
<?php

use Chikiday\MultiCryptoApi\Blockbook\EthereumBlockbook;
use Chikiday\MultiCryptoApi\Blockchain\RpcCredentials;

$blockbook = new EthereumBlockbook(
	new RpcCredentials(
		'https://eth.nownodes.io/YOUR_API_KEY',
		'https://eth-blockbook.nownodes.io',
		['api-key' => 'YOUR_API_KEY']
	)
);

// Get latest block
$block = $blockbook->getBlock();
echo "Block: {$block->height}\n";

// Get transaction
$tx = $blockbook->getTx('0x...');
echo "TX: {$tx->txid}\n";

// Get address
$addr = $blockbook->getAddress('0x...');
echo "Balance: {$addr->balance->toBtc()}\n";

// Get address transactions
$txs = $blockbook->getAddressTransactions('0x...');
foreach ($txs->transactions as $tx) {
	echo "TX: {$tx->txid}\n";
}
```

### Bitcoin Example

```php
<?php

use Chikiday\MultiCryptoApi\Factory\ApiFactory;
use Chikiday\MultiCryptoApi\Factory\ApiType;
use Chikiday\MultiCryptoApi\Blockchain\RpcCredentials;

$credentials = new RpcCredentials(
	'https://btc.nownodes.io/YOUR_API_KEY',
	'https://btc-blockbook.nownodes.io'
);

$api = ApiFactory::bitcoin($credentials);

// Create wallet
$wallet = $api->createWallet();
echo "Address: {$wallet->address}\n";
echo "Private Key: {$wallet->privateKey}\n";

// Send transaction
$tx = $api->sendCoins(
	$wallet,
	'recipient_address',
	'0.001'
);
echo "TX sent: {$tx->txid}\n";
```

## 🏗️ Architecture

The library is built on the following components:

### Blockbook
Interface for working with blockchain data:
- `getBlock()` - get block
- `getTx()` - get transaction
- `getAddress()` - get address information
- `getAddressTransactions()` - get address transaction list
- `getAssets()` - get address tokens
- `pushRawTransaction()` - send raw transaction

### API Client
High-level client for operations:
- `createWallet()` - create new wallet
- `createFromPrivateKey()` - create wallet from private key
- `sendCoins()` - send native coins
- `sendAsset()` - send tokens
- `validateAddress()` - validate address

### Stream
Real-time data streaming:
- Subscribe to new blocks
- Track transactions
- Handle events

> **⚠️ Note:** Streaming works for EVM blockchains (Ethereum, BSC) and Tron. For BTC-like blockchains (Bitcoin, Litecoin, Dogecoin, etc.) streaming is not implemented and has not been tested.

## ⚙️ Configuration

### Ethereum/BSC RPC Settings

For working with Ethereum/BSC via RPC, you can configure the following parameters:

```php
$blockbook = new EthereumRpc($credentials, [
	// ERC-20 transaction search depth (in blocks)
	'erc20Lookback' => 50000,
	
	// Window size for a single eth_getLogs request (in blocks)
	'erc20BlocksPerQuery' => 9500,
	
	// Search depth via trace_filter (in blocks)
	'traceLookback' => 20000,
	
	// Etherscan API key (for fallback)
	'etherscanApiKey' => 'YOUR_ETHERSCAN_API_KEY',
	
	// List of supported tokens
	'tokens' => [
		'0x...', // USDT
		'0x...', // USDC
	]
]);
```

### Fallback Mechanism

The library automatically switches between data sources:

1. **Etherscan API** (primary) - fast and paginated
2. **RPC eth_getLogs** (fallback for tokens) - when Etherscan is unavailable
3. **RPC trace_filter** (fallback for ETH transactions) - when Etherscan is unavailable

## 📖 Usage Examples

A complete set of examples is located in the `examples/` directory:

- `*-blockbook.php` - working with blockchain data
- `*-send-*.php` - sending transactions
- `*-stream.php` - stream processing
- `*-wallet.php` - wallet operations

## 🔑 API Keys

The library requires API keys from the following providers:

- **NowNodes** - https://nownodes.io/ (RPC and Blockbook)
- **Etherscan** - https://etherscan.io/ (for Ethereum/BSC)
- **TronScan** - https://tronscan.org/ (for Tron)
- **Infura** - https://infura.io/ (WebSocket connections)

Key configuration is described in `examples/README.md`.

## 🧪 Testing

```bash
# Run tests
vendor/bin/phpunit
```

## 📝 License

MIT License

## 👤 Author

**Chikiday**
- Telegram: @chikiday

## 🤝 Contributing

Pull Requests and Issues are welcome.

## 📚 Additional Documentation

- [Usage Examples](examples/README.md)
- [Factory API](src/Chikiday/MultiCryptoApi/Factory/)
- [Interfaces](src/Chikiday/MultiCryptoApi/Interface/)

## ⚠️ Important Notes

- When working with large block ranges (e.g., BSC), consider provider limits
- For production use, caching is recommended
- When working with private keys, follow security best practices
- Some functions require archive nodes
- **Data streaming does not work and has not been tested for BTC-like blockchains** (Bitcoin, Litecoin, Dogecoin, Dash, Zcash)

## 🔄 Changelog

### Recent Changes

- Added fallback mechanism for retrieving transactions via RPC
- Support for trace_filter for EVM blockchains
- Configurable search parameters for ERC-20 tokens
- Improved error handling and retry logic

