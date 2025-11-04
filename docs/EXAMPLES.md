# Usage Examples

Comprehensive examples and tutorials for Multi Crypto API.

## Table of Contents

- [Setup](#setup)
- [Bitcoin Examples](#bitcoin-examples)
- [Ethereum Examples](#ethereum-examples)
- [TRON Examples](#tron-examples)
- [Real-Time Monitoring](#real-time-monitoring)
- [Advanced Topics](#advanced-topics)

---

## Setup

### Installation

```bash
composer require payex-id/multicryptoapi
```

### Configuration

Create a credentials configuration file:

```php
<?php
// config/credentials.php

use MultiCryptoApi\Model\RpcCredentials;

return [
    'bitcoin' => new RpcCredentials(
        uri: 'https://btc.nownodes.io/',
        blockbookUri: 'https://btcbook.nownodes.io',
        headers: ['api-key' => getenv('NODES_API_KEY')]
    ),

    'ethereum' => new RpcCredentials(
        uri: 'https://eth.nownodes.io/',
        blockbookUri: 'https://ethbook.nownodes.io',
        headers: ['api-key' => getenv('NODES_API_KEY')]
    ),

    'tron' => new RpcCredentials(
        uri: 'https://api.trongrid.io',
        headers: ['TRON-PRO-API-KEY' => getenv('TRONGRID_API_KEY')]
    ),
];
```

### Environment Variables

Create a `.env` file:

```bash
NODES_API_KEY=your-nownodes-api-key
INFURA_API_KEY=your-infura-api-key
TRONGRID_API_KEY=your-trongrid-api-key
TRONSCAN_API_KEY=your-tronscan-api-key
```

---

## Bitcoin Examples

### Example 1: Query Address Balance

```php
<?php
require 'vendor/autoload.php';

use MultiCryptoApi\Factory\ApiFactory;

$credentials = require 'config/credentials.php';
$bitcoin = ApiFactory::bitcoin($credentials['bitcoin']);

// Query address
$address = $bitcoin->provider()->getAddress('bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh');

echo "Address: {$address->address}\n";
echo "Balance: " . $address->balance->toBtc() . " BTC\n";
echo "Total Received: " . (new Amount($address->totalReceived, 8))->toBtc() . " BTC\n";
echo "Total Sent: " . (new Amount($address->totalSent, 8))->toBtc() . " BTC\n";
echo "Transaction Count: {$address->txCount}\n";
```

### Example 2: Create New Bitcoin Wallet

```php
<?php
require 'vendor/autoload.php';

use MultiCryptoApi\Factory\ApiFactory;

$bitcoin = ApiFactory::bitcoin($credentials['bitcoin']);

// Generate new wallet
$wallet = $bitcoin->createWallet();

echo "New Bitcoin Wallet Created!\n";
echo "Address: {$wallet->address}\n";
echo "Private Key (WIF): {$wallet->privateKey}\n";
echo "\n⚠️  SAVE YOUR PRIVATE KEY SECURELY! ⚠️\n";
```

### Example 3: Send Bitcoin

```php
<?php
require 'vendor/autoload.php';

use MultiCryptoApi\Factory\ApiFactory;
use MultiCryptoApi\Exception\NotEnoughFundsException;

$bitcoin = ApiFactory::bitcoin($credentials['bitcoin']);

try {
    // Import wallet from private key
    $wallet = $bitcoin->createFromPrivateKey('L1aW4aubDFB7yfras2S1mN3bqg9nwySY8nkoLmJebSLD5BWv3ENZ');

    // Check balance first
    $address = $bitcoin->provider()->getAddress($wallet->address);
    echo "Current Balance: " . $address->balance->toBtc() . " BTC\n";

    // Send 0.001 BTC
    $tx = $bitcoin->sendCoins(
        from: $wallet,
        addressTo: 'bc1qrecipient...',
        amount: '0.001'
    );

    echo "Transaction sent successfully!\n";
    echo "Transaction ID: {$tx->txId}\n";
    echo "Fee: " . $tx->fee->toBtc() . " BTC\n";

} catch (NotEnoughFundsException $e) {
    echo "Error: Insufficient funds\n";
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n";
}
```

### Example 4: Send Bitcoin with Custom Fee

```php
<?php
require 'vendor/autoload.php';

use MultiCryptoApi\Factory\ApiFactory;
use MultiCryptoApi\Model\Fee;

$bitcoin = ApiFactory::bitcoin($credentials['bitcoin']);
$wallet = $bitcoin->createFromPrivateKey($privateKey);

// Create custom fee (50 satoshis per byte)
$customFee = new Fee(feeRate: '50');

$tx = $bitcoin->sendCoins(
    from: $wallet,
    addressTo: 'bc1qrecipient...',
    amount: '0.001',
    fee: $customFee
);

echo "Sent with custom fee!\n";
echo "TX ID: {$tx->txId}\n";
```

### Example 5: Send to Multiple Recipients

```php
<?php
require 'vendor/autoload.php';

use MultiCryptoApi\Factory\ApiFactory;

$bitcoin = ApiFactory::bitcoin($credentials['bitcoin']);

// Send from multiple inputs to multiple outputs
$tx = $bitcoin->sendMany(
    inputs: [
        'bc1qinput1...' => 'privateKey1WIF',
        'bc1qinput2...' => 'privateKey2WIF',
    ],
    outputs: [
        'bc1qoutput1...' => '0.05',
        'bc1qoutput2...' => '0.03',
        'bc1qoutput3...' => '0.02',
    ]
);

echo "Multi-output transaction sent!\n";
echo "TX ID: {$tx->txId}\n";
```

### Example 6: Query Transaction History

```php
<?php
require 'vendor/autoload.php';

use MultiCryptoApi\Factory\ApiFactory;

$bitcoin = ApiFactory::bitcoin($credentials['bitcoin']);

// Get transaction history (paginated)
$txList = $bitcoin->provider()->getAddressTransactions(
    address: 'bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh',
    page: 1,
    pageSize: 10
);

echo "Total Transactions: {$txList->totalItems}\n";
echo "Current Page: {$txList->page} of {$txList->totalPages}\n\n";

foreach ($txList->transactions as $tx) {
    $date = date('Y-m-d H:i:s', $tx->blockTime ?? time());
    echo "TX: {$tx->txId}\n";
    echo "  Date: {$date}\n";
    echo "  Amount: " . $tx->amount->toBtc() . " BTC\n";
    echo "  Fee: " . $tx->fee->toBtc() . " BTC\n";
    echo "  Confirmations: {$tx->confirmations}\n\n";
}
```

### Example 7: Query UTXOs

```php
<?php
require 'vendor/autoload.php';

use MultiCryptoApi\Factory\ApiFactory;

$bitcoin = ApiFactory::bitcoin($credentials['bitcoin']);

// Get unspent outputs
$utxos = $bitcoin->provider()->getUTXO('bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh');

echo "Unspent Outputs (UTXOs):\n";
$total = '0';

foreach ($utxos as $utxo) {
    $amount = (new Amount($utxo->value, 8))->toBtc();
    echo "  {$utxo->txid}:{$utxo->vout}\n";
    echo "    Amount: {$amount} BTC\n";
    echo "    Confirmations: {$utxo->confirmations}\n";

    $total = bcadd($total, $utxo->value, 0);
}

$totalBtc = (new Amount($total, 8))->toBtc();
echo "\nTotal Spendable: {$totalBtc} BTC\n";
```

---

## Ethereum Examples

### Example 1: Query ETH Balance and Tokens

```php
<?php
require 'vendor/autoload.php';

use MultiCryptoApi\Factory\ApiFactory;

$ethereum = ApiFactory::ethereum($credentials['ethereum']);

// Query address
$address = $ethereum->provider()->getAddress('0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb');

echo "Address: {$address->address}\n";
echo "ETH Balance: " . $address->balance->toEth() . " ETH\n";
echo "Transaction Count: {$address->txCount}\n\n";

echo "ERC20 Tokens:\n";
foreach ($address->tokens as $token) {
    echo "  {$token->symbol} ({$token->name})\n";
    echo "    Balance: {$token->balance}\n";
    echo "    Contract: {$token->contract}\n";
    echo "    Decimals: {$token->decimals}\n\n";
}
```

### Example 2: Create Ethereum Wallet

```php
<?php
require 'vendor/autoload.php';

use MultiCryptoApi\Factory\ApiFactory;

$ethereum = ApiFactory::ethereum($credentials['ethereum']);

// Generate new wallet
$wallet = $ethereum->createWallet();

echo "New Ethereum Wallet Created!\n";
echo "Address: {$wallet->address}\n";
echo "Private Key: {$wallet->privateKey}\n";
echo "\n⚠️  SAVE YOUR PRIVATE KEY SECURELY! ⚠️\n";
```

### Example 3: Send ETH

```php
<?php
require 'vendor/autoload.php';

use MultiCryptoApi\Factory\ApiFactory;

$ethereum = ApiFactory::ethereum($credentials['ethereum']);

// Import wallet
$wallet = $ethereum->createFromPrivateKey('0x4c0883a69102937d6231471b5dbb6204fe512961708279f8f640c5f09e28e57a');

// Send 0.1 ETH
$tx = $ethereum->sendCoins(
    from: $wallet,
    addressTo: '0xRecipient...',
    amount: '0.1'
);

echo "Transaction sent!\n";
echo "TX Hash: {$tx->txId}\n";
echo "View on Etherscan: https://etherscan.io/tx/{$tx->txId}\n";
```

### Example 4: Send ERC20 Tokens

```php
<?php
require 'vendor/autoload.php';

use MultiCryptoApi\Factory\ApiFactory;

$ethereum = ApiFactory::ethereum($credentials['ethereum']);
$wallet = $ethereum->createFromPrivateKey($privateKey);

// Send USDT (ERC20)
$tx = $ethereum->sendAsset(
    from: $wallet,
    assetId: '0xdAC17F958D2ee523a2206206994597C13D831ec7', // USDT contract
    addressTo: '0xRecipient...',
    amount: '100.50'
);

echo "USDT sent!\n";
echo "TX Hash: {$tx->txId}\n";
```

### Example 5: Send ETH with Custom Gas (EIP-1559)

```php
<?php
require 'vendor/autoload.php';

use MultiCryptoApi\Factory\ApiFactory;
use MultiCryptoApi\Model\Fee;

$ethereum = ApiFactory::ethereum($credentials['ethereum']);
$wallet = $ethereum->createFromPrivateKey($privateKey);

// EIP-1559 transaction with custom gas
$customFee = new Fee(
    maxFeePerGas: '50000000000',        // 50 Gwei max
    maxPriorityFeePerGas: '2000000000', // 2 Gwei priority
    gasLimit: '21000'
);

$tx = $ethereum->sendCoins(
    from: $wallet,
    addressTo: '0xRecipient...',
    amount: '0.1',
    fee: $customFee
);

echo "Transaction sent with EIP-1559 pricing!\n";
echo "TX Hash: {$tx->txId}\n";
```

### Example 6: Get ERC20 Token Info

```php
<?php
require 'vendor/autoload.php';

use MultiCryptoApi\Factory\ApiFactory;

$ethereum = ApiFactory::ethereum($credentials['ethereum']);

// Get token information
$tokenInfo = $ethereum->provider()->getTokenInfo('0xdAC17F958D2ee523a2206206994597C13D831ec7');

echo "Token Information:\n";
echo "Name: {$tokenInfo->name}\n";
echo "Symbol: {$tokenInfo->symbol}\n";
echo "Decimals: {$tokenInfo->decimals}\n";
echo "Contract: {$tokenInfo->contract}\n";
```

### Example 7: Call Smart Contract (Read-Only)

```php
<?php
require 'vendor/autoload.php';

use MultiCryptoApi\Factory\ApiFactory;

$ethereum = ApiFactory::ethereum($credentials['ethereum']);

// Call balanceOf(address) on ERC20
$address = '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb';
$paddedAddress = str_pad(substr($address, 2), 64, '0', STR_PAD_LEFT);
$data = '0x70a08231' . $paddedAddress;

$result = $ethereum->provider()->evmCall(
    to: '0xdAC17F958D2ee523a2206206994597C13D831ec7', // USDT
    data: $data
);

$balance = hexdec($result);
echo "Balance: " . ($balance / 1000000) . " USDT\n"; // USDT has 6 decimals
```

### Example 8: Estimate Gas

```php
<?php
require 'vendor/autoload.php';

use MultiCryptoApi\Factory\ApiFactory;

$ethereum = ApiFactory::ethereum($credentials['ethereum']);

// Estimate gas for transaction
$gasEstimate = $ethereum->provider()->estimateGas([
    'from' => '0xYourAddress...',
    'to' => '0xRecipient...',
    'value' => '0x' . dechex(100000000000000000), // 0.1 ETH in wei
]);

echo "Estimated Gas: " . hexdec($gasEstimate) . "\n";
```

---

## TRON Examples

### Example 1: Query TRX Balance and Tokens

```php
<?php
require 'vendor/autoload.php';

use MultiCryptoApi\Factory\ApiFactory;

$tron = ApiFactory::tron($credentials['tron']);

// Query address
$address = $tron->provider()->getAddress('TQn9Y2khEsLJW1ChVWFMSMeRDow5KcbLSE');

echo "Address: {$address->address}\n";
echo "TRX Balance: " . $address->balance->toString() . " TRX\n";
echo "Transaction Count: {$address->txCount}\n\n";

echo "TRC20 Tokens:\n";
foreach ($address->tokens as $token) {
    echo "  {$token->symbol} ({$token->name})\n";
    echo "    Balance: {$token->balance}\n";
    echo "    Contract: {$token->contract}\n\n";
}
```

### Example 2: Check if Address is Active

```php
<?php
require 'vendor/autoload.php';

use MultiCryptoApi\Factory\ApiFactory;

$tron = ApiFactory::tron($credentials['tron']);

$address = 'TNewAddress...';

if ($tron->isAddressActive($address)) {
    echo "Address is active\n";
} else {
    echo "Address needs activation (requires 1 TRX)\n";
}
```

### Example 3: Activate New TRON Address

```php
<?php
require 'vendor/autoload.php';

use MultiCryptoApi\Factory\ApiFactory;

$tron = ApiFactory::tron($credentials['tron']);

// Wallet with TRX to pay for activation
$fundedWallet = $tron->createFromPrivateKey($privateKey);

// New address to activate
$newAddress = 'TNewAddress...';

// Activate (sends 1 TRX)
$tx = $tron->activateAddress(
    from: $fundedWallet,
    addressToActivate: $newAddress
);

echo "Address activated!\n";
echo "TX ID: {$tx->txId}\n";
echo "View on TronScan: https://tronscan.org/#/transaction/{$tx->txId}\n";
```

### Example 4: Send TRX

```php
<?php
require 'vendor/autoload.php';

use MultiCryptoApi\Factory\ApiFactory;

$tron = ApiFactory::tron($credentials['tron']);
$wallet = $tron->createFromPrivateKey($privateKey);

// Send 10 TRX
$tx = $tron->sendCoins(
    from: $wallet,
    addressTo: 'TRecipient...',
    amount: '10'
);

echo "TRX sent!\n";
echo "TX ID: {$tx->txId}\n";
```

### Example 5: Send USDT (TRC20)

```php
<?php
require 'vendor/autoload.php';

use MultiCryptoApi\Factory\ApiFactory;

$tron = ApiFactory::tron($credentials['tron']);
$wallet = $tron->createFromPrivateKey($privateKey);

// Send 50 USDT
$tx = $tron->sendAsset(
    from: $wallet,
    assetId: 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t', // USDT TRC20
    addressTo: 'TRecipient...',
    amount: '50.00'
);

echo "USDT sent!\n";
echo "TX ID: {$tx->txId}\n";
```

### Example 6: Delegate Energy to Address

```php
<?php
require 'vendor/autoload.php';

use MultiCryptoApi\Factory\ApiFactory;

$tron = ApiFactory::tron($credentials['tron']);
$wallet = $tron->createFromPrivateKey($privateKey);

// Delegate 100 TRX worth of energy
$tx = $tron->delegateResource(
    from: $wallet,
    receiverAddress: 'TReceiver...',
    balance: '100',
    resource: 'ENERGY',  // or 'BANDWIDTH'
    lock: false
);

echo "Energy delegated!\n";
echo "TX ID: {$tx->txId}\n";
echo "The receiver can now use this energy for smart contract calls\n";
```

### Example 7: Get Unconfirmed Balance

```php
<?php
require 'vendor/autoload.php';

use MultiCryptoApi\Factory\ApiFactory;

$tron = ApiFactory::tron($credentials['tron']);

$address = 'TYourAddress...';
$unconfirmed = $tron->provider()->getUnconfirmedBalance($address);

echo "Unconfirmed Balance: {$unconfirmed} SUN\n";
echo "In TRX: " . ($unconfirmed / 1000000) . " TRX\n";
```

---

## Real-Time Monitoring

### Example 1: Monitor Bitcoin Address

```php
<?php
require 'vendor/autoload.php';

use MultiCryptoApi\Factory\ApiFactory;
use MultiCryptoApi\Model\IncomingTransaction;
use MultiCryptoApi\Model\Enum\TransactionDirection;

$bitcoin = ApiFactory::bitcoin($credentials['bitcoin']);

echo "Monitoring Bitcoin addresses...\n";

$stream = $bitcoin->stream();
$stream->subscribeToAddresses(
    addresses: [
        'bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh',
        'bc1qanother...',
    ],
    callback: function(IncomingTransaction $tx) {
        echo "\n" . str_repeat('=', 50) . "\n";
        echo "New Transaction Detected!\n";
        echo "TX ID: {$tx->txId}\n";
        echo "Amount: {$tx->amount}\n";
        echo "Direction: {$tx->direction->name}\n";

        if ($tx->direction === TransactionDirection::Incoming) {
            echo "💰 Received payment!\n";
        } elseif ($tx->direction === TransactionDirection::Outgoing) {
            echo "📤 Sent payment\n";
        }

        echo str_repeat('=', 50) . "\n";
    }
);

$stream->run(); // Blocks until stopped
```

### Example 2: Monitor Ethereum Blocks and Transactions

```php
<?php
require 'vendor/autoload.php';

use MultiCryptoApi\Factory\ApiFactory;
use MultiCryptoApi\Model\IncomingBlock;
use MultiCryptoApi\Model\IncomingTransaction;

$ethereum = ApiFactory::ethereum($credentials['ethereum']);
$stream = $ethereum->stream();

// Monitor new blocks
$stream->subscribeToAnyBlock(function(IncomingBlock $block) {
    $time = date('H:i:s', $block->time);
    echo "[{$time}] New block #{$block->height} - {$block->hash}\n";
});

// Monitor specific addresses
$stream->subscribeToAddresses(
    addresses: ['0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb'],
    callback: function(IncomingTransaction $tx) {
        echo "\n💰 Payment received!\n";
        echo "TX: {$tx->txId}\n";
        echo "Amount: {$tx->amount}\n";

        if ($tx->asset) {
            echo "Token: {$tx->asset->symbol}\n";
        }
    }
);

echo "Monitoring Ethereum network...\n";
$stream->run();
```

### Example 3: Monitor TRON with Custom Handling

```php
<?php
require 'vendor/autoload.php';

use MultiCryptoApi\Factory\ApiFactory;
use MultiCryptoApi\Model\IncomingTransaction;

$tron = ApiFactory::tron($credentials['tron']);
$stream = $tron->stream();

$myAddress = 'TYourAddress...';

$stream->subscribeToAddresses(
    addresses: [$myAddress],
    callback: function(IncomingTransaction $tx) use ($myAddress) {
        echo "\nTransaction detected!\n";

        if ($tx->asset) {
            // Token transfer
            echo "Token: {$tx->asset->symbol}\n";
            echo "Amount: {$tx->asset->balance}\n";

            if ($tx->asset->symbol === 'USDT') {
                // Handle USDT payment
                processUsdtPayment($tx);
            }
        } else {
            // TRX transfer
            echo "TRX Amount: {$tx->amount}\n";
            processTrxPayment($tx);
        }
    }
);

function processUsdtPayment($tx) {
    // Your payment processing logic
    echo "Processing USDT payment...\n";
}

function processTrxPayment($tx) {
    // Your payment processing logic
    echo "Processing TRX payment...\n";
}

echo "Monitoring TRON address: {$myAddress}\n";
$stream->run();
```

### Example 4: Monitor All Network Transactions (Bitcoin)

```php
<?php
require 'vendor/autoload.php';

use MultiCryptoApi\Factory\ApiFactory;
use MultiCryptoApi\Model\IncomingTransaction;

$bitcoin = ApiFactory::bitcoin($credentials['bitcoin']);
$stream = $bitcoin->stream();

// ⚠️ Warning: This monitors ALL transactions on the network
// Use with caution - very high volume!

$txCount = 0;
$startTime = time();

$stream->subscribeToAnyTransaction(function(IncomingTransaction $tx) use (&$txCount, $startTime) {
    $txCount++;
    $elapsed = time() - $startTime;
    $rate = $elapsed > 0 ? $txCount / $elapsed : 0;

    echo sprintf(
        "[%d] TX: %s | %.8f BTC | Rate: %.2f tx/s\r",
        $txCount,
        substr($tx->txId, 0, 16) . '...',
        $tx->amount->toBtc(),
        $rate
    );
});

echo "Monitoring Bitcoin network (all transactions)...\n";
$stream->run();
```

---

## Advanced Topics

### Example 1: Payment Processor with Confirmation Tracking

```php
<?php
require 'vendor/autoload.php';

use MultiCryptoApi\Factory\ApiFactory;
use MultiCryptoApi\Model\IncomingTransaction;

class PaymentProcessor {
    private $api;
    private $pendingPayments = [];

    public function __construct($api) {
        $this->api = $api;
    }

    public function monitorAddress(string $address, callable $onConfirmed) {
        $stream = $this->api->stream();

        $stream->subscribeToAddresses(
            addresses: [$address],
            callback: function(IncomingTransaction $tx) use ($onConfirmed) {
                echo "Transaction detected: {$tx->txId}\n";
                $this->trackConfirmations($tx->txId, $onConfirmed);
            }
        );

        $stream->run();
    }

    private function trackConfirmations(string $txId, callable $onConfirmed) {
        // Check confirmations every 60 seconds
        $loop = \React\EventLoop\Loop::get();
        $loop->addPeriodicTimer(60, function($timer) use ($txId, $onConfirmed) {
            $tx = $this->api->provider()->getTx($txId);

            if ($tx && $tx->confirmations >= 3) {
                echo "Transaction {$txId} confirmed!\n";
                $onConfirmed($tx);
                $timer->cancel();
            } else {
                echo "Confirmations: {$tx->confirmations}/3\n";
            }
        });
    }
}

// Usage
$bitcoin = ApiFactory::bitcoin($credentials['bitcoin']);
$processor = new PaymentProcessor($bitcoin);

$processor->monitorAddress(
    address: 'bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh',
    onConfirmed: function($tx) {
        echo "Payment confirmed and ready to process!\n";
        echo "TX: {$tx->txId}\n";
        echo "Amount: " . $tx->amount->toBtc() . " BTC\n";

        // Process order, update database, etc.
    }
);
```

### Example 2: Batch Transaction Processing

```php
<?php
require 'vendor/autoload.php';

use MultiCryptoApi\Factory\ApiFactory;

$ethereum = ApiFactory::ethereum($credentials['ethereum']);
$wallet = $ethereum->createFromPrivateKey($privateKey);

// List of recipients
$recipients = [
    '0xRecipient1...' => '0.1',
    '0xRecipient2...' => '0.2',
    '0xRecipient3...' => '0.15',
];

echo "Sending to " . count($recipients) . " recipients...\n";

foreach ($recipients as $address => $amount) {
    try {
        $tx = $ethereum->sendCoins(
            from: $wallet,
            addressTo: $address,
            amount: $amount
        );

        echo "✓ Sent {$amount} ETH to {$address}\n";
        echo "  TX: {$tx->txId}\n";

        // Wait 2 seconds between transactions to avoid nonce issues
        sleep(2);

    } catch (\Exception $e) {
        echo "✗ Failed to send to {$address}: {$e->getMessage()}\n";
    }
}

echo "\nBatch processing complete!\n";
```

### Example 3: Multi-Chain Balance Checker

```php
<?php
require 'vendor/autoload.php';

use MultiCryptoApi\Factory\ApiFactory;

class MultiChainWallet {
    private $address;
    private $credentials;

    public function __construct(string $address, array $credentials) {
        $this->address = $address;
        $this->credentials = $credentials;
    }

    public function getBalances(): array {
        $balances = [];

        // Bitcoin
        try {
            $bitcoin = ApiFactory::bitcoin($this->credentials['bitcoin']);
            $addr = $bitcoin->provider()->getAddress($this->address);
            $balances['BTC'] = $addr->balance->toBtc();
        } catch (\Exception $e) {
            $balances['BTC'] = 'Error: ' . $e->getMessage();
        }

        // Ethereum
        try {
            $ethereum = ApiFactory::ethereum($this->credentials['ethereum']);
            $addr = $ethereum->provider()->getAddress($this->address);
            $balances['ETH'] = $addr->balance->toEth();

            // Also get tokens
            foreach ($addr->tokens as $token) {
                $balances[$token->symbol] = $token->balance->toString();
            }
        } catch (\Exception $e) {
            $balances['ETH'] = 'Error: ' . $e->getMessage();
        }

        return $balances;
    }
}

// Usage
$wallet = new MultiChainWallet('your-address', require 'config/credentials.php');
$balances = $wallet->getBalances();

echo "Multi-Chain Balances:\n";
foreach ($balances as $currency => $balance) {
    echo "  {$currency}: {$balance}\n";
}
```

### Example 4: Transaction Fee Comparison

```php
<?php
require 'vendor/autoload.php';

use MultiCryptoApi\Factory\ApiFactory;
use MultiCryptoApi\Model\Fee;

$ethereum = ApiFactory::ethereum($credentials['ethereum']);
$wallet = $ethereum->createFromPrivateKey($privateKey);

// Test different gas prices
$gasPrices = [
    'slow'    => ['max' => '30000000000', 'priority' => '1000000000'],
    'normal'  => ['max' => '50000000000', 'priority' => '2000000000'],
    'fast'    => ['max' => '80000000000', 'priority' => '3000000000'],
];

echo "Gas Price Comparison:\n\n";

foreach ($gasPrices as $speed => $prices) {
    $fee = new Fee(
        maxFeePerGas: $prices['max'],
        maxPriorityFeePerGas: $prices['priority'],
        gasLimit: '21000'
    );

    $gasCost = bcmul($prices['max'], '21000', 0);
    $ethCost = bcdiv($gasCost, '1000000000000000000', 8);

    echo strtoupper($speed) . ":\n";
    echo "  Max Fee: " . ($prices['max'] / 1000000000) . " Gwei\n";
    echo "  Priority Fee: " . ($prices['priority'] / 1000000000) . " Gwei\n";
    echo "  Total Cost: ~{$ethCost} ETH\n\n";
}
```

---

## Testing

### Example: Test Transaction Building (Without Broadcasting)

```php
<?php
require 'vendor/autoload.php';

use MultiCryptoApi\Factory\ApiFactory;
use MultiCryptoApi\Api\Builder\BitcoinTxBuilder;

$bitcoin = ApiFactory::bitcoin($credentials['bitcoin']);

// Create test wallet
$wallet = $bitcoin->createFromPrivateKey($testPrivateKey);

echo "Testing transaction building...\n";

try {
    $builder = new BitcoinTxBuilder($bitcoin->provider());

    // Build transaction (but don't broadcast)
    $rawTx = $builder->build(
        from: $wallet,
        to: 'bc1qtest...',
        amount: '0.001',
        fee: null
    );

    echo "✓ Transaction built successfully\n";
    echo "Raw TX: {$rawTx}\n";
    echo "Raw TX Size: " . strlen($rawTx) / 2 . " bytes\n";

    // Don't push to network during testing
    // $result = $bitcoin->provider()->pushRawTransaction($rawTx);

} catch (\Exception $e) {
    echo "✗ Error: {$e->getMessage()}\n";
}
```

---

## See Also

- [API Reference](API_REFERENCE.md)
- [Architecture Guide](ARCHITECTURE.md)
- [README](../README.md)
- [Example Files](/examples) - 42+ ready-to-run examples
