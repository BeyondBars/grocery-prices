<?php

namespace Tests\Feature;

use App\Jobs\ProcessReceipt;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\Store;
use App\Receipts\Ocr\OcrEngine;
use App\Receipts\Ocr\OcrResult;
use App\Receipts\ReceiptProcessingException;
use App\Support\Enums\ReceiptStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class ProcessReceiptTest extends TestCase
{
    use RefreshDatabase;

    private int $ocrCalls = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::disk('local')->put('receipts/1/r.jpg', 'image bytes');
    }

    private function fakeOcr(string|\Throwable $result): void
    {
        $this->app->instance(OcrEngine::class, new class($result, $this->ocrCalls) implements OcrEngine {
            public function __construct(private $result, private int &$calls)
            {
            }

            public function read(string $contents, string $mimeType): OcrResult
            {
                $this->calls++;

                return is_string($this->result) ? new OcrResult('fake', $this->result) : throw $this->result;
            }
        });
    }

    private function receipt(array $attributes = []): Receipt
    {
        return Receipt::factory()->create([
            'store_id' => null,
            'purchased_at' => null,
            'total_cents' => null,
            'status' => ReceiptStatus::Uploaded,
            'file_path' => 'receipts/1/r.jpg',
            'file_mime' => 'image/jpeg',
            ...$attributes,
        ]);
    }

    public function testReadsTheReceiptIntoItemsForReview()
    {
        $woolworths = Store::factory()->create(['name' => 'Woolworths', 'aliases' => ['WOOLWORTHS']]);
        $this->fakeOcr(file_get_contents(base_path('tests/fixtures/receipts/woolworths.txt')));
        $receipt = $this->receipt();

        ProcessReceipt::dispatchSync($receipt);

        $receipt->refresh();
        $this->assertSame(ReceiptStatus::NeedsReview, $receipt->status);
        $this->assertSame($woolworths->id, $receipt->store_id);
        $this->assertSame('2026-10-08 10:15', $receipt->purchased_at->format('Y-m-d H:i'));
        $this->assertSame(2120, $receipt->total_cents);
        $this->assertSame('fake', $receipt->ocr_provider);
        $this->assertStringContainsString('************1111', $receipt->ocr_text);
        $this->assertNotNull($receipt->processed_at);
        $this->assertSame(
            ['PAULS FULL CREAM MILK 2L', 'WONDER WHITE BREAD 700G', 'FARM FRESH EGGS 12PK', 'COCA COLA 1.25L', 'BANANAS CAVENDISH'],
            $receipt->items->pluck('original_name')->all()
        );
        $this->assertSame('pending', $receipt->items[0]->match_status->value);
    }

    public function testReprocessingReusesSavedOcrTextAndReplacesItems()
    {
        $this->fakeOcr('should not be called');
        $receipt = $this->receipt(['ocr_provider' => 'textract', 'ocr_text' => "BREAD 3.50\nTOTAL 3.50"]);
        ReceiptItem::factory()->for($receipt)->count(3)->create();

        ProcessReceipt::dispatchSync($receipt);

        $this->assertSame(0, $this->ocrCalls);
        $this->assertSame(['BREAD'], $receipt->items()->pluck('original_name')->all());
    }

    public function testAKnownProblemFailsWithItsMessageWithoutRetrying()
    {
        $this->fakeOcr(new ReceiptProcessingException('This file could not be read.'));
        $receipt = $this->receipt();

        ProcessReceipt::dispatchSync($receipt);

        $this->assertSame(ReceiptStatus::Failed, $receipt->fresh()->status);
        $this->assertSame('This file could not be read.', $receipt->fresh()->failure_reason);
        $this->assertSame(1, $this->ocrCalls);
    }

    public function testAnUnexpectedErrorShowsAFriendlyMessageOnceRetriesRunOut()
    {
        $this->fakeOcr(new RuntimeException('Textract timed out'));
        $receipt = $this->receipt();

        (new ProcessReceipt($receipt))->failed(new RuntimeException('Textract timed out'));

        $this->assertSame(ReceiptStatus::Failed, $receipt->fresh()->status);
        $this->assertSame("We couldn't read this receipt. Try again, or upload a clearer photo.", $receipt->fresh()->failure_reason);
    }

    public function testPhotosFailClearlyWhenNoOcrIsConfigured()
    {
        config(['receipts.ocr_engine' => 'none']);
        $receipt = $this->receipt();

        ProcessReceipt::dispatchSync($receipt);

        $this->assertSame('Reading photos of receipts is not set up yet. PDF receipts still work.', $receipt->fresh()->failure_reason);
    }

    public function testPdfsWithTextSkipOcr()
    {
        Process::fake(['*' => Process::result("COLES\n01/10/2026\nMILK 2L 4.00\nTOTAL 4.00")]);
        $this->fakeOcr('should not be called');
        Storage::disk('local')->put('receipts/1/r.pdf', '%PDF');
        $receipt = $this->receipt(['file_path' => 'receipts/1/r.pdf', 'file_mime' => 'application/pdf']);

        ProcessReceipt::dispatchSync($receipt);

        $this->assertSame(0, $this->ocrCalls);
        $this->assertSame('pdftotext', $receipt->fresh()->ocr_provider);
        $this->assertSame(['MILK 2L'], $receipt->items()->pluck('original_name')->all());
        Process::assertRan(fn ($process) => $process->command[1] === '-layout');
    }

    public function testScannedPdfsFallBackToOcr()
    {
        Process::fake(['*' => Process::result('')]);
        $this->fakeOcr("BREAD 3.50\nTOTAL 3.50");
        Storage::disk('local')->put('receipts/1/r.pdf', '%PDF');
        $receipt = $this->receipt(['file_path' => 'receipts/1/r.pdf', 'file_mime' => 'application/pdf']);

        ProcessReceipt::dispatchSync($receipt);

        $this->assertSame(1, $this->ocrCalls);
        $this->assertSame('fake', $receipt->fresh()->ocr_provider);
    }
}
