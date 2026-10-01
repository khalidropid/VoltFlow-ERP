<?php

namespace Tests\Unit\Support;

use App\Support\Decimal;
use PHPUnit\Framework\TestCase;

class DecimalTest extends TestCase
{
    public function test_it_adds_and_multiplies_without_float_arithmetic(): void
    {
        $this->assertSame('100.0000', Decimal::add('99.9999', '0.0001'));
        $this->assertSame('12.5000', Decimal::multiply('2.5000', '5.0000'));
        $this->assertSame('1.2345', Decimal::sub('2.0000', '0.7655'));
    }
}
