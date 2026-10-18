<?php

namespace Tests\Feature;

use App\Jobs\ProcessReceipt;
use App\Models\Receipt;
use App\Support\Enums\ReceiptStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReceiptUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Queue::fake();
    }

    public function testUserCanUploadPhotosAndPdfs()
    {
        $user = $this->createStandardUser();

        $this->be($user)->post('receipts', [
            'files' => [
                UploadedFile::fake()->image('receipt.jpg', 800, 2400),
                UploadedFile::fake()->createWithContent('e-receipt.pdf', "%PDF-1.4\n%fake receipt\n"),
            ],
        ], ['Accept' => 'application/json'])
            ->assertAccepted()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.status', 'uploaded')
            ->assertJsonPath('duplicates', []);

        $receipts = Receipt::whereBelongsTo($user)->get();
        $this->assertSame(['image/jpeg', 'application/pdf'], $receipts->pluck('file_mime')->all());
        $receipts->each(fn ($receipt) => Storage::disk('local')->assertExists($receipt->file_path));
        $this->assertStringStartsWith("receipts/{$user->id}/", $receipts[0]->file_path);
        Queue::assertPushed(ProcessReceipt::class, 2);
    }

    public function testLargePhotosAreShrunkAndStrippedOfLocationData()
    {
        $this->beUser()->post('receipts', ['files' => [$this->photoWithGps()]], ['Accept' => 'application/json'])->assertAccepted();

        $stored = Storage::disk('local')->get(Receipt::first()->file_path);
        [$width, $height] = getimagesizefromstring($stored);

        $this->assertSame(2000, max($width, $height));
        $this->assertStringNotContainsString('GPS', $stored);
        $this->assertStringNotContainsString('Exif', $stored);
    }

    public function testTheSameFileIsOnlyUploadedOnce()
    {
        $user = $this->createStandardUser();
        $file = UploadedFile::fake()->image('receipt.jpg');

        $this->be($user)->post('receipts', ['files' => [$file]], ['Accept' => 'application/json'])->assertAccepted();
        $this->post('receipts', ['files' => [$file]], ['Accept' => 'application/json'])
            ->assertAccepted()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('duplicates', ['receipt.jpg']);

        // Once deleted, the same file can be uploaded again.
        Receipt::first()->delete();
        $this->post('receipts', ['files' => [$file]], ['Accept' => 'application/json'])->assertJsonCount(1, 'data');
        $this->assertSame(1, Receipt::withTrashed()->count());
    }

    public function testRejectsOtherFileTypesAndTooManyFiles()
    {
        $this->beUser();

        $this->post('receipts', ['files' => [UploadedFile::fake()->create('notes.txt', 1, 'text/plain')]], ['Accept' => 'application/json'])
            ->assertJsonValidationErrors(['files.0' => 'Receipts must be photos (JPG or PNG) or PDFs.']);

        $this->post('receipts', ['files' => array_fill(0, 11, UploadedFile::fake()->image('r.jpg'))], ['Accept' => 'application/json'])
            ->assertJsonValidationErrors('files');

        $this->post('receipts', ['files' => [UploadedFile::fake()->image('huge.jpg')->size(11000)]], ['Accept' => 'application/json'])
            ->assertJsonValidationErrors('files.0');
    }

    public function testOnlyTheOwnerCanSeeTheOriginalFile()
    {
        $user = $this->createStandardUser();
        $this->be($user)->post('receipts', ['files' => [UploadedFile::fake()->image('receipt.jpg')]], ['Accept' => 'application/json']);
        $receipt = Receipt::first();

        $this->get("receipts/{$receipt->id}/file")->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->beUser()->getJson("receipts/{$receipt->id}/file")->assertForbidden();
    }

    public function testReprocessingQueuesTheReceiptAgainAndCanForceNewOcr()
    {
        $user = $this->createStandardUser();
        $receipt = Receipt::factory()->for($user)->create(['status' => ReceiptStatus::NeedsReview, 'ocr_provider' => 'textract', 'ocr_text' => 'TOTAL 1.00']);

        $this->be($user)->postJson("receipts/{$receipt->id}/reprocess")->assertSuccessful()->assertJsonPath('data.status', 'uploaded');
        $this->assertNotNull($receipt->fresh()->ocr_text);
        $this->postJson("receipts/{$receipt->id}/reprocess")->assertConflict();

        $receipt->update(['status' => ReceiptStatus::Failed]);
        $this->postJson("receipts/{$receipt->id}/reprocess", ['ocr' => true])->assertSuccessful();
        $this->assertNull($receipt->fresh()->ocr_text);

        Queue::assertPushed(ProcessReceipt::class, 2);
        $this->beUser()->postJson("receipts/{$receipt->id}/reprocess")->assertForbidden();
    }

    public function testReceiptsDeletedOver30DaysAgoArePrunedWithTheirFiles()
    {
        Storage::disk('local')->put('receipts/1/old.jpg', 'x');
        $old = Receipt::factory()->create(['file_path' => 'receipts/1/old.jpg']);
        $old->delete();
        $old->forceFill(['deleted_at' => now()->subDays(31)])->save();
        $recent = Receipt::factory()->create();
        $recent->delete();

        $this->artisan('model:prune', ['--model' => Receipt::class]);

        $this->assertModelMissing($old);
        $this->assertSoftDeleted($recent);
        Storage::disk('local')->assertMissing('receipts/1/old.jpg');
    }

    /**
     * A tall JPEG carrying an EXIF block with a GPS tag, as phones produce.
     */
    private function photoWithGps(): UploadedFile
    {
        $image = imagecreatetruecolor(1000, 3000);
        ob_start();
        imagejpeg($image);
        $jpeg = ob_get_clean();

        $exif = "Exif\0\0".'MM'."\0\x2A\0\0\0\x08".'GPSLatitude -34.9285 GPSLongitude 138.6007';
        $app1 = "\xFF\xE1".pack('n', strlen($exif) + 2).$exif;

        return UploadedFile::fake()->createWithContent('photo.jpg', substr($jpeg, 0, 2).$app1.substr($jpeg, 2));
    }
}
