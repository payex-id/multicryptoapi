<?php

namespace Chikiday\MultiCryptoApi\Blockbook;

use BitWasp\Bitcoin\Network\NetworkInterface;
use BitWasp\Bitcoin\Network\Networks\Bitcoin;
use BitWasp\Bitcoin\Network\Networks\Dash;
use BitWasp\Bitcoin\Network\Networks\Dogecoin;
use BitWasp\Bitcoin\Network\Networks\Litecoin;
use BitWasp\Bitcoin\Network\Networks\Zcash;
use Chikiday\MultiCryptoApi\Blockbook\Abstract\BlockbookAbstract;
use Chikiday\MultiCryptoApi\Blockchain\RpcCredentials;
use JsonRPC\Client;
use Override;

class BitcoinBlockbook extends BlockbookAbstract
{
	protected int $decimals = 8;
	protected string $name = 'Bitcoin';
	protected string $symbol = 'BTC';
	private Client $rpc;

	public function __construct(
		public RpcCredentials   $credentials,
		public NetworkInterface $network,
		protected array         $options = [],
	) {
		$this->rpc = new Client($this->credentials->uri);
		parent::__construct($credentials, $options);
	}

	public function getSymbol(): string
	{
		return match (get_class($this->network)) {
			Bitcoin::class => 'BTC',
			Litecoin::class => 'LTC',
			Dash::class => 'DASH',
			Dogecoin::class => 'DOGE',
			Zcash::class => 'ZEC',
		};
	}

	public function getName(): string
	{
		return match (get_class($this->network)) {
			Bitcoin::class => 'Bitcoin',
			Litecoin::class => 'Litecoin',
			Dash::class => 'Dash',
			Dogecoin::class => 'Dogecoin',
			Zcash::class => 'Zcash',
		};
	}

	public function getAvgFeePerKb(int|string|null $lastBlock = null): int
	{
		$lastBlock ??= $this->jsonRpc('getbestblockhash');
		$data = $this->jsonRpc('getblockstats', [$lastBlock, ['avgfeerate']]);

		return $data['avgfeerate'] ?? 1;
	}

	private function jsonRpc(string $method, array $params = []): mixed
	{
		$_headers = $this->credentials->getFlatHeaders();

		return $this->rpc->execute($method, $params, [], null, $_headers);
	}

	#[Override] public function getAssets(string $address): array
	{
		return [];
	}
}