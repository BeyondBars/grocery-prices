<?php

namespace Tests\Feature;

use App\Models\PriceObservation;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\Store;
use App\Models\User;
use App\Support\Enums\ReceiptStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    /** @var array<string, Store> */
    private array $stores = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-20');
        $this->user = $this->createStandardUser();

        foreach (['Woolworths', 'Coles', 'Drakes'] as $name) {
            $this->stores[$name] = Store::factory()->create(['name' => $name]);
        }
    }

    /**
     * A price from one of the user's receipts, with its receipt line (so the quantity is known).
     */
    private function bought(Product $product, string $store, int $cents, string $date, float $quantity = 1): void
    {
        $receipt = Receipt::factory()->for($this->user)->create(['store_id' => $this->stores[$store]->id, 'purchased_at' => $date, 'total_cents' => (int) round($cents * $quantity)]);
        $item = ReceiptItem::factory()->for($receipt)->matchedTo($product)->create([
            'quantity' => $quantity, 'unit_price_cents' => $cents, 'total_price_cents' => (int) round($cents * $quantity),
        ]);
        $item->syncPriceObservation();
    }

    public function testCountsAndRecentReceipts()
    {
        $milk = Product::factory()->create(['name' => 'Milk']);
        $this->bought($milk, 'Woolworths', 450, '2026-10-08');
        $this->bought($milk, 'Coles', 400, '2026-10-10');
        Receipt::factory()->for($this->user)->create(['status' => ReceiptStatus::NeedsReview, 'purchased_at' => '2026-09-01', 'total_cents' => 1000]);
        Receipt::factory()->create(); // someone else's

        $this->be($this->user)->getJson('dashboard')
            ->assertSuccessful()
            ->assertJsonPath('data.counts', ['receipts' => 3, 'needs_review' => 1, 'products' => 1, 'stores' => 2])
            ->assertJsonPath('data.spent_this_month_cents', 850)
            ->assertJsonCount(3, 'data.recent_receipts')
            ->assertJsonPath('data.recent_receipts.0.store', 'Coles')
            ->assertJsonPath('data.recent_receipts.1.store', 'Woolworths');
    }

    public function testRecentPriceChangesCheaperAndDearer()
    {
        $milk = Product::factory()->create(['name' => 'Milk']);
        $bread = Product::factory()->create(['name' => 'Bread']);
        $eggs = Product::factory()->create(['name' => 'Eggs']);
        $this->bought($milk, 'Drakes', 450, '2026-10-01');
        $this->bought($milk, 'Drakes', 380, '2026-10-15');
        $this->bought($bread, 'Coles', 350, '2026-10-02');
        $this->bought($bread, 'Coles', 400, '2026-10-12');
        $this->bought($eggs, 'Coles', 600, '2026-10-03');
        $this->bought($eggs, 'Coles', 600, '2026-10-13'); // unchanged
        // A change long ago isn't recent.
        $this->bought($eggs, 'Woolworths', 500, '2026-06-01');
        $this->bought($eggs, 'Woolworths', 650, '2026-07-01');

        $this->be($this->user)->getJson('dashboard')
            ->assertJsonCount(2, 'data.recent_changes')
            ->assertJsonPath('data.recent_changes.0.product.name', 'Milk')
            ->assertJsonPath('data.recent_changes.0.from_cents', 450)
            ->assertJsonPath('data.recent_changes.0.to_cents', 380)
            ->assertJsonPath('data.cheaper.0.product.name', 'Milk')
            ->assertJsonPath('data.cheaper.0.change_cents', -70)
            ->assertJsonPath('data.dearer.0.product.name', 'Bread')
            ->assertJsonPath('data.dearer.0.change_cents', 50);
    }

    public function testSavingsOpportunitiesAndPotentialSavingsThisMonth()
    {
        $milk = Product::factory()->create(['name' => 'Milk']);
        $rice = Product::factory()->create(['name' => 'Rice']);
        $this->bought($milk, 'Woolworths', 450, '2026-10-08', 2);
        $this->bought($milk, 'Drakes', 380, '2026-09-20');
        $this->bought($rice, 'Coles', 1300, '2026-10-05');
        $this->bought($rice, 'Woolworths', 1500, '2026-09-25');

        $this->be($this->user)->getJson('dashboard')
            ->assertJsonCount(1, 'data.savings_opportunities')
            ->assertJsonPath('data.savings_opportunities.0.product.name', 'Milk')
            ->assertJsonPath('data.savings_opportunities.0.paid_at.name', 'Woolworths')
            ->assertJsonPath('data.savings_opportunities.0.cheapest_at.name', 'Drakes')
            ->assertJsonPath('data.savings_opportunities.0.saving_cents', 70)
            // October: 2 milks at Woolworths, 70c each dearer than Drakes. Rice was bought at the cheapest.
            ->assertJsonPath('data.potential_savings_this_month_cents', 140);
    }

    public function testOtherUsersPricesDoNotShow()
    {
        PriceObservation::factory()->create(['user_id' => $this->createStandardUser()->id]);

        $this->be($this->user)->getJson('dashboard')
            ->assertJsonPath('data.counts.products', 0)
            ->assertJsonPath('data.recent_changes', [])
            ->assertJsonPath('data.savings_opportunities', [])
            ->assertJsonPath('data.potential_savings_this_month_cents', 0);
    }
}
