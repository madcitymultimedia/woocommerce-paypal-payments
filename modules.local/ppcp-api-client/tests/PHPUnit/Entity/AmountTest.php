<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Entity;


use PHPUnit\Framework\TestCase;

class AmountTest extends TestCase
{

    public function test() {
        $money = \Mockery::mock(Money::class);
        $money->expects('currencyCode')->andReturn('currencyCode');
        $money->expects('value')->andReturn(1.10);
        $testee = new Amount($money);

        $this->assertEquals('currencyCode', $testee->currencyCode());
        $this->assertEquals(1.10, $testee->value());
    }
    public function testBreakdownIsNull() {
        $money = \Mockery::mock(Money::class);
        $money->expects('currencyCode')->andReturn('currencyCode');
        $money->expects('value')->andReturn(1.10);
        $testee = new Amount($money);

        $this->assertNull($testee->breakdown());

        $expectedArray = [
            'currency_code' => 'currencyCode',
            'value' => 1.10,
        ];
        $this->assertEquals($expectedArray, $testee->toArray());
    }
    public function testBreakdown() {
        $money = \Mockery::mock(Money::class);
        $money->expects('currencyCode')->andReturn('currencyCode');
        $money->expects('value')->andReturn(1.10);
        $breakdown = \Mockery::mock(AmountBreakdown::class);
        $breakdown->expects('toArray')->andReturn([1]);
        $testee = new Amount($money, $breakdown);

        $this->assertEquals($breakdown, $testee->breakdown());

        $expectedArray = [
            'currency_code' => 'currencyCode',
            'value' => 1.10,
            'breakdown' => [1]
        ];
        $this->assertEquals($expectedArray, $testee->toArray());
    }
}