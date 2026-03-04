<?php

namespace Chikiday\MultiCryptoApi\Blockbook\Trait;

use Chikiday\MultiCryptoApi\Blockbook\EthereumBlockbook;
use Chikiday\MultiCryptoApi\Blockchain\Amount;
use Chikiday\MultiCryptoApi\Blockchain\Transaction;
use Chikiday\MultiCryptoApi\Blockchain\TransactionList;
use Chikiday\MultiCryptoApi\Blockchain\TxvInOut;
use Chikiday\MultiCryptoApi\Exception\MultiCryptoApiException;
use Chikiday\MultiCryptoApi\Model\TokenInfo;

/**
 * @mixin EthereumBlockbook
 */
trait EtherscanTrait
{
	public function hasEtherscanToken(): bool
	{
		return !empty($this->getEtherscanToken());
	}

	protected function getTransactionListViaEtherscan(string $address, int $page, int $pageSize): TransactionList
	{
		$list = $this->fetchViaEtherscanAddressTxs($address, $page, $pageSize);
		return new TransactionList($list, $page, 1);
	}

	protected function fetchViaEtherscanAddressTxs(string $address, int $page, int $pageSize): array
	{
		$address = strtolower($address);
		$tokens = $this->getAllowedTokens();
		$list = [];
		foreach ($tokens as $token) {
			try {
				$list = array_merge($list, $this->getErc20Txs($token, $address, $page, $pageSize));
			} catch (\Throwable $e) {
				continue;
			}
		}
		try {
			$normal = $this->getNormalAddressTxs($address, $page, $pageSize);
		} catch (\Throwable $e) {
			return [];
		}

		return array_merge($list, $normal);
	}

	protected function getErc20Txs(TokenInfo $tokenInfo, string $address, int $page, int $pageSize): array
	{
		$data = $this->etherscanApiQuery([
			'module'          => 'account',
			'action'          => 'tokentx',
			'contractaddress' => $tokenInfo->contract,
			'address'         => $address,
			'page'            => $page,
			'offset'          => $pageSize,
			'startblock'      => 0,
			'endblock'        => 'latest',
			'sort'            => 'desc',
		]);

		$txs = [];
		foreach ($data as $tx) {
			$asset = $tokenInfo->toAsset($tx['value'], $tx['from'], $tx['to']);

			$txs[] = new Transaction(
				$tx['hash'],
				$tx['blockNumber'],
				$tx['confirmations'],
				$tx['timeStamp'],
				[
					new TxvInOut(
						$tx['from'],
						Amount::satoshi("0", $this->getDecimals()),
						$tx['transactionIndex'],
						[$asset],
					),
				],
				[
					new TxvInOut(
						$tx['to'],
						Amount::satoshi("0", $this->getDecimals()),
						$tx['transactionIndex'],
						[$asset],
					),
				],
				"0",
				$tx
			);
		}

		return $txs;
	}

	protected function getNormalAddressTxs(string $address, int $page, int $pageSize): array
	{
		$data = $this->etherscanApiQuery([
			'module'     => 'account',
			'action'     => 'txlist',
			'address'    => $address,
			'page'       => $page,
			'offset'     => $pageSize,
			'startblock' => 0,
			'endblock'   => 'latest',
			'sort'       => 'desc',
		]);

		$txs = [];
		foreach ($data as $tx) {
			if (!empty($tx['functionName'])) {
				continue;
			}
			$amount = Amount::satoshi($tx['value'], $this->getDecimals());

			$txs[] = new Transaction(
				$tx['hash'],
				$tx['blockNumber'],
				$tx['confirmations'],
				$tx['timeStamp'],
				[
					new TxvInOut(
						$tx['from'],
						$amount,
						$tx['transactionIndex'],
					),
				],
				[
					new TxvInOut(
						$tx['to'],
						$amount,
						$tx['transactionIndex'],
					),
				],
				"0",
				$tx,
				!$tx['isError']
			);
		}

		return $txs;
	}

	protected function etherscanApiQuery(array $params): array
	{
		if (!$token = $this->getEtherscanToken()) {
			throw new MultiCryptoApiException("EtherscanApiKey is required");
		}

		$params = array_merge([
			'chainId' => $this->chainId,
			'apikey'  => $token,
		], $params);

		$url = "https://api.etherscan.io/v2/api?" . http_build_query($params);

		$response = $this->http()->get($url)->getBody()->getContents();

		$data = json_decode($response, true);

		if ($data['status'] == 1) {
			return $data['result'] ?? [];
		}

		if (str_contains($data['message'], 'No transactions found')) {
			return [];
		}

		throw new \Exception("Etherscan API error: " . $data['message']);
	}

	protected function getEtherscanToken(): mixed
	{
		return $this->getOption('etherscanApiKey');
	}
}