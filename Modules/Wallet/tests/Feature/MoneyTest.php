<?php

namespace Modules\Wallet\Tests\Feature;

use Modules\Wallet\Support\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    #[DataProvider('formats')]
    public function test_format(int $minor, string $expected): void
    {
        $this->assertSame($expected, Money::format($minor));
    }

    public static function formats(): array
    {
        return [[0, '$0.00'], [5, '$0.05'], [1050, '$10.50'], [-12, '-$0.12'], [123456789, '$1,234,567.89']];
    }

    #[DataProvider('inputs')]
    public function test_parse(string $input, ?int $expected): void
    {
        $this->assertSame($expected, Money::parse($input));
    }

    public static function inputs(): array
    {
        return [['10', 1000], ['10.5', 1050], ['10.50', 1050], [' 7 ', 700], ['0.05', 5], ['', null], ['-1', null], ['1.234', null], ['1e3', null], ['abc', null]];
    }
}
