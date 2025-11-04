# Multi Crypto API

A comprehensive PHP library providing unified access to multiple blockchain networks through a standardized API interface. Query blockchain data, build and sign transactions, send coins and tokens, and monitor real-time blockchain events across Bitcoin, Ethereum, TRON, and more.

## Features

- **Unified API Interface** - Single, consistent API for multiple blockchains
- **Multi-Chain Support** - Bitcoin, Ethereum, BSC, TRON, Litecoin, Dogecoin, Dash, Zcash
- **Token Operations** - Full support for ERC20, TRC20, and TRC10 tokens
- **Transaction Building** - Build, sign, and broadcast transactions offline
- **Real-Time Monitoring** - WebSocket and polling-based event streams
- **Precision Arithmetic** - BC Math for accurate handling of satoshis and wei
- **Production Ready** - Comprehensive error handling and PSR-3 logging support

## Requirements

- PHP 8.1 or higher
- BC Math extension (`ext-bcmath`)
- Composer for dependency management

## Installation

Install via Composer:

```bash
composer require payex-id/multicryptoapi
```

## Quick Start

### Bitcoin Example

```php
<?php
require 'vendor/autoload.php';

use MultiCryptoApi\Factory\ApiFactory;
use MultiCryptoApi\Model\RpcCredentials;

// Configure API connection
$credentials = new RpcCredentials(
    uri: 'https://btc.nownodes.io/',
    blockbookUri: 'https://btcbook.nownodes.io',
    headers: ['api-key' => 'your-api-key']
);

// Create Bitcoin API client
$bitcoin = ApiFactory::bitcoin($credentials);

// Query address balance
$address = $bitcoin->blockbook()->getAddress('bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh');
echo "Balance: " . $address->balance->toBtc() . " BTC\n";

// Send transaction
$wallet = $bitcoin->createFromPrivateKey('your-private-key-wif');
$tx = $bitcoin->sendCoins(
    from: $wallet,
    to: 'bc1recipient...',
    amount: '0.001'
);
echo "Transaction sent: " . $tx->txId . "\n";
```

### Ethereum Example

```php
use MultiCryptoApi\Factory\ApiFactory;

// Create Ethereum API client
$ethereum = ApiFactory::ethereum($credentials);

// Query address and tokens
$address = $ethereum->blockbook()->getAddress('0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb');
echo "ETH Balance: " . $address->balance->toEth() . "\n";

foreach ($address->tokens as $token) {
    echo "Token: {$token->name} = {$token->amount}\n";
}

// Send ERC20 token
$wallet = $ethereum->createFromPrivateKey('your-private-key-hex');
$tx = $ethereum->sendAsset(
    from: $wallet,
    to: '0xrecipient...',
    assetId: '0xTokenContractAddress',
    amount: '100.50'
);
```

### TRON Example

```php
use MultiCryptoApi\Factory\ApiFactory;

// Create TRON API client
$tron = ApiFactory::tron($credentials);

// Send USDT (TRC20)
$wallet = $tron->createFromPrivateKey('your-private-key-hex');
$tx = $tron->sendAsset(
    from: $wallet,
    to: 'TRecipientAddress...',
    assetId: 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t', // USDT contract
    amount: '50.00'
);
```

### Real-Time Monitoring

```php
// Monitor Bitcoin address for incoming transactions
$bitcoin->stream()->subscribeToAddresses(
    addresses: ['bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh'],
    callback: function($tx) {
        echo "Received {$tx->amount} BTC in transaction {$tx->txId}\n";
    }
);

// Monitor Ethereum blocks
$ethereum->stream()->subscribeToAnyBlock(
    callback: function($block) {
        echo "New block: {$block->height}\n";
    }
);
```

## Supported Blockchains

| Blockchain | Support Level | Features |
|-----------|--------------|----------|
| **Bitcoin** | Full | UTXO selection, SegWit, WebSocket streams, Testnet |
| **Ethereum** | Full | ERC20/ERC721, EIP-1559, Gas estimation, WebSocket |
| **BSC** | Full | Full Ethereum compatibility (EVM) |
| **TRON** | Full | TRC20/TRC10, Resource delegation, Account activation |
| **Litecoin** | Good | Same UTXO model as Bitcoin |
| **Dogecoin** | Good | Same UTXO model as Bitcoin |
| **Dash** | Good | Same UTXO model as Bitcoin |
| **Zcash** | Good | Same UTXO model as Bitcoin |

## Architecture

The library is organized into several layers:

```
src/
├── Api/              # High-level API clients (BitcoinApiClient, EthereumApiClient, etc.)
├── Provider/         # Blockchain data providers (query blocks, transactions, addresses)
├── Stream/           # Real-time event monitoring (WebSocket and polling)
├── Model/            # Data models (Transaction, Address, Block, Amount, etc.)
├── Factory/          # Factory pattern for creating API clients
├── Contract/         # Interfaces defining capabilities
├── Exception/        # Custom exception types
├── Util/             # Utility classes
└── Log/              # PSR-3 logging implementation
```

### Key Design Patterns

- **Factory Pattern** - `ApiFactory` creates blockchain-specific clients
- **Strategy Pattern** - Different implementations per blockchain
- **Adapter Pattern** - Unified interface over various data sources
- **Observer Pattern** - Event streams for real-time monitoring

## Documentation

- **[Architecture Guide](docs/ARCHITECTURE.md)** - System design and architectural patterns
- **[API Reference](docs/API_REFERENCE.md)** - Complete API documentation
- **[Examples](docs/EXAMPLES.md)** - Comprehensive usage examples and tutorials
- **[Contributing](CONTRIBUTING.md)** - Development guidelines

## API Credentials

This library works with blockchain node providers. Popular options:

- **NowNodes** - https://nownodes.io/ (Bitcoin, Ethereum, etc.)
- **Infura** - https://infura.io/ (Ethereum WebSocket)
- **TronGrid** - https://www.trongrid.io/ (TRON)
- **TronScan** - https://tronscan.org/ (TRON)

Set your API keys as environment variables:

```bash
export NODES_API_KEY="your-nownodes-key"
export INFURA_API_KEY="your-infura-key"
export TRONGRID_API_KEY="your-trongrid-key"
export TRONSCAN_API_KEY="your-tronscan-key"
```

## Examples

The `/examples` directory contains 42+ working examples:

```bash
# Query blockchain data
php examples/bitcoin-blockbook.php
php examples/eth-blockbook.php
php examples/trx-blockbook.php

# Send transactions
php examples/bitcoin-send-tx-testnet.php
php examples/eth-send-tx-testnet.php
php examples/trx-send-usdt.php

# Real-time monitoring
php examples/bitcoin-stream.php
php examples/eth-stream.php
php examples/trx-stream.php
```

## Testing

Run the test suite:

```bash
composer install --dev
./vendor/bin/phpunit
```

## License

This project is licensed under the MIT License. See [LICENSE](LICENSE) file for details.

## Contributing

Contributions are welcome! Please read [CONTRIBUTING.md](CONTRIBUTING.md) for details on our code of conduct and development process.

## Support

- **Issues**: [GitHub Issues](https://github.com/payex-id/multicryptoapi/issues)
- **Documentation**: See `/docs` directory
- **Examples**: See `/examples` directory

## Security

For security vulnerabilities, please email security@payex.id instead of using the issue tracker.

## Credits

Developed and maintained by the PayEx team.

### Dependencies

- [bitwasp/bitcoin-php](https://github.com/bitwasp/bitcoin-php) - Bitcoin transaction handling
- [kornrunner/ethereum-offline-raw-tx](https://github.com/kornrunner/ethereum-offline-raw-tx) - Ethereum transaction signing
- [ReactPHP](https://reactphp.org/) - Async event-driven architecture
- [JsonRPC](https://github.com/fguillot/JsonRPC) - JSON-RPC client

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history and release notes.
