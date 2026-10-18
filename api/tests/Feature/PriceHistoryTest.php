<?php

namespace Tests\Feature;

use App\Models\PriceObservation;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\Store;
use App\Support\Enums\ItemUnit;
use App\Support\Enums\MatchStatus;
use App\Support\Enums\PriceSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Price history is derived from receipt items and must always follow their current state.
 */
class PriceHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function testAMatchedItemRecordsWhatWasPaidAfterDiscount()
    {
        $product = Product::factory()->create();
        $receipt = Receipt::factory()->create(['purchased_at' => '2026-10-08 09:00:00']);
        $item = ReceiptItem::factory()->for($receipt)->matchedTo($product)->create([
            'quantity' => 2,
            'unit_price_cents' => 450,
            'total_price_cents' => 900,
            'discount_cents' => 100,
        ]);

        $item->syncPriceObservation();

        $this->assertDatabaseHas('price_observations', [
            'receipt_item_id' => $item->id,
            'product_id' => $product->id,
            'store_id' => $receipt->store_id,
            'user_id' => $receipt->user_id,
            'source' => PriceSource::Receipt->value,
            'price_cents' => 400,
            'regular_price_cents' => 450,
            'is_promotion' => true,
            'unit' => ItemUnit::Each->value,
        ]);
        $this->assertSame('2026-10-08', PriceObservation::first()->observed_on->toDateString());
    }

    public function testWeighedItemsArePricedPerKilogram()
    {
        $bananas = Product::factory()->create(['name' => 'Bananas', 'size_amount' => null, 'size_unit' => null]);
        $item = ReceiptItem::factory()->matchedTo($bananas)->create([
            'quantity' => 0.756,
            'unit' => ItemUnit::Kilogram,
            'unit_price_cents' => 490,
            'total_price_cents' => 370,
        ]);

        $item->syncPriceObservation();

        // The printed $4.90/kg, not $3.70 / 0.756 kg = $4.89.
        $this->assertDatabaseHas('price_observations', ['receipt_item_id' => $item->id, 'price_cents' => 490, 'unit' => 'kg']);
    }

    public function testUnmatchedSuggestedAndIgnoredItemsHaveNoPrice()
    {
        $product = Product::factory()->create();

        foreach ([MatchStatus::Pending, MatchStatus::Suggested, MatchStatus::Ignored] as $status) {
            ReceiptItem::factory()->create(['product_id' => $product->id, 'match_status' => $status])->syncPriceObservation();
        }

        $this->assertDatabaseCount('price_observations', 0);
    }

    public function testItemsOnAReceiptWithoutAStoreOrDateHaveNoPrice()
    {
        $product = Product::factory()->create();

        ReceiptItem::factory()->for(Receipt::factory()->state(['store_id' => null]))->matchedTo($product)->create()->syncPriceObservation();
        ReceiptItem::factory()->for(Receipt::factory()->state(['purchased_at' => null]))->matchedTo($product)->create()->syncPriceObservation();

        $this->assertDatabaseCount('price_observations', 0);
    }

    public function testRematchingAnItemMovesItsPriceToTheNewProduct()
    {
        [$wrong, $right] = Product::factory()->count(2)->create();
        $item = ReceiptItem::factory()->matchedTo($wrong)->create();
        $item->syncPriceObservation();

        $item->update(['product_id' => $right->id]);
        $item->syncPriceObservation();

        $this->assertDatabaseCount('price_observations', 1);
        $this->assertDatabaseHas('price_observations', ['receipt_item_id' => $item->id, 'product_id' => $right->id]);

        $item->update(['match_status' => MatchStatus::Ignored]);
        $item->syncPriceObservation();
        $this->assertDatabaseCount('price_observations', 0);
    }

    public function testCorrectingAnItemOrTheReceiptHeaderUpdatesItsPrice()
    {
        $user = $this->createStandardUser();
        $product = Product::factory()->create();
        $receipt = Receipt::factory()->for($user)->create();
        $item = ReceiptItem::factory()->for($receipt)->matchedTo($product)->create(['unit_price_cents' => 380, 'total_price_cents' => 380]);
        $item->syncPriceObservation();

        $this->be($user)->putJson("receipt-items/{$item->id}", ['unit_price_cents' => 350, 'total_price_cents' => 350])
            ->assertSuccessful()
            ->assertJsonPath('data.paid_unit_price_cents', 350);
        $this->assertDatabaseHas('price_observations', ['receipt_item_id' => $item->id, 'price_cents' => 350]);

        $drakes = Store::factory()->create();
        $this->putJson("receipts/{$receipt->id}", ['store_id' => $drakes->id, 'purchased_at' => '2026-10-01'])->assertSuccessful();
        $observation = PriceObservation::firstWhere('receipt_item_id', $item->id);
        $this->assertSame($drakes->id, $observation->store_id);
        $this->assertSame('2026-10-01', $observation->observed_on->toDateString());
    }

    public function testDeletingAReceiptRemovesItsPricesAndRestoringBringsThemBack()
    {
        $receipt = Receipt::factory()->create();
        ReceiptItem::factory()->for($receipt)->matchedTo(Product::factory()->create())->create();
        $receipt->syncPriceObservations();
        $this->assertDatabaseCount('price_observations', 1);

        $receipt->delete();
        $this->assertDatabaseCount('price_observations', 0);

        $receipt->restore();
        $this->assertDatabaseCount('price_observations', 1);
    }

    public function testEditingAProductNeverChangesRecordedPrices()
    {
        $product = Product::factory()->create(['name' => 'Milk 2L']);
        $item = ReceiptItem::factory()->matchedTo($product)->create(['original_name' => 'PAULS MILK 2L', 'total_price_cents' => 450, 'unit_price_cents' => 450]);
        $item->syncPriceObservation();

        $product->update(['name' => "Paul's Full Cream Milk"]);

        $this->assertDatabaseHas('receipt_items', ['id' => $item->id, 'original_name' => 'PAULS MILK 2L', 'total_price_cents' => 450]);
        $this->assertDatabaseHas('price_observations', ['receipt_item_id' => $item->id, 'price_cents' => 450]);
    }
}
