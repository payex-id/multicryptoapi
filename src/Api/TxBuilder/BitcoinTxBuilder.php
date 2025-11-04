<?php

namespace MultiCryptoApi\Api\TxBuilder;

use BitWasp\Bitcoin\Address\AddressCreator;
use BitWasp\Bitcoin\Exceptions\UnrecognizedAddressException;
use BitWasp\Bitcoin\Key\Factory\PrivateKeyFactory;
use BitWasp\Bitcoin\Script\ScriptFactory;
use BitWasp\Bitcoin\Script\WitnessScript;
use BitWasp\Bitcoin\Transaction\Factory\SignData;
use BitWasp\Bitcoin\Transaction\Factory\Signer;
use BitWasp\Bitcoin\Transaction\OutPoint;
use BitWasp\Bitcoin\Transaction\TransactionFactory;
use BitWasp\Bitcoin\Transaction\TransactionInterface;
use BitWasp\Bitcoin\Transaction\TransactionOutput;
use BitWasp\Buffertools\Buffer;
use BitWasp\Buffertools\Buffertools;
use MultiCryptoApi\Provider\BitcoinBlockbook;
use MultiCryptoApi\Blockchain\AddressCredentials;
use MultiCryptoApi\Blockchain\Amount;
use MultiCryptoApi\Blockchain\Fee;
use MultiCryptoApi\Blockchain\RawTransaction;
use MultiCryptoApi\Blockchain\TxOutput;
use MultiCryptoApi\Blockchain\UTXO;
use MultiCryptoApi\Exception\NotEnoughFundsException;
use MultiCryptoApi\Exception\NotEnoughFundsToCoverFeeException;
use Dict\Currency;

readonly class BitcoinTxBuilder
{
	public function __construct(
		private BitcoinBlockbook $blockbook,
	) {
	}

	public function singleOutput(AddressCredentials $from, string $addressTo, string $amount, ?Fee $fee = null, ): RawTransaction
	{
		$utxo = $this->blockbook->getUTXO($from->address);
		array_map(fn($_item) => $_item->setCredentials($from), $utxo);

		$tx = $this->build($utxo, [
			new TxOutput($addressTo, Amount::value($amount)->satoshi),
		], $fee);

		return $tx;
	}

	/**
	 * @param AddressCredentials[] $from
	 * @param array $to
	 * @param Fee|null $fee
	 * @return RawTransaction
	 */
	public function manyInputOutput(array $from, array $to, ?Fee $fee = null, ): RawTransaction
	{
		$input = [];

		foreach ($from as $wallet) {
			$_utxo = $this->blockbook->getUTXO($wallet->address);
			array_map(fn($_item) => $_item->setCredentials($wallet), $_utxo);
			$input = array_merge($input, $_utxo);
		}

		$i = 0;
		$output = [];
		foreach ($to as $address => $value) {
			$value = Amount::value($value)->satoshi;
			$output[] = new TxOutput($address, $value, $i++);
		}

		$tx = $this->build($input, $output, $fee);

		return $tx;
	}

	/**
	 * Spend UNSPENT txes. If UTXO bigger than we need - we wont use it
	 *
	 * @param Currency $currency
	 * @param UTXO[] $utxos
	 * @param TxOutput[] $outputs
	 * @param Fee $fee
	 * @return RawTransaction
	 */
	public function build(array $utxos, array $outputs, ?Fee $fee = null): RawTransaction
	{
		$amount = $this->getTotalAmount($outputs);

		// собираем базовые инпуты для оплаты
		$inputs = $this->addInputs($utxos, $amount);

		// смотрим, сколько остается на комсе
		$totalFee = $this->getTotalAmount($inputs) - $amount;

		// считаем, а сколько реально требуется
		$actualFee = $this->getMinimalFee($inputs, $outputs, true);

		// Выбираем комсу
		$feeAmount = $fee?->fee?->satoshi ?? $actualFee;

		// min fee
		$feeAmount = max($fee?->getMinFee()?->satoshi ?? 0, $feeAmount);

		// если комса вычитается из отправляемой суммы
		if ($fee?->isSubtractFromAmount()) {
			$this->subtractFeeFromOutputs($outputs, $feeAmount);
		} else {
			if ($totalFee < $feeAmount) {
				// если инпутов недостаточно, чтобы покрыть комсу, добавляем еще инпуты
				$inputs += $this->addInputs($utxos, $feeAmount - $totalFee, array_keys($inputs));
			}
		}

		// если имеется переплата, то добавим возвратный адрес
		if ($changeOutput = $this->considerChange($inputs, $outputs, $feeAmount)) {
			$outputs[] = $changeOutput;
		}

		$tx = $this->createTx($inputs, $outputs);

		return new RawTransaction($tx->getHex(), $tx->getTxId()->getHex(), $inputs, $outputs, $feeAmount);
	}

	/**
	 * @param UTXO[]|TxOutput[] $inputs
	 * @return int
	 */
	public function getTotalAmount(array $inputs): int
	{
		return array_reduce($inputs, fn($total, $tx) => bcadd($total, $tx->value), 0);
	}

	/**
	 * @param UTXO[] $utxos
	 * @param int $amountSatoshi
	 * @param array $excludeUTXOs
	 * @return UTXO[]
	 * @throws NotEnoughFundsException
	 */
	protected function addInputs(array $utxos, $amountSatoshi, array $excludeUTXOs = []): array
	{
		$added = 0;
		$inputs = [];

		// confirmed outputs use first
		usort($utxos, function (UTXO $a, UTXO $b) use ($amountSatoshi) {
			$aConfirmations = min(6, $b->confirmations);
			$bConfirmations = min(6, $a->confirmations);

			// сначала сопртируем по кол-ву подтверждений (максимум 6)
			if ($aConfirmations != $bConfirmations) {
				return $aConfirmations <=> $bConfirmations;
			}

			$aGreater = $a->value > $amountSatoshi;
			$bGreater = $b->value > $amountSatoshi;

			// если оба инпута больше требуемого, то возьмем тот инпут, что поменьше
			if ($aGreater && $bGreater) {
				return $a->value <=> $b->value;
			}

			// либо сортируем по размеру (сначала более ценные)
			return $b->value <=> $a->value;
		});

		foreach ($utxos as $UTXO) {
			if (isset($inputs[$UTXO->txid])) {
				continue;
			}

			if (in_array($UTXO->txid, $excludeUTXOs)) {
				continue;
			}

			$inputs[$UTXO->txid] = $UTXO;
			$added = bcadd($added, $UTXO->value); // сколько мы отправим

			if ($added >= $amountSatoshi) { // если мы "вобрали" достаточно транзакций чтобы покрыть требуемую сумму
				break;
			}
		}

		if ($added < $amountSatoshi) {
			throw new NotEnoughFundsException(
				"Required amount is bigger than utxos have, " .
				"need {$amountSatoshi}, have {$added}, need more " . ($amountSatoshi - $added)
			);
		}

		return $inputs;
	}

	protected function getMinimalFee(array $inputs, array $outputs, bool $considerChangeOutput): int
	{
		if ($considerChangeOutput) {
			/** @var UTXO $firstInput */
			$firstInput = reset($inputs);
			$overPayment = $this->getOverPayment($inputs, $outputs);
			$feeOutput = new TxOutput($firstInput->credentials()->address, $overPayment);

			$testOutputs = array_merge($outputs, [$feeOutput]);
		} else {
			$testOutputs = array_values($outputs);
		}

		// calculate real fee for vSize
		return $this->getFeeAtTxVSize($inputs, $testOutputs);
	}

	/**
	 * @param $inputs TxInput[]
	 * @param $outputs TxOutput[]
	 */
	private function getOverPayment(array $inputs, array $outputs)
	{
		$totalPayment = $this->getTotalAmount($inputs);

		$totalNeed = 0;
		foreach ($outputs as $output) {
			$totalNeed = bcadd($totalNeed, $output->value);
		}

		$overPayment = $totalPayment - $totalNeed;

		return $overPayment;
	}

	private function getFeeAtTxVSize(array $inputs, array $outputs)
	{
		$feeRate = $this->blockbook->getAvgFeePerKb();

		$vSize = $this->getVSize($this->createTx($inputs, $outputs));
		$feeAtVSize = intval($vSize * $feeRate / 1024);

		return $feeAtVSize;
	}

	private function getVSize(TransactionInterface $tx)
	{
		$fields = [];
		$fields[] = [4 * 4, "version {$tx->getVersion()}"];
		$fields[] = [4 * Buffertools::numToVarInt(count($tx->getInputs()))->getSize(), 'nIn'];
		if ($tx->hasWitness()) {
			$fields[] = [2, "segwit markers 0001"];
		}
		foreach ($tx->getInputs() as $input) {
			$script = $input->getScript();
			$scriptSize = $script->getBuffer()->getSize();
			$scriptVarInt = Buffertools::numToVarInt($scriptSize);
			$fields[] = [4 * 32, "\ttxid\t" . $input->getOutPoint()->getTxId()->getHex()];
			$fields[] = [4 * 4, "\tvout\t" . $input->getOutPoint()->getVout()];
			$fields[] = [4 * $scriptVarInt->getSize(), "\tvarint\t" . $scriptVarInt->getHex()];
			$fields[] = [4 * $scriptSize, "\tscript\t" . $input->getScript()->getHex()];
			$fields[] = [4 * 4, "\tseq\t" . $input->getSequence()];
		}
		$fields[] = [Buffertools::numToVarInt(count($tx->getOutputs()))->getSize(), 'nOut'];
		foreach ($tx->getOutputs() as $output) {
			$script = $output->getScript();
			$scriptSize = $script->getBuffer()->getSize();
			$scriptVarInt = Buffertools::numToVarInt($scriptSize);
			$fields[] = [4 * 8, "\tvalue\t{$output->getValue()}"];
			$fields[] = [4 * $scriptVarInt->getSize(), "\tvarint {$scriptVarInt->getHex()}\t"];
			$fields[] = [4 * $scriptSize, "\tscript\n{$script->getHex()}"];
		}
		if ($tx->hasWitness()) {
			for ($i = 0; $i < count($tx->getInputs()); $i++) {
				$wit = $tx->getWitness($i);
				$fields[] = [Buffertools::numToVarInt(count($wit))->getSize(), "wit {$i}"];
				if ($wit->count() > 0) {
					foreach ($wit->all() as $value) {
						$fields[] =
							[
								Buffertools::numToVarInt($value->getSize())->getSize() + $value->getSize(),
								"\tvalue\t{$value->getHex()}",
							];
					}
				}
			}
		}
		$fields[] = [4 * 4, "lockTime {$tx->getLockTime()}"];

		$totalIn = 0;
		foreach ($fields as $field) {
			$totalIn += $field[0];
		}

		return ceil($totalIn / 4);
	}

	protected function createTx(array $inputs, array $output): TransactionInterface
	{
		$isSegWit = in_array($this->blockbook->network->getSegwitBech32Prefix() , ['bc', 'tb']);
		if ($isSegWit) {
			return $this->createTxSegWit($inputs, $output);
		} else {
			return $this->createTxBase58($inputs, $output);
		}
	}

	/**
	 * @param UTXO[] $inputs
	 * @param TxOutput[] $output
	 * @return TransactionInterface
	 * @throws UnrecognizedAddressException
	 */
	protected function createTxSegWit(array $inputs, array $output): TransactionInterface
	{
		$network = $this->blockbook->network;

		$privKeyFactory = new PrivateKeyFactory();

		$addrCreator = new AddressCreator();
		$transaction = TransactionFactory::build();


		// Spend from P2PKH
		foreach ($inputs as $item) {
			$transaction->spendOutPoint(
				new OutPoint(Buffer::hex($item->txid, 32), $item->vout)
			);
		}

		foreach ($output as $txOutput) {
			$transaction->payToAddress($txOutput->value, $addrCreator->fromString($txOutput->getAddress(), $network));
		}

		$tx = $transaction->get();

		$signer = new Signer($tx);
		$i = 0;

		foreach ($inputs as $item) {
			$privateKey = $privKeyFactory->fromHexCompressed($item->credentials()->privateKey);
			$witnessScript = new WitnessScript(ScriptFactory::scriptPubKey()->payToPubKeyHash($privateKey->getPubKeyHash()));

			$signData = (new SignData())->p2wsh($witnessScript);
			$program = ScriptFactory::scriptPubKey()->p2wkh($privateKey->getPubKeyHash());

			$outpoint = new TransactionOutput($item->value, $program);
			$input = $signer->input($i++, $outpoint/*, $signData*/);
			$input->sign($privateKey);
		}

		return $signer->get();
	}

	/**
	 * @param UTXO[] $inputs
	 * @param TxOutput[] $output
	 * @return TransactionInterface
	 * @throws UnrecognizedAddressException
	 */
	protected function createTxBase58(array $inputs, array $output): TransactionInterface
	{
		$network = $this->blockbook->network;

		$privKeyFactory = new PrivateKeyFactory();

		$addrCreator = new AddressCreator();
		$transaction = TransactionFactory::build();

		foreach ($inputs as $item) {
			$transaction->input($item->txid, $item->vout, null);
		}

		foreach ($output as $txOutput) {
			$transaction->payToAddress($txOutput->getValue(), $addrCreator->fromString($txOutput->getAddress(), $network));
		}

		$signer = new Signer($transaction->get());
		$i = 0;
		$outputScriptFactory = ScriptFactory::scriptPubKey();

		foreach ($inputs as $item) {
			$privateKey = $privKeyFactory->fromHexCompressed($item->credentials()->privateKey);
			$txOut = new TransactionOutput(
				$item->value,
				$outputScriptFactory->payToPubKeyHash($privateKey->getPubKeyHash())
			);

			$input = $signer->input($i++, $txOut);
			$input->sign($privateKey);
		}

		return $signer->get();
	}

	/**
	 * @param TxOutput[] $outputs
	 * @param int $feeAmount
	 */
	private function subtractFeeFromOutputs(array &$outputs, int $feeAmount): void
	{
		foreach ($outputs as $output) {
			if ($output->value < $feeAmount) {
				continue;
			}

			$output->reduceValue($feeAmount);
			$subtracted = true;
			break;
		}

		if (!isset($subtracted)) {
			$total = array_sum(array_column($outputs, 'value'));

			throw new NotEnoughFundsToCoverFeeException(
				"Can't subtract fee from output, because output lower than fee: " .
				"Fee: {$feeAmount} sat, outputs total: {$total} sat"
			);
		}
	}

	/**
	 * @param UTXO[] $inputs
	 * @param TxOutput[] $outputs
	 * @param int $fee
	 * @return TxOutput|null
	 */
	protected function considerChange(array $inputs, array $outputs, int $fee): ?TxOutput
	{
		$overPayment = $this->getOverPayment($inputs, $outputs);
		$change = $overPayment - $fee;

		$slipPage = 1000;
		if ($change <= $slipPage) {
			return null;
		}

		$firstInput = array_shift($inputs);
		$feeOutput = new TxOutput($firstInput->credentials()->address, $change);

		return $feeOutput;
	}

}