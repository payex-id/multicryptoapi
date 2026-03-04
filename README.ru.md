# Multi Crypto API

Универсальная PHP библиотека для работы с множественными криптовалютными блокчейнами через единый API интерфейс.

## 🚀 Возможности

- **Мультиблокчейн поддержка**: Bitcoin, Litecoin, Dogecoin, Dash, Zcash, Ethereum, BSC, Tron
- **Единый API интерфейс** для всех поддерживаемых блокчейнов
- **Работа с транзакциями**: получение, отправка, отслеживание
- **Работа с токенами**: ERC-20, TRC-20 и другие стандарты
- **Стриминг данных** в реальном времени (для EVM блокчейнов и Tron)
- **Fallback механизмы**: автоматическое переключение между источниками данных (Blockbook → RPC → Etherscan)
- **Гибкая конфигурация**: настраиваемые параметры поиска и лимиты

## 📦 Установка

```bash
composer require payex-id/multicryptoapi
```

## 🔧 Требования

- PHP 8.0+
- Расширение `bcmath`
- API ключи для провайдеров (NowNodes, Etherscan, TronScan и др.)

## 📚 Поддерживаемые блокчейны

### UTXO-based блокчейны
- **Bitcoin** (BTC)
- **Litecoin** (LTC)
- **Dogecoin** (DOGE)
- **Dash** (DASH)
- **Zcash** (ZEC)

### EVM-based блокчейны
- **Ethereum** (ETH)
- **BNB Smart Chain** (BSC/BNB)

### Другие
- **Tron** (TRX)

## 🎯 Быстрый старт

### Пример работы с Ethereum

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

// Получение последнего блока
$block = $blockbook->getBlock();
echo "Block: {$block->height}\n";

// Получение транзакции
$tx = $blockbook->getTx('0x...');
echo "TX: {$tx->txid}\n";

// Получение адреса
$addr = $blockbook->getAddress('0x...');
echo "Balance: {$addr->balance->toBtc()}\n";

// Получение транзакций адреса
$txs = $blockbook->getAddressTransactions('0x...');
foreach ($txs->transactions as $tx) {
	echo "TX: {$tx->txid}\n";
}
```

### Пример работы с Bitcoin

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

// Создание кошелька
$wallet = $api->createWallet();
echo "Address: {$wallet->address}\n";
echo "Private Key: {$wallet->privateKey}\n";

// Отправка транзакции
$tx = $api->sendCoins(
	$wallet,
	'recipient_address',
	'0.001'
);
echo "TX sent: {$tx->txid}\n";
```

## 🏗️ Архитектура

Библиотека построена на основе следующих компонентов:

### Blockbook
Интерфейс для работы с блокчейн данными:
- `getBlock()` - получение блока
- `getTx()` - получение транзакции
- `getAddress()` - получение информации об адресе
- `getAddressTransactions()` - получение списка транзакций адреса
- `getAssets()` - получение токенов адреса
- `pushRawTransaction()` - отправка сырой транзакции

### API Client
Высокоуровневый клиент для операций:
- `createWallet()` - создание нового кошелька
- `createFromPrivateKey()` - создание кошелька из приватного ключа
- `sendCoins()` - отправка нативных монет
- `sendAsset()` - отправка токенов
- `validateAddress()` - валидация адреса

### Stream
Потоковая обработка данных в реальном времени:
- Подписка на новые блоки
- Отслеживание транзакций
- Обработка событий

> **⚠️ Примечание:** Стриминг работает для EVM блокчейнов (Ethereum, BSC) и Tron. Для BTC-like блокчейнов (Bitcoin, Litecoin, Dogecoin и др.) стриминг не реализован и не тестировался.

## ⚙️ Конфигурация

### Ethereum/BSC RPC настройки

Для работы с Ethereum/BSC через RPC можно настроить следующие параметры:

```php
$blockbook = new EthereumRpc($credentials, [
	// Глубина поиска ERC-20 транзакций (в блоках)
	'erc20Lookback' => 50000,
	
	// Размер окна для одного запроса eth_getLogs (в блоках)
	'erc20BlocksPerQuery' => 9500,
	
	// Глубина поиска через trace_filter (в блоках)
	'traceLookback' => 20000,
	
	// API ключ Etherscan (для fallback)
	'etherscanApiKey' => 'YOUR_ETHERSCAN_API_KEY',
	
	// Список поддерживаемых токенов
	'tokens' => [
		'0x...', // USDT
		'0x...', // USDC
	]
]);
```

### Fallback механизм

Библиотека автоматически переключается между источниками данных:

1. **Etherscan API** (основной) - быстрый и пагинированный
2. **RPC eth_getLogs** (fallback для токенов) - при недоступности Etherscan
3. **RPC trace_filter** (fallback для ETH транзакций) - при недоступности Etherscan

## 📖 Примеры использования

Полный набор примеров находится в директории `examples/`:

- `*-blockbook.php` - работа с блокчейн данными
- `*-send-*.php` - отправка транзакций
- `*-stream.php` - потоковая обработка
- `*-wallet.php` - работа с кошельками

## 🔑 API ключи

Для работы библиотеки необходимы API ключи от следующих провайдеров:

- **NowNodes** - https://nownodes.io/ (RPC и Blockbook)
- **Etherscan** - https://etherscan.io/ (для Ethereum/BSC)
- **TronScan** - https://tronscan.org/ (для Tron)
- **Infura** - https://infura.io/ (WebSocket соединения)

Настройка ключей описана в `examples/README.md`.

## 🧪 Тестирование

```bash
# Запуск тестов
vendor/bin/phpunit
```

## 📝 Лицензия

MIT License

## 👤 Автор

**Chikiday**
- Telegram: @chikiday

## 🤝 Вклад в проект

Приветствуются Pull Request'ы и Issues.

## 📚 Дополнительная документация

- [Примеры использования](examples/README.md)
- [Factory API](src/Chikiday/MultiCryptoApi/Factory/)
- [Интерфейсы](src/Chikiday/MultiCryptoApi/Interface/)

## ⚠️ Важные замечания

- При работе с большими диапазонами блоков (например, BSC) учитывайте лимиты провайдеров
- Для production использования рекомендуется настроить кэширование
- При работе с приватными ключами соблюдайте меры безопасности
- Некоторые функции требуют архивных нод (archive nodes)
- **Стриминг данных не работает и не тестировался для BTC-like блокчейнов** (Bitcoin, Litecoin, Dogecoin, Dash, Zcash)

## 🔄 Changelog

### Последние изменения

- Добавлен fallback механизм для получения транзакций через RPC
- Поддержка trace_filter для EVM блокчейнов
- Настраиваемые параметры поиска для ERC-20 токенов
- Улучшена обработка ошибок и retry логика
