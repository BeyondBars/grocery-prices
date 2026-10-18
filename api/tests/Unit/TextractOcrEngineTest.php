<?php

namespace Tests\Unit;

use App\Receipts\Ocr\TextractOcrEngine;
use App\Receipts\ReceiptProcessingException;
use App\Support\Enums\ItemUnit;
use Aws\CommandInterface;
use Aws\MockHandler;
use Aws\Result;
use Aws\Textract\Exception\TextractException;
use Aws\Textract\TextractClient;
use PHPUnit\Framework\TestCase;

class TextractOcrEngineTest extends TestCase
{
    private function engine(MockHandler $handler): TextractOcrEngine
    {
        return new TextractOcrEngine(new TextractClient([
            'version' => '2018-06-27',
            'region' => 'ap-southeast-2',
            'credentials' => ['key' => 'test', 'secret' => 'test'],
            'retries' => 0,
            'handler' => $handler,
        ]));
    }

    private static function field(string $type, string $value): array
    {
        return ['Type' => ['Text' => $type], 'ValueDetection' => ['Text' => $value]];
    }

    public function testMapsAnalyzeExpenseLineItemsAndText()
    {
        $handler = new MockHandler([new Result(['ExpenseDocuments' => [[
            'Blocks' => [
                ['BlockType' => 'PAGE'],
                ['BlockType' => 'LINE', 'Text' => 'WOOLWORTHS'],
                ['BlockType' => 'LINE', 'Text' => 'TOTAL 8.20'],
            ],
            'LineItemGroups' => [['LineItems' => [
                ['LineItemExpenseFields' => [self::field('ITEM', 'PAULS MILK 2L'), self::field('PRICE', '$4.50'), self::field('PRODUCT_CODE', '9300633603158'), self::field('EXPENSE_ROW', 'PAULS MILK 2L 4.50')]],
                ['LineItemExpenseFields' => [self::field('ITEM', 'BANANAS'), self::field('PRICE', '3.70'), self::field('QUANTITY', '0.756kg'), self::field('UNIT_PRICE', '4.90')]],
                ['LineItemExpenseFields' => [self::field('ITEM', 'NO PRICE')]],
            ]]],
        ]]])]);

        $result = $this->engine($handler)->read('bytes', 'image/jpeg');

        $this->assertSame('textract', $result->provider);
        $this->assertSame("WOOLWORTHS\nTOTAL 8.20", $result->text);
        $this->assertCount(2, $result->items);
        $this->assertSame(['PAULS MILK 2L', 450, '9300633603158'], [$result->items[0]->name, $result->items[0]->totalPriceCents, $result->items[0]->sku]);
        $this->assertSame([0.756, ItemUnit::Kilogram, 490], [$result->items[1]->quantity, $result->items[1]->unit, $result->items[1]->unitPriceCents]);
    }

    public function testUnreadableDocumentsAreNotRetried()
    {
        $handler = new MockHandler([
            fn (CommandInterface $command) => new TextractException('bad', $command, ['code' => 'UnsupportedDocumentException']),
        ]);

        $this->expectException(ReceiptProcessingException::class);

        $this->engine($handler)->read('bytes', 'image/jpeg');
    }

    public function testOtherErrorsAreRethrownSoTheJobRetries()
    {
        $handler = new MockHandler([
            fn (CommandInterface $command) => new TextractException('slow down', $command, ['code' => 'ThrottlingException']),
        ]);

        $this->expectException(TextractException::class);

        $this->engine($handler)->read('bytes', 'image/jpeg');
    }
}
