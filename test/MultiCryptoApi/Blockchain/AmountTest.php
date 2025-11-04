<?php

namespace MultiCryptoApi\Blockchain;

use PHPUnit\Framework\TestCase;


class AmountTest extends TestCase
{
	public static function getValues()
	{
		return [
			['0.01000000', 8],
			['1', 8],

			['1', 18],
		];
	}

	public static function getSatoshis()
	{
		return [
			['1000000', 8],
			['1000', 8],

			['1', 18],
		];
	}

	/**
	 * @param string $btc
	 * @param int $decimals
	 * @dataProvider getValues
	 */
	public function testValue(string $btc, int $decimals)
	{
		$amount = Amount::value($btc, $decimals);
		$this->assertEqualsWithDelta((float)$btc, (float)$amount->toBtc(), 0.0000001);
		$this->assertEquals(bcmul($btc, 10 ** $decimals), $amount->satoshi);
	}


	public function testLargePrecision()
	{
		$amount = Amount::value(0.04746812, 18);
		$this->assertEquals("47468120000000000", $amount->satoshi);
		$this->assertEquals("0.047468120000000000", $amount->toBtc());

		$amount = Amount::value("0.047468120000000009", 18);
		$this->assertEquals("47468120000000009", $amount->satoshi);

		$amount = Amount::value(0.047468120000000019, 18);
		// cause php's floats has only 16 precision
		$this->assertEquals("47468120000000000", $amount->satoshi);
	}

	public function testExp()
	{
		$amount = Amount::value(1e-6, 18);
		$this->assertEquals(str_pad("0.000001", 20, "0"), $amount->toBtc());

		$amount = Amount::value(1e-16, 18);
		$this->assertEquals(str_pad("0.0000000000000001", 20, "0"), $amount->toBtc());

		$amount = Amount::value(1e-18, 18);
		$this->assertEquals(str_pad("0.000000000000000001", 20, "0"), $amount->toBtc());
	}

	/**
	 * @param string $satoshi
	 * @param int $decimals
	 * @dataProvider getSatoshis
	 */
	public function testSatoshi(string $satoshi, int $decimals)
	{
		$amount = Amount::satoshi($satoshi, $decimals);

		$this->assertEqualsWithDelta($satoshi, $amount->satoshi, 0.0000001);

		$this->assertEquals(bcdiv($satoshi, 10 ** $decimals, $decimals), $amount->toBtc());
	}

}
