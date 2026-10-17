<?php

namespace Tests\Feature;

use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\Store;
use App\Support\Enums\ReceiptStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function testUsersOnlySeeTheirOwnReceipts()
    {
        $user = $this->createStandardUser();
        $mine = Receipt::factory()->for($user)->has(ReceiptItem::factory()->count(2), 'items')->create();
        $theirs = Receipt::factory()->create();

        $this->be($user)->getJson('receipts')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id)
            ->assertJsonPath('data.0.items_count', 2);

        $this->getJson("receipts/{$mine->id}")->assertSuccessful()->assertJsonCount(2, 'data.items');
        $this->getJson("receipts/{$theirs->id}")->assertForbidden();
        $this->putJson("receipts/{$theirs->id}", ['store_location' => 'x'])->assertForbidden();
        $this->deleteJson("receipts/{$theirs->id}")->assertForbidden();
        $this->putJson("receipt-items/{$theirs->items()->create(ReceiptItem::factory()->raw(['receipt_id' => $theirs->id]))->id}", ['quantity' => 2])
            ->assertForbidden();
    }

    public function testReceiptsCanBeFilteredByStatus()
    {
        $user = $this->createStandardUser();
        Receipt::factory()->for($user)->create(['status' => ReceiptStatus::NeedsReview]);
        Receipt::factory()->for($user)->create(['status' => ReceiptStatus::Completed]);

        $this->be($user)->getJson('receipts?filter[status]=needs_review')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'needs_review');
    }

    public function testUserCanCorrectTheReceiptHeader()
    {
        $user = $this->createStandardUser();
        $receipt = Receipt::factory()->for($user)->create();
        $drakes = Store::factory()->create(['name' => 'Drakes']);

        $this->be($user)->putJson("receipts/{$receipt->id}", [
            'store_id' => $drakes->id,
            'store_location' => 'Mile End',
            'purchased_at' => '2026-10-08 10:15:00',
            'receipt_number' => '1234-56',
        ])
            ->assertSuccessful()
            ->assertJsonPath('data.store.name', 'Drakes')
            ->assertJsonPath('data.receipt_number', '1234-56');

        $this->putJson("receipts/{$receipt->id}", ['purchased_at' => now()->addWeek()])->assertJsonValidationErrors('purchased_at');
    }

    public function testDeletedReceiptsDisappearFromTheList()
    {
        $user = $this->createStandardUser();
        $receipt = Receipt::factory()->for($user)->create();

        $this->be($user)->deleteJson("receipts/{$receipt->id}")->assertNoContent();

        $this->assertSoftDeleted($receipt);
        $this->getJson('receipts')->assertJsonCount(0, 'data');
        $this->getJson("receipts/{$receipt->id}")->assertNotFound();
    }
}
