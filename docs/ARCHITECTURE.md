# Architecture Guide

This document describes the architectural design, patterns, and principles used in the Multi Crypto API library.

## Table of Contents

- [Overview](#overview)
- [System Architecture](#system-architecture)
- [Design Patterns](#design-patterns)
- [Layer Architecture](#layer-architecture)
- [Data Flow](#data-flow)
- [Extension Points](#extension-points)
- [Best Practices](#best-practices)

## Overview

Multi Crypto API is built using a layered architecture that separates concerns and provides flexibility for adding new blockchain support. The architecture follows SOLID principles and uses established design patterns for maintainability and extensibility.

### Core Principles

1. **Separation of Concerns** - Each layer has a distinct responsibility
2. **Interface Segregation** - Small, focused interfaces for specific capabilities
3. **Dependency Inversion** - Depend on abstractions, not concrete implementations
4. **Open/Closed Principle** - Open for extension, closed for modification
5. **Single Responsibility** - Each class has one reason to change

## System Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                     Application Layer                        │
│                  (Your Application Code)                     │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ↓
┌─────────────────────────────────────────────────────────────┐
│                      API Layer                               │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │ BitcoinApi   │  │ EthereumApi  │  │   TronApi    │      │
│  │   Client     │  │   Client     │  │   Client     │      │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘      │
│         │                  │                  │              │
│         └──────────────────┼──────────────────┘              │
└────────────────────────────┼─────────────────────────────────┘
                             ↓
┌─────────────────────────────────────────────────────────────┐
│                   Provider Layer                             │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │   Bitcoin    │  │  Ethereum    │  │     TRON     │      │
│  │   Provider   │  │  Provider    │  │   Provider   │      │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘      │
└─────────┼──────────────────┼──────────────────┼─────────────┘
          │                  │                  │
          ↓                  ↓                  ↓
┌─────────────────────────────────────────────────────────────┐
│                  Blockchain Networks                         │
│       (JSON-RPC, REST APIs, WebSocket)                       │
└─────────────────────────────────────────────────────────────┘
```

## Design Patterns

### 1. Factory Pattern

The `ApiFactory` class provides a centralized way to create blockchain-specific API clients:

```php
// Factory creates the correct client type
$bitcoin = ApiFactory::bitcoin($credentials);
$ethereum = ApiFactory::ethereum($credentials);
$tron = ApiFactory::tron($credentials);
```

**Benefits:**
- Encapsulates object creation logic
- Single point of configuration
- Easy to add new blockchain types

**Implementation:**
```
Factory/
├── ApiFactory.php      # Main factory class
└── ApiType.php         # Enum of supported types
```

### 2. Strategy Pattern

Different blockchain implementations use different strategies for transaction building, address validation, and data retrieval:

```php
interface ApiClientInterface {
    public function sendCoins(AddressCredentials $from, string $to, string $amount): PushedTX;
}

// Bitcoin strategy
class BitcoinApiClient implements ApiClientInterface {
    // Uses UTXO-based transaction building
}

// Ethereum strategy
class EthereumApiClient implements ApiClientInterface {
    // Uses account-based transaction building
}
```

**Benefits:**
- Interchangeable algorithms
- Blockchain-specific optimizations
- Extensible without modifying existing code

### 3. Adapter Pattern

The Provider layer adapts various blockchain data sources (Blockbook API, JSON-RPC, REST) to a unified interface:

```php
interface ProviderInterface {
    public function getAddress(string $address): Address;
    public function getTx(string $txId): Transaction;
    public function pushRawTransaction(string $rawTx): string;
}

// Adapts Blockbook REST API
class BitcoinProvider implements ProviderInterface { }

// Adapts Ethereum JSON-RPC
class EthereumProvider implements ProviderInterface { }
```

**Benefits:**
- Unified interface for different data sources
- Easy to switch between providers
- Shields application from external API changes

### 4. Observer Pattern

The Stream layer implements the observer pattern for real-time blockchain events:

```php
$stream = $bitcoin->stream();

// Subscribe to events (observers)
$stream->subscribeToAddresses(['bc1...'], function($tx) {
    echo "New transaction: {$tx->txId}\n";
});

// Event loop notifies all observers
$stream->run();
```

**Benefits:**
- Decoupled event handling
- Multiple subscribers per event
- Asynchronous processing

### 5. Builder Pattern

Transaction builders construct complex transaction objects step by step:

```php
$builder = new BitcoinTxBuilder($provider);
$rawTx = $builder
    ->setFrom($wallet)
    ->addOutput($recipient, $amount)
    ->selectUTXOs()
    ->calculateFee()
    ->sign()
    ->build();
```

**Benefits:**
- Fluent interface
- Step-by-step construction
- Validation at each step

## Layer Architecture

### 1. API Layer (`Api/`)

**Responsibility:** High-level blockchain operations

**Components:**
- `BitcoinApiClient` - Bitcoin operations
- `EthereumApiClient` - Ethereum/EVM operations
- `TronApiClient` - TRON operations
- `Builder/` - Transaction builders

**Key Methods:**
```php
interface ApiClientInterface {
    public function createWallet(): AddressCredentials;
    public function createFromPrivateKey(string $key): AddressCredentials;
    public function sendCoins(AddressCredentials $from, string $to, string $amount): PushedTX;
    public function validateAddress(string $address): bool;
    public function provider(): ProviderInterface;
    public function stream(): StreamableInterface;
}
```

**Dependencies:**
- Uses Provider layer for blockchain data
- Uses Builder for transaction construction
- Uses Stream for real-time monitoring

### 2. Provider Layer (`Provider/`)

**Responsibility:** Low-level blockchain data access

**Components:**
- `BitcoinProvider` - Bitcoin Blockbook/RPC
- `EthereumProvider` - Ethereum RPC
- `EthereumRpc` - Alternative Ethereum implementation
- `TrxProvider` - TRON Grid API
- `Abstract/ProviderAbstract` - Base class

**Key Methods:**
```php
interface ProviderInterface {
    public function getBlock(string $hashOrHeight): Block;
    public function getTx(string $txId): Transaction;
    public function getAddress(string $address): Address;
    public function getAddressTransactions(string $address, int $page, int $pageSize): array;
    public function getUTXO(string $address): array;
    public function pushRawTransaction(string $rawTx): string;
}
```

**Data Sources:**
- Blockbook REST API
- JSON-RPC nodes
- TRON Grid API
- TronScan API

### 3. Stream Layer (`Stream/`)

**Responsibility:** Real-time blockchain event monitoring

**Components:**
- `BitcoinStream` - WebSocket for Bitcoin
- `EthereumStream` - WebSocket for Ethereum
- `TronStream` - Polling for TRON
- `Abstract/AbstractStream` - Base streaming logic

**Key Methods:**
```php
interface StreamableInterface {
    public function subscribeToAddresses(array $addresses, callable $callback): void;
    public function subscribeToAnyTransaction(callable $callback): void;
    public function subscribeToAnyBlock(callable $callback): void;
    public function run(): void;
}
```

**Technologies:**
- ReactPHP Event Loop (async I/O)
- WebSocket (Bitcoin, Ethereum)
- Polling (TRON)

### 4. Model Layer (`Model/`)

**Responsibility:** Data structures and domain objects

**Core Models:**

```php
// Blockchain primitives
class Transaction { /* txId, inputs, outputs, fee, confirmations */ }
class Address { /* address, balance, tokens, transactions */ }
class Block { /* height, hash, time, transactions */ }
class Amount { /* value, decimals, conversion methods */ }
class Asset { /* contract, name, symbol, decimals, balance */ }
class UTXO { /* txId, vout, value, address */ }

// Credentials
class RpcCredentials { /* uri, blockbookUri, headers, auth */ }
class AddressCredentials { /* address, privateKey */ }

// Real-time events
class IncomingTransaction { /* txId, amount, from, to, direction */ }
class IncomingBlock { /* height, hash, time */ }
```

**Enums:**
```php
enum TransactionDirection { Incoming, Outgoing, Self }
```

### 5. Factory Layer (`Factory/`)

**Responsibility:** Object creation and configuration

```php
class ApiFactory {
    public static function factory(ApiType $type, RpcCredentials $credentials): ApiClientInterface;

    // Convenience methods
    public static function bitcoin(RpcCredentials $credentials): BitcoinApiClient;
    public static function ethereum(RpcCredentials $credentials): EthereumApiClient;
    public static function tron(RpcCredentials $credentials): TronApiClient;
}

enum ApiType {
    case Bitcoin;
    case Litecoin;
    case Dogecoin;
    case Dash;
    case Zcash;
    case Ethereum;
    case Tron;
}
```

### 6. Contract Layer (`Contract/`)

**Responsibility:** Interface definitions

**Core Interfaces:**

```php
// Main API contract
interface ApiClientInterface { /* createWallet, sendCoins, validate */ }

// Provider contract
interface ProviderInterface { /* getBlock, getTx, getAddress, push */ }

// Capability interfaces
interface TokenAwareInterface { /* getAssets, getTokenInfo */ }
interface EvmLikeInterface { /* evmCall, estimateGas, getChainId */ }
interface StreamableInterface { /* subscribe methods, run */ }
interface ManyInputsInterface { /* sendMany */ }
interface ResourceRentableInterface { /* delegateResource (TRON) */ }
interface AddressActivator { /* activateAddress (TRON) */ }
interface UnconfirmedBalanceFeatureInterface { /* getUnconfirmedBalance */ }

// Utility interfaces
interface BlockchainDataResolver { /* resolveAssets, resolveFee */ }
interface TransactionDecoratorInterface { /* getDirection, isRelated */ }
```

**Design Philosophy:**
- Small, focused interfaces (Interface Segregation Principle)
- Capability-based (not all clients implement all interfaces)
- Easy to test and mock

### 7. Exception Layer (`Exception/`)

**Responsibility:** Error handling

```php
class MultiCryptoApiException extends \Exception { /* Base exception */ }
class NotEnoughFundsException extends MultiCryptoApiException { /* Insufficient balance */ }
class NotEnoughFundsToCoverFeeException extends MultiCryptoApiException { /* Fee exceeds balance */ }
class IncorrectTxException extends MultiCryptoApiException { /* Invalid transaction */ }
```

### 8. Utility Layer (`Util/`)

**Responsibility:** Helper functions and utilities

```php
class EthUtil {
    public static function toWei(string $value, int $decimals = 18): string;
    public static function fromWei(string $value, int $decimals = 18): string;
    public static function parseTokenTransfer(string $data): array;
}

class Throttler {
    public function throttle(callable $callback, float $interval): void;
}
```

## Data Flow

### Querying Blockchain Data

```
User Code
   ↓
ApiClient::provider()
   ↓
Provider::getAddress($address)
   ↓
HTTP/RPC Call to Blockchain
   ↓
Provider parses response
   ↓
Address Model returned
   ↓
User Code receives Address
```

### Sending Transaction

```
User Code
   ↓
ApiClient::sendCoins($from, $to, $amount)
   ↓
Builder::build()
   ├→ Provider::getUTXO() or Provider::getAddress()
   ├→ Builder::selectUTXOs() or Builder::calculateNonce()
   ├→ Builder::calculateFee()
   ├→ Builder::sign($privateKey)
   └→ Builder::serialize()
   ↓
Provider::pushRawTransaction($rawTx)
   ↓
HTTP/RPC Call to Blockchain
   ↓
PushedTX returned (txId)
   ↓
User Code receives PushedTX
```

### Real-Time Monitoring

```
User Code
   ↓
ApiClient::stream()->subscribeToAddresses($addresses, $callback)
   ↓
Stream establishes WebSocket/Polling
   ↓
ReactPHP Event Loop
   ↓
Stream receives blockchain events
   ↓
Stream parses and filters events
   ↓
Callback invoked with IncomingTransaction/IncomingBlock
   ↓
User Code handles event
```

## Extension Points

### Adding a New Blockchain

1. **Create API Client** (`Api/NewChainApiClient.php`):
```php
class NewChainApiClient implements ApiClientInterface {
    public function __construct(private ProviderInterface $provider) {}

    // Implement required methods
}
```

2. **Create Provider** (`Provider/NewChainProvider.php`):
```php
class NewChainProvider extends ProviderAbstract implements ProviderInterface {
    // Implement blockchain-specific data access
}
```

3. **Create Transaction Builder** (`Api/Builder/NewChainTxBuilder.php`):
```php
class NewChainTxBuilder {
    public function build(/* params */): string {
        // Build and sign transactions
    }
}
```

4. **Optional: Create Stream** (`Stream/NewChainStream.php`):
```php
class NewChainStream extends AbstractStream {
    // Implement real-time monitoring
}
```

5. **Update Factory** (`Factory/ApiFactory.php` and `Factory/ApiType.php`):
```php
enum ApiType {
    // ... existing
    case NewChain;
}

class ApiFactory {
    public static function factory(ApiType $type, RpcCredentials $credentials): ApiClientInterface {
        return match($type) {
            // ... existing
            ApiType::NewChain => new NewChainApiClient(new NewChainProvider($credentials)),
        };
    }
}
```

### Adding New Capabilities

Use interface segregation to add optional features:

```php
interface NewFeatureInterface {
    public function newFeature(): Result;
}

class BitcoinApiClient implements ApiClientInterface, NewFeatureInterface {
    public function newFeature(): Result {
        // Implementation
    }
}

// Check at runtime
if ($client instanceof NewFeatureInterface) {
    $result = $client->newFeature();
}
```

## Best Practices

### 1. Use Type Hints

```php
// Good
public function getAddress(string $address): Address

// Bad
public function getAddress($address)
```

### 2. Use Dependency Injection

```php
// Good
public function __construct(private ProviderInterface $provider) {}

// Bad
public function __construct() {
    $this->provider = new BitcoinProvider();
}
```

### 3. Use Value Objects for Data

```php
// Good
$amount = new Amount('0.5', 8); // Value object
echo $amount->toBtc();

// Bad
$amount = 50000000; // Integer satoshis
```

### 4. Handle Errors with Exceptions

```php
// Good
if ($balance < $amount) {
    throw new NotEnoughFundsException();
}

// Bad
if ($balance < $amount) {
    return false;
}
```

### 5. Use PSR-3 Logging

```php
// Good
$this->logger->info('Transaction sent', ['txId' => $tx->txId]);

// Bad
echo "Transaction sent: {$tx->txId}\n";
```

### 6. Precision Arithmetic

```php
// Good
bcadd($value1, $value2, 8)

// Bad
$value1 + $value2 // Float arithmetic loses precision
```

### 7. Async/Event-Driven for Streams

```php
// Good - non-blocking
$stream->subscribeToAddresses($addresses, $callback);
$loop->run();

// Bad - blocking
while (true) {
    $tx = $api->pollTransaction();
}
```

## Testing Strategy

### Unit Tests

Test individual classes in isolation:

```php
class AmountTest extends TestCase {
    public function testConversion() {
        $amount = new Amount('100000000', 8);
        $this->assertEquals('1.00000000', $amount->toBtc());
    }
}
```

### Integration Tests

Test API clients with real providers:

```php
class BitcoinApiClientTest extends TestCase {
    public function testGetAddress() {
        $api = ApiFactory::bitcoin($credentials);
        $address = $api->provider()->getAddress('bc1...');
        $this->assertInstanceOf(Address::class, $address);
    }
}
```

### Mock External Dependencies

```php
$mockProvider = $this->createMock(ProviderInterface::class);
$mockProvider->method('getAddress')->willReturn($mockAddress);

$client = new BitcoinApiClient($mockProvider);
```

## Performance Considerations

1. **Connection Pooling** - Reuse HTTP clients when possible
2. **Caching** - Cache token metadata, gas prices, and block data
3. **Batch Requests** - Use batch JSON-RPC when supported
4. **Async Operations** - Use ReactPHP for concurrent requests
5. **Rate Limiting** - Implement throttling for API limits

## Security Considerations

1. **Private Key Handling** - Never log or expose private keys
2. **Input Validation** - Validate addresses, amounts, and transaction data
3. **HTTPS Only** - Always use encrypted connections
4. **Fee Validation** - Check fees before broadcasting
5. **Nonce Management** - Prevent transaction replay
6. **Rate Limiting** - Protect against DDoS

## Conclusion

The Multi Crypto API architecture provides a solid foundation for blockchain integration with:
- Clear separation of concerns
- Extensibility for new blockchains
- Type safety and error handling
- Real-time monitoring capabilities
- Production-ready patterns and practices

For more information, see:
- [API Reference](API_REFERENCE.md)
- [Examples](EXAMPLES.md)
- [Contributing Guide](../CONTRIBUTING.md)
