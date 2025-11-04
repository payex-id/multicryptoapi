# Contributing to Multi Crypto API

Thank you for your interest in contributing to Multi Crypto API! This document provides guidelines and instructions for contributing to the project.

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Getting Started](#getting-started)
- [Development Setup](#development-setup)
- [Project Structure](#project-structure)
- [Coding Standards](#coding-standards)
- [Testing](#testing)
- [Pull Request Process](#pull-request-process)
- [Adding New Blockchains](#adding-new-blockchains)
- [Reporting Issues](#reporting-issues)

## Code of Conduct

### Our Pledge

We are committed to providing a welcoming and inclusive environment for all contributors, regardless of experience level, background, or identity.

### Expected Behavior

- Be respectful and considerate
- Welcome newcomers and help them get started
- Provide constructive feedback
- Focus on what is best for the community
- Show empathy towards other community members

### Unacceptable Behavior

- Harassment, discrimination, or offensive comments
- Trolling, insulting/derogatory comments, and personal attacks
- Publishing others' private information without permission
- Other conduct which could reasonably be considered inappropriate

## Getting Started

### Prerequisites

- PHP 8.1 or higher
- Composer
- Git
- BC Math PHP extension
- Basic understanding of blockchain technology

### Fork and Clone

1. Fork the repository on GitHub
2. Clone your fork locally:

```bash
git clone https://github.com/YOUR-USERNAME/multicryptoapi-blockbook.git
cd multicryptoapi-blockbook
```

3. Add the upstream repository:

```bash
git remote add upstream https://github.com/payex-id/multicryptoapi-blockbook.git
```

## Development Setup

### Install Dependencies

```bash
composer install
```

### Configure API Keys

Create a `.env` file or set environment variables:

```bash
export NODES_API_KEY="your-nownodes-key"
export INFURA_API_KEY="your-infura-key"
export TRONGRID_API_KEY="your-trongrid-key"
```

### Run Tests

```bash
./vendor/bin/phpunit
```

### Run Examples

```bash
php examples/bitcoin-blockbook.php
```

## Project Structure

```
src/
├── Api/              # High-level API clients
│   ├── BitcoinApiClient.php
│   ├── EthereumApiClient.php
│   ├── TronApiClient.php
│   └── Builder/      # Transaction builders
│
├── Provider/         # Blockchain data providers
│   ├── BitcoinProvider.php
│   ├── EthereumProvider.php
│   ├── TrxProvider.php
│   └── Abstract/
│
├── Stream/           # Real-time event monitoring
│   ├── BitcoinStream.php
│   ├── EthereumStream.php
│   ├── TronStream.php
│   └── Abstract/
│
├── Model/            # Data models and domain objects
│   ├── Transaction.php
│   ├── Address.php
│   ├── Block.php
│   └── Enum/
│
├── Factory/          # Factory pattern for client creation
│   ├── ApiFactory.php
│   └── ApiType.php
│
├── Contract/         # Interfaces
│   ├── ApiClientInterface.php
│   ├── ProviderInterface.php
│   └── StreamableInterface.php
│
├── Exception/        # Custom exceptions
│   └── MultiCryptoApiException.php
│
├── Util/             # Utility classes
│   └── EthUtil.php
│
└── Log/              # PSR-3 logging
    └── StdoutLogger.php
```

## Coding Standards

### PHP Standards

Follow [PSR-12](https://www.php-fig.org/psr/psr-12/) coding style.

#### Key Points

- Use 4 spaces for indentation (no tabs)
- Maximum line length: 120 characters
- Use type hints for all parameters and return types
- Use strict types: `declare(strict_types=1);`
- Use PHP 8.1+ features (enums, readonly properties, etc.)

### Naming Conventions

#### Classes

- PascalCase for class names
- Suffix interfaces with `Interface`
- Suffix abstract classes with `Abstract`
- Suffix exceptions with `Exception`

```php
// Good
class BitcoinApiClient { }
interface ApiClientInterface { }
abstract class ProviderAbstract { }
class NotEnoughFundsException extends \Exception { }

// Bad
class bitcoin_api { }
interface ApiClient { }
```

#### Methods and Variables

- camelCase for method and variable names
- Descriptive names (avoid abbreviations)

```php
// Good
public function getAddress(string $address): Address { }
$transactionId = $tx->txId;

// Bad
public function get_addr($a) { }
$txId = $tx->id;
```

#### Constants

- SCREAMING_SNAKE_CASE for constants

```php
// Good
const MAX_PAGE_SIZE = 1000;
const DEFAULT_DECIMALS = 18;

// Bad
const maxPageSize = 1000;
```

### Documentation

#### DocBlocks

All public methods must have DocBlocks:

```php
/**
 * Sends native coins to a recipient address.
 *
 * @param AddressCredentials $from Source wallet credentials
 * @param string $addressTo Recipient blockchain address
 * @param string $amount Amount to send in whole units (e.g., "0.5" BTC)
 * @param Fee|null $fee Optional custom fee specification
 * @return Transaction Transaction object with txId and confirmation status
 * @throws NotEnoughFundsException If wallet balance is insufficient
 * @throws IncorrectTxException If transaction building fails
 */
public function sendCoins(
    AddressCredentials $from,
    string $addressTo,
    string $amount,
    ?Fee $fee = null
): Transaction {
    // Implementation
}
```

#### Comments

- Use comments to explain "why", not "what"
- Keep comments up-to-date with code changes
- Remove commented-out code before committing

```php
// Good
// Use EIP-1559 pricing for better fee estimation
$fee = $this->calculateEip1559Fee();

// Bad
// Set fee variable
$fee = $this->calculateEip1559Fee();
```

### Type Safety

Always use type hints:

```php
// Good
public function getBalance(string $address): Amount { }

// Bad
public function getBalance($address) { }
```

Use readonly properties where applicable (PHP 8.1+):

```php
// Good
class Address {
    public function __construct(
        public readonly string $address,
        public readonly Amount $balance,
    ) {}
}
```

### Error Handling

Use exceptions for error conditions:

```php
// Good
if ($balance < $amount) {
    throw new NotEnoughFundsException(
        "Insufficient balance: {$balance} < {$amount}"
    );
}

// Bad
if ($balance < $amount) {
    return false;
}
```

### Precision Arithmetic

Always use BC Math for cryptocurrency amounts:

```php
// Good
$total = bcadd($amount1, $amount2, 8);
$fee = bcmul($feeRate, $txSize, 0);

// Bad
$total = $amount1 + $amount2;  // Float arithmetic loses precision
```

## Testing

### Writing Tests

Create tests in the `/test` directory matching the source structure:

```
test/
└── MultiCryptoApi/
    ├── Model/
    │   └── AmountTest.php
    ├── Factory/
    │   └── ApiFactoryTest.php
    └── Provider/
        └── BitcoinProviderTest.php
```

### Test Structure

```php
<?php

namespace MultiCryptoApi\Test\Model;

use PHPUnit\Framework\TestCase;
use MultiCryptoApi\Model\Amount;

class AmountTest extends TestCase
{
    public function testToBtcConversion(): void
    {
        $amount = new Amount('100000000', 8);
        $this->assertEquals('1.00000000', $amount->toBtc());
    }

    public function testToSatoshiConversion(): void
    {
        $amount = new Amount('1.5', 8);
        $this->assertEquals('150000000', $amount->toSatoshi());
    }
}
```

### Running Tests

```bash
# Run all tests
./vendor/bin/phpunit

# Run specific test
./vendor/bin/phpunit test/MultiCryptoApi/Model/AmountTest.php

# Run with coverage
./vendor/bin/phpunit --coverage-html coverage/
```

### Integration Tests

For testing with real blockchain data, use testnet credentials and mark as integration tests:

```php
/**
 * @group integration
 */
class BitcoinApiIntegrationTest extends TestCase
{
    public function testSendCoinsOnTestnet(): void
    {
        $credentials = new RpcCredentials(/* testnet config */);
        $bitcoin = ApiFactory::bitcoin($credentials);

        // Test with testnet
        $tx = $bitcoin->sendCoins(/* ... */);
        $this->assertNotEmpty($tx->txId);
    }
}
```

Run integration tests:

```bash
./vendor/bin/phpunit --group integration
```

## Pull Request Process

### Before Submitting

1. **Update from upstream:**

```bash
git fetch upstream
git rebase upstream/main
```

2. **Create a feature branch:**

```bash
git checkout -b feature/your-feature-name
```

3. **Make your changes:**
   - Follow coding standards
   - Add tests for new functionality
   - Update documentation

4. **Run tests:**

```bash
./vendor/bin/phpunit
```

5. **Commit your changes:**

```bash
git add .
git commit -m "Add feature: description of your changes"
```

Follow [Conventional Commits](https://www.conventionalcommits.org/):

```
feat: Add support for Polygon network
fix: Correct gas estimation for EIP-1559 transactions
docs: Update API reference with new methods
test: Add unit tests for Amount class
refactor: Simplify transaction builder logic
```

6. **Push to your fork:**

```bash
git push origin feature/your-feature-name
```

### Creating the Pull Request

1. Go to the original repository on GitHub
2. Click "New Pull Request"
3. Select your fork and branch
4. Fill in the PR template:

```markdown
## Description
Brief description of changes

## Motivation
Why is this change needed?

## Changes Made
- Added X feature
- Fixed Y bug
- Updated Z documentation

## Testing
How was this tested?

## Checklist
- [ ] Tests added/updated
- [ ] Documentation updated
- [ ] Follows coding standards
- [ ] No breaking changes (or documented)
```

### Review Process

- Maintainers will review your PR
- Address any feedback or requested changes
- Once approved, your PR will be merged

### After Merging

Delete your feature branch:

```bash
git branch -d feature/your-feature-name
git push origin --delete feature/your-feature-name
```

## Adding New Blockchains

To add support for a new blockchain:

### 1. Create API Client

```php
<?php

namespace MultiCryptoApi\Api;

use MultiCryptoApi\Contract\ApiClientInterface;
use MultiCryptoApi\Contract\ProviderInterface;

class NewChainApiClient implements ApiClientInterface
{
    public function __construct(
        private ProviderInterface $provider
    ) {}

    public function createWallet(): AddressCredentials { }
    public function createFromPrivateKey(string $privateKey): AddressCredentials { }
    public function sendCoins(/* ... */): Transaction { }
    public function sendAsset(/* ... */): Transaction { }
    public function validateAddress(string $address): bool { }
    public function provider(): ProviderInterface { }
    public function stream(): ?StreamableInterface { }
}
```

### 2. Create Provider

```php
<?php

namespace MultiCryptoApi\Provider;

use MultiCryptoApi\Provider\Abstract\ProviderAbstract;

class NewChainProvider extends ProviderAbstract
{
    public function getBlock(string $hash = 'latest'): Block { }
    public function getTx(string $txId): ?Transaction { }
    public function getAddress(string $address): Address { }
    // Implement other ProviderInterface methods
}
```

### 3. Create Transaction Builder

```php
<?php

namespace MultiCryptoApi\Api\Builder;

class NewChainTxBuilder
{
    public function build(/* params */): string {
        // Build and sign transaction
        // Return serialized transaction
    }
}
```

### 4. Optional: Create Stream

```php
<?php

namespace MultiCryptoApi\Stream;

use MultiCryptoApi\Stream\Abstract\AbstractStream;

class NewChainStream extends AbstractStream
{
    protected function connect(): void { }
    protected function subscribe(): void { }
    // Implement streaming logic
}
```

### 5. Update Factory

```php
// Factory/ApiType.php
enum ApiType {
    // ... existing
    case NewChain;
}

// Factory/ApiFactory.php
public static function factory(ApiType $type, RpcCredentials $credentials): ApiClientInterface
{
    return match($type) {
        // ... existing
        ApiType::NewChain => new NewChainApiClient(new NewChainProvider($credentials)),
    };
}

public static function newchain(RpcCredentials $credentials): NewChainApiClient
{
    return self::factory(ApiType::NewChain, $credentials);
}
```

### 6. Add Tests

```php
class NewChainApiClientTest extends TestCase
{
    public function testCreateWallet(): void { }
    public function testSendCoins(): void { }
}
```

### 7. Add Documentation

- Update README.md with new blockchain support
- Add examples in /examples directory
- Update docs/API_REFERENCE.md

### 8. Submit PR

Follow the pull request process above.

## Reporting Issues

### Bug Reports

When reporting bugs, include:

1. **Description:** Clear description of the bug
2. **Steps to Reproduce:** Minimal code to reproduce
3. **Expected Behavior:** What should happen
4. **Actual Behavior:** What actually happens
5. **Environment:**
   - PHP version
   - Operating system
   - Library version

### Feature Requests

When requesting features, include:

1. **Problem:** What problem does this solve?
2. **Proposed Solution:** How should it work?
3. **Alternatives:** Other solutions you've considered
4. **Use Case:** Real-world scenario

### Security Issues

**DO NOT** open public issues for security vulnerabilities.

Email security concerns to: **security@payex.id**

## Development Tips

### Debugging

Use the provided logger:

```php
use MultiCryptoApi\Log\StdoutLogger;

$logger = new StdoutLogger();
$logger->info('Transaction sent', ['txId' => $tx->txId]);
$logger->error('Failed to send', ['error' => $e->getMessage()]);
```

### Testing API Calls

Use testnet when developing:

```php
// Bitcoin Testnet
$credentials = new RpcCredentials(
    uri: 'https://btc-testnet.nownodes.io/',
    blockbookUri: 'https://btcbook-testnet.nownodes.io',
    headers: ['api-key' => getenv('NODES_API_KEY')]
);

// Ethereum Goerli
$credentials = new RpcCredentials(
    uri: 'https://eth-goerli.nownodes.io/',
    headers: ['api-key' => getenv('NODES_API_KEY')]
);
```

### Performance Profiling

```php
$start = microtime(true);

// Your code here
$result = $api->sendCoins(/* ... */);

$elapsed = microtime(true) - $start;
echo "Operation took {$elapsed} seconds\n";
```

## Questions?

- **Documentation:** See `/docs` directory
- **Examples:** See `/examples` directory
- **Issues:** [GitHub Issues](https://github.com/payex-id/multicryptoapi/issues)
- **Discussions:** [GitHub Discussions](https://github.com/payex-id/multicryptoapi/discussions)

## License

By contributing, you agree that your contributions will be licensed under the MIT License.

## Thank You!

Thank you for contributing to Multi Crypto API! Your contributions help make blockchain development more accessible to everyone.
