<?php

namespace MultiCryptoApi\Model;

use MultiCryptoApi\Blockchain\Amount;
use MultiCryptoApi\Blockchain\Asset;

readonly class TokenInfo implements \JsonSerializable
{
	public function __construct(
		public string $contract,
		public string $name,
		public string $symbol,
		public int    $decimals,
		public string $type,
	)
	{
	}

	public static function import(array $data): self
	{
		return new self(
			contract: $data['contract'],
			name: $data['name'],
			symbol: $data['symbol'],
			decimals: $data['decimals'],
			type: $data['type'] ?? 'erc20'
		);
	}

	// add import method

	public function jsonSerialize(): mixed
	{
		return [
			'contract' => $this->contract,
			'name'     => $this->name,
			'symbol'   => $this->symbol,
			'decimals' => $this->decimals,
			'type'     => $this->type,
		];
	}

	public function toAssetByLog(EthTransferLog $log): Asset
	{
		return $this->toAsset(
			$log->value,
			$log->to,
			$log->from
		);
	}

	public function toAsset(string $amount, string $from, string $to): Asset
	{
		return new Asset(
			$this->type,
			$this->contract,
			Amount::satoshi($amount, $this->decimals),
			$this->name,
			$this->symbol,
			[
				'from' => $from,
				'to'   => $to,
			]
		);
	}
}