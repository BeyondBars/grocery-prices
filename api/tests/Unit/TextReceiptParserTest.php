<?php

namespace Tests\Unit;

use App\Receipts\Ocr\OcrResult;
use App\Receipts\Parsing\ParsedItem;
use App\Receipts\Parsing\TextReceiptParser;
use App\Support\Enums\ItemUnit;
use Tests\TestCase;

class TextReceiptParserTest extends TestCase
{
    private function parse(string $text, ?array $items = null)
    {
        return (new TextReceiptParser)->parse(new OcrResult('test', $text, $items));
    }

    public function testParsesAWoolworthsStyleReceipt()
    {
        $receipt = $this->parse(file_get_contents(base_path('tests/fixtures/receipts/woolworths.txt')));

        $this->assertSame('88000014675', $receipt->abn);
        $this->assertSame('2026-10-08 10:15', $receipt->purchasedAt->format('Y-m-d H:i'));
        $this->assertSame('008812', $receipt->receiptNumber);
        $this->assertSame(2120, $receipt->subtotalCents);
        $this->assertSame(2120, $receipt->totalCents);
        $this->assertSame([], $receipt->warnings);

        $lines = array_map(fn (ParsedItem $item) => [$item->name, $item->quantity, $item->unit, $item->unitPriceCents, $item->totalPriceCents, $item->discountCents], $receipt->items);

        $this->assertSame([
            ['PAULS FULL CREAM MILK 2L', 1.0, ItemUnit::Each, 450, 450, 0],
            ['WONDER WHITE BREAD 700G', 1.0, ItemUnit::Each, 350, 350, 0],
            ['FARM FRESH EGGS 12PK', 1.0, ItemUnit::Each, 600, 600, 100],
            ['COCA COLA 1.25L', 2.0, ItemUnit::Each, 225, 450, 0],
            ['BANANAS CAVENDISH', 0.756, ItemUnit::Kilogram, 490, 370, 0],
        ], $lines);
    }

    public function testQuantityLineAfterAPricedItemUpdatesThatItem()
    {
        $receipt = $this->parse("COLES\nSOFT DRINK 1.25L 5.00\n2 @ $2.50 EACH\nTOTAL 5.00");

        $this->assertCount(1, $receipt->items);
        $this->assertSame(2.0, $receipt->items[0]->quantity);
        $this->assertSame(250, $receipt->items[0]->unitPriceCents);
    }

    public function testReadsProductCodesTrailingMinusDiscountsAndGstFlags()
    {
        $receipt = $this->parse("9300633603158 PAULS MILK 2L 4.50 #\nPROMO 0.50-\nTOTAL 4.00");

        $this->assertSame('9300633603158', $receipt->items[0]->sku);
        $this->assertSame('PAULS MILK 2L', $receipt->items[0]->name);
        $this->assertSame(50, $receipt->items[0]->discountCents);
        $this->assertSame([], $receipt->warnings);
    }

    public function testADiscountBeforeAnyItemIsAReceiptDiscount()
    {
        $receipt = $this->parse("MEMBER DISCOUNT -2.00\nBREAD 3.50\nTOTAL 1.50");

        $this->assertSame(200, $receipt->discountCents);
        $this->assertSame(0, $receipt->items[0]->discountCents);
    }

    public function testWarnsWhenItemsDoNotAddUpToTheTotal()
    {
        $receipt = $this->parse("MILK 2L 4.50\nTOTAL 10.00");

        $this->assertStringContainsString('add up to $4.50 but the receipt total is $10.00', $receipt->warnings[0]);
    }

    public function testWarnsWhenNoItemsAreFound()
    {
        $this->assertSame(['No items could be read from this receipt.'], $this->parse("THANK YOU\nTOTAL 0.00")->warnings);
    }

    public function testAProductSizeIsNeverReadAsAPrice()
    {
        $this->assertSame([], $this->parse("COCA COLA 1.25L\nTHANKS")->items);
    }

    public function testUnderstandsDifferentDateFormats()
    {
        $this->assertSame('2026-10-08', $this->parse('DATE 8/10/26')->purchasedAt->toDateString());
        $this->assertSame('2026-10-08 14:05', $this->parse('08 OCT 2026 2:05 PM')->purchasedAt->format('Y-m-d H:i'));
        $this->assertNull($this->parse('31/02/2026')->purchasedAt);
        $this->assertNull($this->parse('01/01/2099')->purchasedAt);
    }

    public function testUsesItemsFromTheOcrEngineWhenItHasThem()
    {
        $receipt = $this->parse("WOOLWORTHS\nSOMETHING 1.00\nTOTAL 3.50", [
            new ParsedItem('PAULS MILK 2L', 450),
            new ParsedItem('PRICE REDUCED', -100),
        ]);

        $this->assertCount(1, $receipt->items);
        $this->assertSame('PAULS MILK 2L', $receipt->items[0]->name);
        $this->assertSame(100, $receipt->items[0]->discountCents);
        $this->assertSame(350, $receipt->totalCents);
    }

    public function testMasksCardNumbersButNotBarcodes()
    {
        $masked = (new OcrResult('test', "CARD 4111 1111 1111 1111\nMILK 9300633603158 4.50"))->masked()->text;

        $this->assertStringContainsString('CARD ************1111', $masked);
        $this->assertStringContainsString('9300633603158', $masked);
    }
}
