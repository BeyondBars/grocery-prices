<?php

namespace Tests\Feature;

use App\Matching\ReceiptItemMatcher;
use App\Models\Brand;
use App\Models\PriceObservation;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\Store;
use App\Models\User;
use App\Support\Enums\MatchStatus;
use App\Support\Enums\ReceiptStatus;
use App\Support\Enums\SizeUnit;
use App\Support\Enums\UserAbility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The review screen: confirming, creating and ignoring products for receipt lines.
 */
class ReceiptReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Receipt $receipt;

    private Product $milk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->createStandardUser();
        $this->receipt = Receipt::factory()->for($this->user)->create(['status' => ReceiptStatus::NeedsReview]);
        $this->milk = Product::factory()->create([
            'name' => 'Full Cream Milk', 'variant' => 'Full Cream', 'size_amount' => 2000, 'size_unit' => SizeUnit::Millilitres,
            'brand_id' => Brand::factory()->create(['name' => "Paul's", 'aliases' => ['PAULS']])->id,
        ]);
    }

    private function item(string $name, array $attributes = []): ReceiptItem
    {
        return ReceiptItem::factory()->for($this->receipt)->create(['original_name' => $name, 'match_status' => MatchStatus::Pending, ...$attributes]);
    }

    public function testCandidatesListLikelyProductsAndAPrefilledNewProduct()
    {
        $item = $this->item('PAULS FULL CREAM 2LTR', ['sku' => '9310036001234']);

        $this->be($this->user)->getJson("receipt-items/{$item->id}/candidates")
            ->assertSuccessful()
            ->assertJsonPath('data.0.product.id', $this->milk->id)
            ->assertJsonPath('data.0.reason', 'Same brand, same size')
            ->assertJsonPath('new_product', [
                'name' => 'Full Cream',
                'brand' => "Paul's",
                'variant' => 'Full Cream',
                'size' => '2L',
                'pack_count' => 1,
                'barcode' => '9310036001234',
            ]);

        $this->beUser()->getJson("receipt-items/{$item->id}/candidates")->assertForbidden();
    }

    public function testConfirmingAProductRecordsThePriceLearnsTheWordingAndCompletesTheReceipt()
    {
        $item = $this->item('PAULS FULL CREAM 2LTR', ['total_price_cents' => 450, 'unit_price_cents' => 450]);

        $this->be($this->user)->postJson("receipt-items/{$item->id}/match", ['product_id' => $this->milk->id])
            ->assertSuccessful()
            ->assertJsonPath('data.match_status', 'confirmed')
            ->assertJsonPath('data.matched_by', 'user')
            ->assertJsonPath('data.product.id', $this->milk->id);

        $this->assertDatabaseHas('price_observations', ['receipt_item_id' => $item->id, 'product_id' => $this->milk->id, 'price_cents' => 450]);
        $this->assertDatabaseHas('product_aliases', [
            'product_id' => $this->milk->id, 'store_id' => $this->receipt->store_id, 'normalized_text' => 'PAULS FULL CREAM 2L', 'confirmed_by' => $this->user->id,
        ]);
        $this->assertSame(ReceiptStatus::Completed, $this->receipt->fresh()->status);
    }

    public function testConfirmingAWordingMatchesTheSameWordingOnTheUsersOtherReceipts()
    {
        $item = $this->item('PAULS FULL CREAM 2LTR');
        $otherReceipt = Receipt::factory()->for($this->user)->create(['status' => ReceiptStatus::NeedsReview, 'store_id' => $this->receipt->store_id]);
        $other = ReceiptItem::factory()->for($otherReceipt)->create(['original_name' => 'PAULS FULL CREAM 2LTR', 'normalized_name' => 'PAULS FULL CREAM 2L', 'match_status' => MatchStatus::Suggested]);
        $someoneElses = ReceiptItem::factory()->create(['original_name' => 'PAULS FULL CREAM 2LTR', 'normalized_name' => 'PAULS FULL CREAM 2L', 'match_status' => MatchStatus::Pending]);

        $this->be($this->user)->postJson("receipt-items/{$item->id}/match", ['product_id' => $this->milk->id])->assertSuccessful();

        $this->assertSame([MatchStatus::AutoMatched, $this->milk->id], [$other->fresh()->match_status, $other->fresh()->product_id]);
        $this->assertSame(ReceiptStatus::Completed, $otherReceipt->fresh()->status);
        $this->assertSame(MatchStatus::Pending, $someoneElses->fresh()->match_status);
    }

    public function testUserCanCreateANewProductFromALine()
    {
        $item = $this->item('SUNRICE JASMINE RICE 5KG');
        $brand = Brand::factory()->create(['name' => 'SunRice']);

        $this->be($this->user)->postJson("receipt-items/{$item->id}/match", [
            'new_product' => ['name' => 'Jasmine Rice', 'brand_id' => $brand->id, 'size' => '5kg'],
        ])
            ->assertSuccessful()
            ->assertJsonPath('data.match_status', 'new_product')
            ->assertJsonPath('data.product.name', 'Jasmine Rice')
            ->assertJsonPath('data.product.size', '5kg');

        $this->assertDatabaseHas('products', ['name' => 'Jasmine Rice', 'created_by' => $this->user->id, 'size_amount' => 5000]);
    }

    public function testCreatingAProductThatAlreadyExistsIsRefused()
    {
        $item = $this->item('PAULS MILK');

        $this->be($this->user)->postJson("receipt-items/{$item->id}/match", [
            'new_product' => ['name' => 'Full cream milk', 'brand_id' => $this->milk->brand_id, 'variant' => 'Full Cream', 'size' => '2 L'],
        ])->assertJsonValidationErrors(['new_product.name' => 'This product already exists as "Full Cream Milk". Choose it from the list instead.']);

        $this->assertSame(1, Product::count());
    }

    public function testRequiresAProductANewProductOrIgnore()
    {
        $item = $this->item('PAULS MILK');

        $this->be($this->user)->postJson("receipt-items/{$item->id}/match", [])->assertJsonValidationErrors(['product_id', 'new_product']);
        $this->postJson("receipt-items/{$item->id}/match", ['new_product' => ['size' => 'big']])->assertJsonValidationErrors(['new_product.name', 'new_product.size']);
    }

    public function testIgnoringALineAndUndoingIt()
    {
        $item = $this->item('BAG 10C', ['total_price_cents' => 15]);

        $this->be($this->user)->postJson("receipt-items/{$item->id}/match", ['ignore' => true])
            ->assertJsonPath('data.match_status', 'ignored');
        $this->assertSame(ReceiptStatus::Completed, $this->receipt->fresh()->status);

        $this->postJson("receipt-items/{$item->id}/match", ['ignore' => false])
            ->assertJsonPath('data.match_status', 'pending');
        $this->assertSame(ReceiptStatus::NeedsReview, $this->receipt->fresh()->status);
    }

    public function testFeesAndDepositsAreRecognisedAsNotProducts()
    {
        $matcher = app(ReceiptItemMatcher::class);

        foreach (['BAG 15C', 'REUSABLE BAGS', 'CONTAINER DEPOSIT 10C', 'CASH ROUNDING'] as $line) {
            $item = $this->item($line);
            $matcher->autoMatch($item);
            $this->assertSame([MatchStatus::Ignored, 'rule'], [$item->fresh()->match_status, $item->fresh()->matched_by->value], $line);
        }

        // "BAGEL" isn't a bag.
        $bagel = $this->item('BAGELS 6PK');
        $matcher->autoMatch($bagel);
        $this->assertSame(MatchStatus::Pending, $bagel->fresh()->match_status);
    }

    public function testALineMarkedNotAProductIsSkippedNextTimeAtThatStore()
    {
        $item = $this->item('LOYALTY CARD SWIPE', ['total_price_cents' => 0]);
        $waiting = ReceiptItem::factory()->for(Receipt::factory()->for($this->user)->state(['store_id' => $this->receipt->store_id, 'status' => ReceiptStatus::NeedsReview]))
            ->create(['original_name' => 'LOYALTY CARD SWIPE', 'normalized_name' => 'LOYALTY CARD SWIPE', 'match_status' => MatchStatus::Pending]);

        $this->be($this->user)->postJson("receipt-items/{$item->id}/match", ['ignore' => true])->assertSuccessful();

        $this->assertDatabaseHas('ignored_wordings', ['store_id' => $this->receipt->store_id, 'normalized_text' => 'LOYALTY CARD SWIPE', 'confirmed_by' => $this->user->id]);
        // The same wording waiting on another receipt is settled straight away...
        $this->assertSame(MatchStatus::Ignored, $waiting->fresh()->match_status);
        // ...and on new receipts from that store, but not other stores.
        $next = $this->item('LOYALTY CARD SWIPE');
        app(ReceiptItemMatcher::class)->autoMatch($next);
        $this->assertSame(MatchStatus::Ignored, $next->fresh()->match_status);

        $elsewhere = ReceiptItem::factory()->create(['original_name' => 'LOYALTY CARD SWIPE', 'match_status' => MatchStatus::Pending]);
        app(ReceiptItemMatcher::class)->autoMatch($elsewhere);
        $this->assertSame(MatchStatus::Pending, $elsewhere->fresh()->match_status);

        // Undo forgets it.
        $this->postJson("receipt-items/{$item->id}/match", ['ignore' => false])->assertJsonPath('data.match_status', 'pending');
        $this->assertDatabaseCount('ignored_wordings', 0);
    }

    public function testAReceiptWithoutAStoreIsNotCompleted()
    {
        $this->receipt->update(['store_id' => null]);
        $item = $this->item('PAULS FULL CREAM 2LTR');

        $this->be($this->user)->postJson("receipt-items/{$item->id}/match", ['product_id' => $this->milk->id]);
        $this->assertSame(ReceiptStatus::NeedsReview, $this->receipt->fresh()->status);
        $this->assertDatabaseCount('price_observations', 0);

        $this->putJson("receipts/{$this->receipt->id}", ['store_id' => Store::factory()->create()->id]);
        $this->assertSame(ReceiptStatus::Completed, $this->receipt->fresh()->status);
        $this->assertDatabaseCount('price_observations', 1);
    }

    public function testRenamingALineMatchesItAgain()
    {
        $item = $this->item('PAULS FULL CRM MLK 2L');

        $this->be($this->user)->putJson("receipt-items/{$item->id}", ['original_name' => 'PAULS FULL CREAM MILK 2L'])
            ->assertJsonPath('data.match_status', 'auto_matched')
            ->assertJsonPath('data.product.id', $this->milk->id);
    }

    public function testCatalogManagersCanMergeDuplicates()
    {
        $duplicate = Product::factory()->create(['name' => 'Pauls Milk 2L', 'barcode' => '9310036001234']);
        $item = $this->item('PAULS MILK 2L')->setRelation('receipt', $this->receipt);
        $item->update(['product_id' => $duplicate->id, 'match_status' => MatchStatus::Confirmed]);
        $item->syncPriceObservation();

        $this->be($this->user)->postJson("products/{$duplicate->id}/merge", ['into_product_id' => $this->milk->id])->assertForbidden();

        $this->beUserAbleTo(UserAbility::ManageCatalog->value)
            ->postJson("products/{$duplicate->id}/merge", ['into_product_id' => $this->milk->id])
            ->assertSuccessful()
            ->assertJsonPath('data.barcode', '9310036001234');

        $this->assertSame($this->milk->id, $item->fresh()->product_id);
        $this->assertSame($this->milk->id, PriceObservation::first()->product_id);
        $this->assertSame($this->milk->id, $duplicate->fresh()->merged_into_id);
        $this->postJson("products/{$duplicate->id}/merge", ['into_product_id' => $this->milk->id])->assertConflict();
        $this->getJson('products')->assertJsonCount(1, 'data');
    }
}
