<?php

namespace Tests\Feature;

use App\Models\PriceObservation;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Support\Enums\ItemUnit;
use App\Support\Enums\PriceSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceComparisonTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Product $milk;

    /** @var array<string, Store> */
    private array $stores = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-17');
        $this->user = $this->createStandardUser();
        $this->milk = Product::factory()->create(['name' => 'Full Cream Milk']);

        foreach (['Woolworths', 'Coles', 'Drakes'] as $name) {
            $this->stores[$name] = Store::factory()->create(['name' => $name]);
        }
    }

    private function price(string $store, int $cents, string $date, array $attributes = []): PriceObservation
    {
        return PriceObservation::factory()->create([
            'product_id' => $this->milk->id,
            'store_id' => $this->stores[$store]->id,
            'user_id' => $this->user->id,
            'price_cents' => $cents,
            'regular_price_cents' => $cents,
            'observed_on' => $date,
            ...$attributes,
        ]);
    }

    /**
     * The example from the product spec.
     */
    private function specExample(): void
    {
        $this->price('Woolworths', 450, '2026-10-01');
        $this->price('Woolworths', 450, '2026-10-08');
        $this->price('Coles', 400, '2026-10-04');
        $this->price('Drakes', 380, '2026-10-08');
    }

    public function testComparesTheLatestPriceAtEachStoreCheapestFirst()
    {
        $this->specExample();

        $this->be($this->user)->getJson("products/{$this->milk->id}/prices")
            ->assertSuccessful()
            ->assertJsonPath('data.unit', 'each')
            ->assertJsonPath('data.stores.*.store.name', ['Drakes', 'Coles', 'Woolworths'])
            ->assertJsonPath('data.stores.*.price_cents', [380, 400, 450])
            ->assertJsonPath('data.cheapest.store.name', 'Drakes')
            ->assertJsonPath('data.saving', ['cents' => 70, 'compared_with' => ['id' => $this->stores['Woolworths']->id, 'name' => 'Woolworths']])
            ->assertJsonPath('data.stats.lowest.price_cents', 380)
            ->assertJsonPath('data.stats.highest.price_cents', 450)
            ->assertJsonPath('data.stats.average_cents', 410)
            ->assertJsonPath('data.stats.average_recorded_cents', 420)
            ->assertJsonPath('data.stats.count', 4)
            ->assertJsonPath('data.stores.2.previous_price_cents', 450);
    }

    public function testShowsPriceChangesPromotionsAndOutOfDatePrices()
    {
        $this->price('Coles', 400, '2026-07-01');
        $this->price('Coles', 350, '2026-07-20', ['regular_price_cents' => 400, 'is_promotion' => true]);
        $this->price('Drakes', 380, '2026-10-08');

        $this->be($this->user)->getJson("products/{$this->milk->id}/prices")
            ->assertJsonPath('data.stores.0.store.name', 'Drakes')
            ->assertJsonPath('data.stores.0.is_stale', false)
            ->assertJsonPath('data.stores.1.store.name', 'Coles')
            ->assertJsonPath('data.stores.1.previous_price_cents', 400)
            ->assertJsonPath('data.stores.1.is_promotion', true)
            ->assertJsonPath('data.stores.1.regular_price_cents', 400)
            ->assertJsonPath('data.stores.1.is_stale', true);
    }

    public function testAnOldSpecialNeverMakesAStoreTheCheapest()
    {
        $this->specExample();
        $this->price('Coles', 300, '2026-06-01', ['is_promotion' => true]);
        $this->price('Coles', 300, '2026-06-02', ['store_id' => Store::factory()->create(['name' => 'Foodland'])->id, 'is_promotion' => true]);

        $this->be($this->user)->getJson("products/{$this->milk->id}/prices")
            ->assertJsonPath('data.stores.*.store.name', ['Drakes', 'Coles', 'Woolworths', 'Foodland'])
            ->assertJsonPath('data.stores.3.is_stale', true)
            ->assertJsonPath('data.cheapest.store.name', 'Drakes')
            ->assertJsonPath('data.saving.cents', 70)
            ->assertJsonPath('data.stats.average_cents', 410)
            ->assertJsonPath('data.stats.lowest.price_cents', 300);

        $this->getJson('products')->assertJsonPath('data.0.cheapest_price.store.name', 'Drakes');
    }

    public function testWhenEveryPriceIsOldTheyStillCompare()
    {
        $this->price('Coles', 400, '2026-05-01');
        $this->price('Drakes', 380, '2026-05-01');

        $this->be($this->user)->getJson("products/{$this->milk->id}/prices")
            ->assertJsonPath('data.cheapest.store.name', 'Drakes')
            ->assertJsonPath('data.saving.cents', 20);
    }

    public function testOtherUsersReceiptsArePrivateButSharedPricesAreVisible()
    {
        $this->price('Coles', 100, '2026-10-10', ['user_id' => $this->createStandardUser()->id]);
        $this->price('Drakes', 380, '2026-10-10', ['user_id' => null, 'source' => PriceSource::Online]);

        $this->be($this->user)->getJson("products/{$this->milk->id}/prices")
            ->assertJsonCount(1, 'data.stores')
            ->assertJsonPath('data.cheapest.store.name', 'Drakes')
            ->assertJsonPath('data.saving', null);
    }

    public function testNeverComparesPerKiloWithPerItemPrices()
    {
        $this->price('Coles', 120, '2026-09-01');
        $this->price('Drakes', 490, '2026-10-08', ['unit' => ItemUnit::Kilogram]);

        $this->be($this->user)->getJson("products/{$this->milk->id}/prices")
            ->assertJsonPath('data.unit', 'kg')
            ->assertJsonCount(1, 'data.stores');
    }

    public function testAProductWithoutPricesHasAnEmptySummary()
    {
        $this->be($this->user)->getJson("products/{$this->milk->id}/prices")
            ->assertJsonPath('data', ['unit' => null, 'stores' => [], 'cheapest' => null, 'saving' => null, 'stats' => null]);
    }

    public function testHistoryGroupsPricesByStoreInDateOrder()
    {
        $this->specExample();

        $this->be($this->user)->getJson("products/{$this->milk->id}/price-history")
            ->assertJsonPath('data.*.store.name', ['Coles', 'Drakes', 'Woolworths'])
            ->assertJsonPath('data.2.points.*.date', ['2026-10-01', '2026-10-08'])
            ->assertJsonPath('data.2.points.0.price_cents', 450);
    }

    public function testProductListShowsTheCheapestPriceAndCanListOnlyPricedProducts()
    {
        $this->specExample();
        Product::factory()->create(['name' => 'Never Bought']);

        $this->be($this->user)->getJson('products')
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Full Cream Milk')
            ->assertJsonPath('data.0.cheapest_price.price_cents', 380)
            ->assertJsonPath('data.0.cheapest_price.store.name', 'Drakes')
            ->assertJsonPath('data.0.cheapest_price.stores_count', 3)
            ->assertJsonPath('data.1.cheapest_price', null);

        $this->getJson('products?filter[priced]=1')->assertJsonCount(1, 'data');
        $this->beUser()->getJson('products?filter[priced]=1')->assertJsonCount(0, 'data');
    }
}
