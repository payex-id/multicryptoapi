# Примеры использования Multi Crypto API

Эта папка содержит примеры использования Multi Crypto API для различных блокчейнов.

## Настройка API-ключей

Перед запуском примеров необходимо настроить переменные окружения с API-ключами:

### Необходимые API-ключи:

1. **NowNodes API Key** - для доступа к блокчейн нодам
   - Получить можно на: https://nownodes.io/
   - Переменная окружения: `NODES_API_KEY`

2. **TronScan API Key** - для работы с TRON блокчейном  
   - Получить можно на: https://tronscan.org/
   - Переменная окружения: `TRONSCAN_API_KEY`

3. **Infura API Key** - для WebSocket соединений с Ethereum/BSC
   - Получить можно на: https://infura.io/
   - Переменная окружения: `INFURA_API_KEY`

4. **TronGrid API Key** - для работы с TRON Grid API
   - Получить можно на: https://www.trongrid.io/
   - Переменная окружения: `TRONGRID_API_KEY`

### Способы настройки:

#### Вариант 1: Переменные окружения
```bash
export NODES_API_KEY="your_nownodes_api_key"
export TRONSCAN_API_KEY="your_tronscan_api_key"  
export INFURA_API_KEY="your_infura_api_key"
export TRONGRID_API_KEY="your_trongrid_api_key"
```

#### Вариант 2: .env файл
Создайте файл `.env` в корне проекта:
```
# API Keys for Crypto Multi API Examples
# NowNodes API Key - get from: https://nownodes.io/
NODES_API_KEY=your_nownodes_api_key

# TronScan API Key - get from: https://tronscan.org/
TRONSCAN_API_KEY=your_tronscan_api_key

# Infura API Key - get from: https://infura.io/
INFURA_API_KEY=your_infura_api_key

# TronGrid API Key - get from: https://www.trongrid.io/
TRONGRID_API_KEY=your_trongrid_api_key
```

**Примечание:** Файл `keys.php` автоматически загружает `.env` файл, если он существует. Системные переменные окружения имеют приоритет над переменными из `.env` файла.

## Запуск примеров

После настройки API-ключей можно запускать любой пример:

```bash
php examples/bitcoin-blockbook.php
php examples/eth-blockbook.php
php examples/eth-gasprice.php
php examples/eth-eip1559-fees.php
php examples/trx-blockbook.php
php examples/trx-energy-info.php <address>
php examples/trx-blockbook-healthcheck.php <address> [<creditor_address>]
php examples/trx-reclaim-info.php <creditor_address> <invoice_address>
```

## Структура файлов

- `keys.php` - файл для загрузки API-ключей из переменных окружения
- `*-blockbook.php` - примеры работы с блокчейн данными
- `eth-gasprice.php` / `eth-eip1559-fees.php` - оценка газа (legacy и EIP-1559)
- `*-send-*.php` - примеры отправки транзакций
- `*-stream.php` - примеры работы с потоками данных в реальном времени 