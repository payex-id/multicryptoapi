<?php

namespace MultiCryptoApi\Blockchain;

readonly class RpcCredentials
{
	public function __construct(
		public string $uri,
		public string $blockbookUri,
		public array $headers = [],
		public ?string $username = null,
		public ?string $password = null,
	) {
	}


	public function getHeaders(): array
	{
		return $this->headers + $this->defaultHeaders();
	}

	public function getFlatHeaders(): array
	{
		foreach ($this->getHeaders() as $key => $value) {
			$headers[] = "{$key}: $value";
		}

		return $headers ?? [];
	}

	private function defaultHeaders(): array
	{
		$headers = [
			'Content-Type' => 'application/json',
			'Accept' => '*/*',
		];

		if ($this->username && $this->password) {
			$headers['Authorization'] = 'Basic ' . base64_encode($this->username . ':' . $this->password);
		}

		return $this->headers + $headers;
	}
}