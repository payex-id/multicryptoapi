<?php

// Загружаем .env файл, если он существует
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
	$lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	foreach ($lines as $line) {
		// Пропускаем комментарии
		if (strpos(trim($line), '#') === 0) {
			continue;
		}
		
		// Парсим переменные в формате KEY=VALUE
		if (strpos($line, '=') !== false) {
			list($key, $value) = explode('=', $line, 2);
			$key = trim($key);
			$value = trim($value);
			
			// Убираем кавычки, если они есть
			$value = trim($value, '"\'');
			
			// Устанавливаем переменную окружения, если она еще не установлена
			if (!getenv($key)) {
				putenv("$key=$value");
			}
		}
	}
}

return [
	'NowNodes' => getenv('NODES_API_KEY'),
	'TronScan' => getenv('TRONSCAN_API_KEY'),
	'Infura' => getenv('INFURA_API_KEY'),
	'TronGridApiKey' => getenv('TRONGRID_API_KEY'),
	'Etherscan' => getenv('ETHERSCAN_API_KEY'),
	'QuickNode' => getenv('QUICK_NODES_API_KEY'),
];
