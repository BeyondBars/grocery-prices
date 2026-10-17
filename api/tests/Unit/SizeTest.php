<?php

namespace Tests\Unit;

use App\Support\Enums\SizeUnit;
use App\Support\Size;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SizeTest extends TestCase
{
    public static function sizes(): array
    {
        return [
            ['PAULS FULL CREAM MILK 2L', 2000, SizeUnit::Millilitres, '2L'],
            ['PAULS FULL CREAM MILK 2 LT', 2000, SizeUnit::Millilitres, '2L'],
            ['PAULS FULL CREAM 2LTR', 2000, SizeUnit::Millilitres, '2L'],
            ['COCA COLA 1.25L', 1250, SizeUnit::Millilitres, '1.25L'],
            ['WONDER WHITE BREAD 700G', 700, SizeUnit::Grams, '700g'],
            ['SUNRICE 5KG', 5000, SizeUnit::Grams, '5kg'],
            ['CREAM 300ML', 300, SizeUnit::Millilitres, '300ml'],
            ['6 each', 6, SizeUnit::Each, '6 each'],
        ];
    }

    #[DataProvider('sizes')]
    public function testParsesSizesAsWrittenOnReceipts(string $text, float $amount, SizeUnit $unit, string $label)
    {
        $size = Size::parse($text);

        $this->assertSame($amount, $size->amount);
        $this->assertSame($unit, $size->unit);
        $this->assertSame($label, $size->label());
    }

    public function testReturnsNullWithoutASize()
    {
        $this->assertNull(Size::parse('BANANAS'));
        $this->assertNull(Size::parse(''));
        $this->assertNull(Size::parse(null));
        // "LIGHT" must not be read as litres.
        $this->assertNull(Size::parse('LIGHT MILK'));
    }

    public function testDifferentSizesAreNotEqual()
    {
        $this->assertTrue(Size::parse('2L')->equals(Size::parse('2 LT')));
        $this->assertFalse(Size::parse('2L')->equals(Size::parse('1.25L')));
        $this->assertFalse(Size::parse('500g')->equals(Size::parse('500ml')));
    }
}
