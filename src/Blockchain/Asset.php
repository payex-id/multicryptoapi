<?php

namespace MultiCryptoApi\Blockchain;


class Asset implements \JsonSerializable
{
	public function __construct(
		public string $type,
		public string $tokenId,
		public float|Amount $balance,
		public string $name,
		public string $abbr,
		public array $payload = [],
	) {
	}

	public static function import(array $json): self
	{
		return new Asset(
			$json['type'],
			$json['tokenId'],
			Amount::import($json['balance']),
			$json['name'],
			$json['abbr'],
			$json['payload']
		);
	}

	public function getFrom(): ?string
	{
		return $this->payload['from'] ?? null;
	}

	public function getTo(): ?string
	{
		return $this->payload['to'] ?? null;
	}

	public function withAmount(string|Amount $amount): self
	{
		$this->balance = $amount;

		return $this;
	}

	#[\Override] public function jsonSerialize(): mixed
	{
		return [
			'type'    => $this->type,
			'tokenId' => $this->tokenId,
			'balance' => $this->balance,
			'name'    => $this->name,
			'abbr'    => $this->abbr,
			'payload' => $this->payload,
		];
	}
}