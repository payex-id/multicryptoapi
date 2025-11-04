# API Reference

Complete API documentation for Multi Crypto API library.

## Table of Contents

- [Factory](#factory)
- [API Clients](#api-clients)
- [Provider Layer](#provider-layer)
- [Models](#models)
- [Streams](#streams)
- [Exceptions](#exceptions)
- [Utilities](#utilities)

---

## Factory

### ApiFactory

Factory class for creating blockchain API clients.

#### Methods

##### `factory(ApiType $type, RpcCredentials $credentials): ApiClientInterface`

Creates an API client for the specified blockchain type.

**Parameters:**
- `$type` - Blockchain type enum
- `$credentials` - RPC connection credentials

**Returns:** API client instance

**Example:**
```php
use MultiCryptoApi\Factory\ApiFactory;
use MultiCryptoApi\Factory\ApiType;

$client = ApiFactory::factory(ApiType::Bitcoin, $credentials);
```

##### `bitcoin(RpcCredentials $credentials): BitcoinApiClient`

Creates a Bitcoin API client.

**Example:**
```php
$bitcoin = ApiFactory::bitcoin($credentials);
```

##### `ethereum(RpcCredentials $credentials): EthereumApiClient`

Creates an Ethereum API client.

##### `tron(RpcCredentials $credentials): TronApiClient`

Creates a TRON API client.

##### `litecoin(RpcCredentials $credentials): BitcoinApiClient`

Creates a Litecoin API client.

##### `dogecoin(RpcCredentials $credentials): BitcoinApiClient`

Creates a Dogecoin API client.

##### `dash(RpcCredentials $credentials): BitcoinApiClient`

Creates a Dash API client.

##### `zcash(RpcCredentials $credentials): BitcoinApiClient`

Creates a Zcash API client.

---

## API Clients

All API clients implement `ApiClientInterface`.

### Common Methods (All Clients)

#### `provider(): ProviderInterface`

Returns the blockchain data provider.

**Returns:** Provider instance

**Example:**
```php
$provider = $client->provider();
$address = $provider->getAddress('address...');
```

#### `stream(): ?StreamableInterface`

Returns the real-time event stream (if supported).

**Returns:** Stream instance or null

**Example:**
```php
$stream = $client->stream();
$stream->subscribeToAddresses(['address...'], $callback);
```

#### `createWallet(): AddressCredentials`

Generates a new wallet with address and private key.

**Returns:** `AddressCredentials` with address and private key

**Example:**
```php
$wallet = $client->createWallet();
echo "Address: {$wallet->address}\n";
echo "Private Key: {$wallet->privateKey}\n";
```

**Security Warning:** Store private keys securely. Never expose them in logs or UI.

#### `createFromPrivateKey(string $privateKey): AddressCredentials`

Imports a wallet from an existing private key.

**Parameters:**
- `$privateKey` - Private key (format depends on blockchain)

**Returns:** `AddressCredentials`

**Example:**
```php
// Bitcoin: WIF format
$wallet = $bitcoin->createFromPrivateKey('L1aW4aubDFB7yfras2S1mN3bqg9nwySY8nkoLmJebSLD5BWv3ENZ');

// Ethereum: Hex format (with or without 0x prefix)
$wallet = $ethereum->createFromPrivateKey('0x4c0883a69102937d6231471b5dbb6204fe512961708279f8f640c5f09e28e57a');
```

#### `sendCoins(AddressCredentials $from, string $addressTo, string $amount, ?Fee $fee = null): Transaction`

Sends native coins (BTC, ETH, TRX, etc.).

**Parameters:**
- `$from` - Source wallet credentials
- `$addressTo` - Recipient address
- `$amount` - Amount to send (in whole units: "0.5" BTC, not satoshis)
- `$fee` - Optional custom fee

**Returns:** `Transaction` object with txId

**Example:**
```php
$wallet = $client->createFromPrivateKey($privateKey);
$tx = $client->sendCoins(
    from: $wallet,
    addressTo: 'recipient-address',
    amount: '0.5'
);
echo "Transaction ID: {$tx->txId}\n";
```

**Throws:**
- `NotEnoughFundsException` - Insufficient balance
- `NotEnoughFundsToCoverFeeException` - Balance can't cover fee
- `IncorrectTxException` - Invalid transaction

#### `sendAsset(AddressCredentials $from, string $assetId, string $addressTo, string $amount, ?int $decimals = null, ?Fee $fee = null): Transaction`

Sends tokens (ERC20, TRC20, etc.).

**Parameters:**
- `$from` - Source wallet credentials
- `$assetId` - Token contract address
- `$addressTo` - Recipient address
- `$amount` - Amount to send (in token units)
- `$decimals` - Token decimals (auto-detected if null)
- `$fee` - Optional custom fee

**Returns:** `Transaction` object with txId

**Example:**
```php
// Send USDT on Ethereum
$tx = $ethereum->sendAsset(
    from: $wallet,
    assetId: '0xdAC17F958D2ee523a2206206994597C13D831ec7', // USDT contract
    addressTo: '0xrecipient...',
    amount: '100.50'
);

// Send USDT on TRON
$tx = $tron->sendAsset(
    from: $wallet,
    assetId: 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t', // USDT TRC20
    addressTo: 'TRecipient...',
    amount: '100.50'
);
```

#### `validateAddress(string $address): bool`

Validates an address format.

**Parameters:**
- `$address` - Address to validate

**Returns:** `true` if valid, `false` otherwise

**Example:**
```php
if ($client->validateAddress('bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh')) {
    echo "Valid address";
}
```

---

### BitcoinApiClient

Extends common methods with Bitcoin-specific features.

#### Implements
- `ApiClientInterface`
- `ManyInputsInterface`

#### Additional Methods

##### `sendMany(array $inputs, array $outputs, ?Fee $fee = null): Transaction`

Sends Bitcoin from multiple inputs to multiple outputs.

**Parameters:**
- `$inputs` - Array of `['address' => 'privateKey']`
- `$outputs` - Array of `['address' => 'amount']`
- `$fee` - Optional custom fee

**Returns:** `Transaction`

**Example:**
```php
$tx = $bitcoin->sendMany(
    inputs: [
        'bc1input1...' => 'privateKey1',
        'bc1input2...' => 'privateKey2',
    ],
    outputs: [
        'bc1output1...' => '0.1',
        'bc1output2...' => '0.2',
    ]
);
```

---

### EthereumApiClient

Extends common methods with Ethereum-specific features.

#### Implements
- `ApiClientInterface`
- `EvmLikeInterface`

#### Additional Methods

##### `evmCall(string $to, string $data): string`

Calls an EVM smart contract (read-only).

**Parameters:**
- `$to` - Contract address
- `$data` - Encoded function call data

**Returns:** Raw result data

**Example:**
```php
// Call balanceOf(address) on ERC20
$data = '0x70a08231' . str_pad(substr($address, 2), 64, '0', STR_PAD_LEFT);
$result = $ethereum->evmCall('0xTokenContract...', $data);
```

##### `estimateGas(string $from, string $to, string $value, string $data = ''): string`

Estimates gas required for a transaction.

**Parameters:**
- `$from` - Sender address
- `$to` - Recipient address
- `$value` - Value in wei (hex)
- `$data` - Transaction data

**Returns:** Estimated gas as hex string

##### `getChainId(): int`

Returns the chain ID (1 for mainnet, 5 for Goerli, etc.).

---

### TronApiClient

Extends common methods with TRON-specific features.

#### Implements
- `ApiClientInterface`
- `ResourceRentableInterface`
- `AddressActivator`

#### Additional Methods

##### `delegateResource(AddressCredentials $from, string $receiverAddress, string $balance, string $resource = 'BANDWIDTH', bool $lock = false): Transaction`

Delegates bandwidth or energy to another address.

**Parameters:**
- `$from` - Delegator wallet
- `$receiverAddress` - Receiver address
- `$balance` - Amount of TRX to stake (in TRX, e.g., "100")
- `$resource` - 'BANDWIDTH' or 'ENERGY'
- `$lock` - Whether to lock the stake

**Returns:** `Transaction`

**Example:**
```php
$tx = $tron->delegateResource(
    from: $wallet,
    receiverAddress: 'TReceiver...',
    balance: '100',
    resource: 'ENERGY'
);
```

##### `activateAddress(AddressCredentials $from, string $addressToActivate): Transaction`

Activates a new TRON address (required before first use).

**Parameters:**
- `$from` - Wallet to pay activation fee
- `$addressToActivate` - New address to activate

**Returns:** `Transaction`

**Example:**
```php
$tx = $tron->activateAddress($wallet, 'TNewAddress...');
```

##### `isAddressActive(string $address): bool`

Checks if a TRON address is activated.

**Parameters:**
- `$address` - Address to check

**Returns:** `true` if active, `false` otherwise

---

## Provider Layer

All providers implement `ProviderInterface`.

### Common Methods (All Providers)

#### `getOption(string $key): mixed`

Gets a provider configuration option.

#### `getDecimals(): int`

Returns the number of decimals for the native coin (8 for Bitcoin, 18 for Ethereum, 6 for TRON).

#### `getName(): string`

Returns the blockchain name (e.g., "Bitcoin", "Ethereum").

#### `getSymbol(): string`

Returns the native coin symbol (e.g., "BTC", "ETH", "TRX").

#### `getBlock(string $hash = 'latest'): Block`

Retrieves a block by hash or height.

**Parameters:**
- `$hash` - Block hash, height (as string), or 'latest'

**Returns:** `Block` object

**Example:**
```php
$block = $provider->getBlock('latest');
echo "Block Height: {$block->height}\n";
echo "Block Hash: {$block->hash}\n";
echo "Transactions: " . count($block->txids) . "\n";
```

#### `getTx(string $txId): ?Transaction`

Retrieves a transaction by ID.

**Parameters:**
- `$txId` - Transaction ID/hash

**Returns:** `Transaction` object or `null` if not found

**Example:**
```php
$tx = $provider->getTx('a1b2c3...');
if ($tx) {
    echo "Confirmations: {$tx->confirmations}\n";
    echo "Fee: {$tx->fee}\n";
}
```

#### `getAddress(string $address): Address`

Retrieves address information including balance and tokens.

**Parameters:**
- `$address` - Blockchain address

**Returns:** `Address` object

**Example:**
```php
$address = $provider->getAddress('0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb');
echo "Balance: {$address->balance}\n";
echo "Tokens: " . count($address->tokens) . "\n";

foreach ($address->tokens as $token) {
    echo "{$token->name}: {$token->balance}\n";
}
```

#### `getAddressTransactions(string $address, int $page = 1, int $pageSize = 1000): TransactionList`

Retrieves transaction history for an address.

**Parameters:**
- `$address` - Blockchain address
- `$page` - Page number (1-indexed)
- `$pageSize` - Number of transactions per page

**Returns:** `TransactionList` object

**Example:**
```php
$txList = $provider->getAddressTransactions('bc1...', page: 1, pageSize: 50);
echo "Total: {$txList->totalItems}\n";
echo "Pages: {$txList->totalPages}\n";

foreach ($txList->transactions as $tx) {
    echo "TX: {$tx->txId} - {$tx->amount}\n";
}
```

#### `getAssets(string $address): array`

Retrieves all tokens/assets held by an address.

**Parameters:**
- `$address` - Blockchain address

**Returns:** Array of `Asset` objects

**Example:**
```php
$assets = $provider->getAssets('0x...');
foreach ($assets as $asset) {
    echo "{$asset->name} ({$asset->symbol}): {$asset->balance}\n";
}
```

#### `pushRawTransaction(RawTransaction $hex): PushedTX`

Broadcasts a signed transaction to the network.

**Parameters:**
- `$hex` - `RawTransaction` object containing serialized transaction

**Returns:** `PushedTX` with transaction ID

**Example:**
```php
$rawTx = new RawTransaction($signedHex);
$result = $provider->pushRawTransaction($rawTx);
echo "Transaction ID: {$result->txId}\n";
```

#### `getUTXO(string $address, bool $confirmed = true): array`

Retrieves unspent transaction outputs (Bitcoin-like chains only).

**Parameters:**
- `$address` - Bitcoin address
- `$confirmed` - Whether to include only confirmed UTXOs

**Returns:** Array of `UTXO` objects

**Example:**
```php
$utxos = $provider->getUTXO('bc1...');
foreach ($utxos as $utxo) {
    echo "UTXO: {$utxo->txid}:{$utxo->vout} = {$utxo->value} satoshis\n";
}
```

---

### EthereumProvider

Specific methods for Ethereum/EVM chains.

#### `evmCall(string $to, string $data, string $from = ''): string`

Executes a read-only smart contract call.

#### `estimateGas(array $params): string`

Estimates gas for a transaction.

#### `getGasPrice(): string`

Returns current gas price in wei.

#### `getChainId(): int`

Returns the EVM chain ID.

#### `getTokenInfo(string $contractAddress): TokenInfo`

Retrieves ERC20 token metadata.

**Parameters:**
- `$contractAddress` - Token contract address

**Returns:** `TokenInfo` with name, symbol, decimals

**Example:**
```php
$info = $ethereum->provider()->getTokenInfo('0xdAC17F958D2ee523a2206206994597C13D831ec7');
echo "{$info->name} ({$info->symbol}) - {$info->decimals} decimals\n";
// Output: "Tether USD (USDT) - 6 decimals"
```

---

### TrxProvider

Specific methods for TRON.

#### `getUnconfirmedBalance(string $address): string`

Returns the unconfirmed balance for an address.

**Example:**
```php
$balance = $tron->provider()->getUnconfirmedBalance('T...');
```

---

## Models

### AddressCredentials

Represents a wallet with address and private key.

**Properties:**
```php
readonly string $address;      // Public address
readonly string $privateKey;   // Private key (format varies by blockchain)
```

**Example:**
```php
$wallet = new AddressCredentials('bc1...', 'L1aW4...');
```

---

### RpcCredentials

Connection credentials for blockchain nodes.

**Properties:**
```php
readonly string $uri;              // JSON-RPC endpoint
readonly string $blockbookUri;     // Blockbook REST endpoint (optional)
readonly array $headers;           // HTTP headers (e.g., API keys)
readonly ?string $username;        // Basic auth username
readonly ?string $password;        // Basic auth password
```

**Example:**
```php
$credentials = new RpcCredentials(
    uri: 'https://btc.nownodes.io/',
    blockbookUri: 'https://btcbook.nownodes.io',
    headers: ['api-key' => 'your-api-key'],
    username: null,
    password: null
);
```

---

### Address

Blockchain address with balance and tokens.

**Properties:**
```php
string $address;            // Address string
Amount $balance;            // Balance as Amount object
array $tokens;              // Array of Asset objects
int $totalReceived;         // Total received (satoshis/wei)
int $totalSent;            // Total sent
int $txCount;              // Number of transactions
?int $unconfirmedBalance;  // Unconfirmed balance (if supported)
```

**Example:**
```php
$address = $provider->getAddress('0x...');
echo "Address: {$address->address}\n";
echo "Balance: " . $address->balance->toEth() . " ETH\n";
echo "Transactions: {$address->txCount}\n";
```

---

### Transaction

Blockchain transaction.

**Properties:**
```php
string $txId;                  // Transaction ID/hash
int $confirmations;            // Number of confirmations
int $blockHeight;              // Block height (0 if unconfirmed)
?int $blockTime;               // Block timestamp
Amount $amount;                // Transaction amount
Amount $fee;                   // Transaction fee
array $vin;                    // Inputs (array of TxvInOut)
array $vout;                   // Outputs (array of TxvInOut)
?string $blockHash;            // Block hash
```

**Example:**
```php
$tx = $provider->getTx('txid...');
echo "TX ID: {$tx->txId}\n";
echo "Confirmations: {$tx->confirmations}\n";
echo "Amount: {$tx->amount}\n";
echo "Fee: {$tx->fee}\n";
```

---

### Block

Blockchain block.

**Properties:**
```php
int $height;                  // Block height/number
string $hash;                 // Block hash
int $time;                    // Block timestamp
array $txids;                 // Array of transaction IDs
?string $previousBlockHash;   // Previous block hash
?string $nextBlockHash;       // Next block hash
```

**Example:**
```php
$block = $provider->getBlock('latest');
echo "Block #{$block->height}\n";
echo "Hash: {$block->hash}\n";
echo "Time: " . date('Y-m-d H:i:s', $block->time) . "\n";
echo "Transactions: " . count($block->txids) . "\n";
```

---

### Amount

Precise decimal amount with conversion methods.

**Properties:**
```php
readonly string $value;       // Value as string (for precision)
readonly int $decimals;       // Number of decimal places
```

**Methods:**

##### `toString(): string`
Returns the amount as a decimal string.

##### `toBtc(): string`
Converts to Bitcoin (8 decimals).

##### `toEth(): string`
Converts to Ether (18 decimals).

##### `toSatoshi(): string`
Converts to satoshis (integer).

##### `toWei(): string`
Converts to wei (integer).

**Example:**
```php
// From satoshis
$amount = new Amount('50000000', 8);
echo $amount->toBtc(); // "0.50000000"

// From wei
$amount = new Amount('1000000000000000000', 18);
echo $amount->toEth(); // "1.000000000000000000"
```

---

### Asset

Token/asset information.

**Properties:**
```php
string $contract;        // Contract address
string $name;            // Token name
string $symbol;          // Token symbol
int $decimals;           // Token decimals
Amount $balance;         // Token balance
?string $type;           // Token type (ERC20, TRC20, etc.)
```

**Example:**
```php
$address = $provider->getAddress('0x...');
foreach ($address->tokens as $asset) {
    echo "{$asset->symbol}: {$asset->balance}\n";
}
```

---

### Fee

Transaction fee specification.

**Properties:**
```php
?string $feeLimit;       // Maximum fee (in smallest unit)
?string $feeRate;        // Fee rate (satoshis per byte for Bitcoin)
?string $gasPrice;       // Gas price (wei for Ethereum)
?string $gasLimit;       // Gas limit (Ethereum)
?string $maxFeePerGas;   // EIP-1559 max fee per gas
?string $maxPriorityFeePerGas;  // EIP-1559 priority fee
```

**Example:**
```php
// Bitcoin custom fee
$fee = new Fee(feeRate: '50'); // 50 satoshis per byte

// Ethereum EIP-1559
$fee = new Fee(
    maxFeePerGas: '50000000000',           // 50 Gwei
    maxPriorityFeePerGas: '2000000000',    // 2 Gwei
    gasLimit: '21000'
);
```

---

### UTXO

Unspent transaction output (Bitcoin-like chains).

**Properties:**
```php
string $txid;            // Transaction ID
int $vout;               // Output index
string $value;           // Value in satoshis
string $address;         // Address
int $confirmations;      // Number of confirmations
```

---

### IncomingTransaction

Real-time transaction event.

**Properties:**
```php
string $txId;                      // Transaction ID
Amount $amount;                    // Transaction amount
string $address;                   // Related address
TransactionDirection $direction;   // Incoming/Outgoing/Self
?string $from;                     // Sender address
?string $to;                       // Recipient address
?Asset $asset;                     // Token (if asset transfer)
```

**Example:**
```php
$stream->subscribeToAddresses(['bc1...'], function(IncomingTransaction $tx) {
    if ($tx->direction === TransactionDirection::Incoming) {
        echo "Received {$tx->amount} at {$tx->address}\n";
    }
});
```

---

### IncomingBlock

Real-time block event.

**Properties:**
```php
int $height;            // Block height
string $hash;           // Block hash
int $time;              // Block timestamp
```

---

## Streams

All streams implement `StreamableInterface`.

### Common Methods

#### `subscribeToAddresses(array $addresses, callable $callback): void`

Subscribes to transactions for specific addresses.

**Parameters:**
- `$addresses` - Array of addresses to monitor
- `$callback` - Function receiving `IncomingTransaction`

**Example:**
```php
$stream = $client->stream();
$stream->subscribeToAddresses(
    addresses: ['bc1...', 'bc1...'],
    callback: function(IncomingTransaction $tx) {
        echo "New transaction: {$tx->txId}\n";
        echo "Amount: {$tx->amount}\n";
        echo "Direction: {$tx->direction->name}\n";
    }
);
$stream->run();
```

#### `subscribeToAnyTransaction(callable $callback): void`

Subscribes to all transactions on the network.

**Warning:** This can be very high volume on active networks.

**Example:**
```php
$stream->subscribeToAnyTransaction(function(IncomingTransaction $tx) {
    echo "Network TX: {$tx->txId}\n";
});
```

#### `subscribeToAnyBlock(callable $callback): void`

Subscribes to new blocks.

**Example:**
```php
$stream->subscribeToAnyBlock(function(IncomingBlock $block) {
    echo "New block #{$block->height} at " . date('H:i:s', $block->time) . "\n";
});
```

#### `run(): void`

Starts the event loop. This method blocks until stopped.

**Example:**
```php
// Set up subscriptions
$stream->subscribeToAddresses([...], $callback);

// Start listening (blocks)
$stream->run();
```

---

### BitcoinStream

WebSocket-based stream for Bitcoin.

**Connection:** Connects to Blockbook WebSocket endpoint.

**Features:**
- Automatic reconnection
- Ping/pong heartbeat
- Unconfirmed transaction support

---

### EthereumStream

WebSocket-based stream for Ethereum.

**Connection:** Connects to Infura or similar WebSocket provider.

**Features:**
- Block header subscriptions
- Transaction filtering
- Stale connection detection

---

### TronStream

Polling-based stream for TRON (no WebSocket support).

**Polling Interval:** 3 seconds

**Features:**
- Block gap detection
- Automatic recovery
- Efficient transaction filtering

---

## Exceptions

### MultiCryptoApiException

Base exception class. Extend this for custom exceptions.

```php
try {
    $tx = $client->sendCoins($wallet, $to, $amount);
} catch (MultiCryptoApiException $e) {
    echo "Error: {$e->getMessage()}\n";
}
```

---

### NotEnoughFundsException

Thrown when the wallet balance is insufficient for the transaction.

```php
try {
    $tx = $client->sendCoins($wallet, $to, '999999');
} catch (NotEnoughFundsException $e) {
    echo "Insufficient balance\n";
}
```

---

### NotEnoughFundsToCoverFeeException

Thrown when the balance can't cover the transaction fee.

```php
try {
    $tx = $client->sendCoins($wallet, $to, $amount);
} catch (NotEnoughFundsToCoverFeeException $e) {
    echo "Can't afford transaction fee\n";
}
```

---

### IncorrectTxException

Thrown when transaction building or validation fails.

```php
try {
    $tx = $client->sendCoins($wallet, 'invalid-address', $amount);
} catch (IncorrectTxException $e) {
    echo "Invalid transaction: {$e->getMessage()}\n";
}
```

---

## Utilities

### EthUtil

Ethereum-specific utility functions.

#### Static Methods

##### `toWei(string $value, int $decimals = 18): string`

Converts from ether/tokens to wei.

**Example:**
```php
$wei = EthUtil::toWei('1.5', 18);  // "1500000000000000000"
```

##### `fromWei(string $value, int $decimals = 18): string`

Converts from wei to ether/tokens.

**Example:**
```php
$eth = EthUtil::fromWei('1500000000000000000', 18);  // "1.5"
```

##### `parseTokenTransfer(string $data): array`

Parses ERC20 transfer function call data.

**Returns:** Array with 'to' and 'value' keys

**Example:**
```php
$parsed = EthUtil::parseTokenTransfer($tx->input);
echo "To: {$parsed['to']}\n";
echo "Value: {$parsed['value']}\n";
```

---

### Throttler

Rate limiting utility.

#### Methods

##### `throttle(callable $callback, float $interval): void`

Throttles function calls to a maximum rate.

**Parameters:**
- `$callback` - Function to throttle
- `$interval` - Minimum time between calls (seconds)

**Example:**
```php
$throttler = new Throttler();
$throttler->throttle(function() {
    echo "API call\n";
}, 1.0);  // Max 1 call per second
```

---

## Enums

### ApiType

Supported blockchain types.

**Values:**
- `ApiType::Bitcoin`
- `ApiType::Litecoin`
- `ApiType::Dogecoin`
- `ApiType::Dash`
- `ApiType::Zcash`
- `ApiType::Ethereum`
- `ApiType::Tron`

---

### TransactionDirection

Transaction direction relative to an address.

**Values:**
- `TransactionDirection::Incoming` - Received
- `TransactionDirection::Outgoing` - Sent
- `TransactionDirection::Self` - Self-transfer

---

## Complete Example

```php
<?php
require 'vendor/autoload.php';

use MultiCryptoApi\Factory\ApiFactory;
use MultiCryptoApi\Model\RpcCredentials;
use MultiCryptoApi\Model\IncomingTransaction;
use MultiCryptoApi\Exception\NotEnoughFundsException;

// Configure credentials
$credentials = new RpcCredentials(
    uri: 'https://btc.nownodes.io/',
    blockbookUri: 'https://btcbook.nownodes.io',
    headers: ['api-key' => getenv('NODES_API_KEY')]
);

// Create Bitcoin client
$bitcoin = ApiFactory::bitcoin($credentials);

// Query address
$address = $bitcoin->provider()->getAddress('bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh');
echo "Balance: " . $address->balance->toBtc() . " BTC\n";
echo "Transactions: {$address->txCount}\n";

// List tokens/assets
foreach ($address->tokens as $token) {
    echo "Token: {$token->name} = {$token->balance}\n";
}

// Send transaction
try {
    $wallet = $bitcoin->createFromPrivateKey(getenv('PRIVATE_KEY'));
    $tx = $bitcoin->sendCoins(
        from: $wallet,
        addressTo: 'bc1recipient...',
        amount: '0.001'
    );
    echo "Sent! TX ID: {$tx->txId}\n";
} catch (NotEnoughFundsException $e) {
    echo "Error: Insufficient funds\n";
}

// Monitor real-time transactions
$stream = $bitcoin->stream();
$stream->subscribeToAddresses(
    addresses: [$wallet->address],
    callback: function(IncomingTransaction $tx) {
        echo "New TX: {$tx->txId}\n";
        echo "Amount: {$tx->amount}\n";
        echo "Direction: {$tx->direction->name}\n";
    }
);

echo "Listening for transactions...\n";
$stream->run();  // Blocks until stopped
```

---

## See Also

- [Architecture Guide](ARCHITECTURE.md)
- [Examples](EXAMPLES.md)
- [README](../README.md)
