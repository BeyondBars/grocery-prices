<?php

namespace Tests\Feature;

use App\Models\PriceObservation;
use App\Models\Product;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Models\Store;
use App\Models\User;
use App\Support\Enums\UserAbility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShoppingListTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ShoppingList $list;

    /** @var array<string, Product> */
    private array $products = [];

    /** @var array<string, Store> */
    private array $stores = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-17');
        $this->user = $this->createStandardUser();
        $this->list = ShoppingList::factory()->for($this->user)->create();

        // The spec's list: milk, bread, eggs and rice at three stores.
        $prices = [
            'Milk 2L' => ['Woolworths' => 450, 'Coles' => 400, 'Drakes' => 380],
            'Bread' => ['Woolworths' => 350, 'Coles' => 300, 'Drakes' => 400],
            'Eggs 12pk' => ['Woolworths' => 600, 'Coles' => 650, 'Drakes' => 550],
            'Rice 5kg' => ['Woolworths' => 1500, 'Coles' => 1400, 'Drakes' => 1300],
        ];

        foreach ($prices as $name => $byStore) {
            $this->products[$name] = Product::factory()->create(['name' => $name, 'size_amount' => null, 'size_unit' => null]);

            foreach ($byStore as $storeName => $cents) {
                $this->stores[$storeName] ??= Store::factory()->create(['name' => $storeName]);
                $this->price($name, $storeName, $cents);
            }
        }
    }

    private function price(string $product, string $store, int $cents, array $attributes = []): void
    {
        $this->stores[$store] ??= Store::factory()->create(['name' => $store]);

        PriceObservation::factory()->create([
            'product_id' => $this->products[$product]->id,
            'store_id' => $this->stores[$store]->id,
            'user_id' => $this->user->id,
            'price_cents' => $cents,
            'observed_on' => '2026-10-10',
            ...$attributes,
        ]);
    }

    private function addAll(): void
    {
        foreach ($this->products as $product) {
            ShoppingListItem::factory()->for($this->list)->create(['product_id' => $product->id]);
        }
    }

    public function testComparesTheListAcrossStoresAndRecommendsTheCheapest()
    {
        $this->addAll();

        $this->be($this->user)->getJson("shopping-lists/{$this->list->id}/comparison")
            ->assertSuccessful()
            ->assertJsonPath('data.stores.*.store.name', ['Drakes', 'Coles', 'Woolworths'])
            ->assertJsonPath('data.stores.*.total_cents', [2630, 2750, 2900])
            ->assertJsonPath('data.cheapest.store.name', 'Drakes')
            ->assertJsonPath('data.saving.cents', 270)
            ->assertJsonPath('data.saving.compared_with.name', 'Woolworths');
    }

    public function testSplitShopBuysEachItemWhereItIsCheapest()
    {
        $this->addAll();

        $this->be($this->user)->getJson("shopping-lists/{$this->list->id}/comparison")
            ->assertJsonPath('data.split.total_cents', 2530)
            ->assertJsonPath('data.split.saving_cents', 100)
            ->assertJsonPath('data.split.stores.0.store.name', 'Drakes')
            ->assertJsonPath('data.split.stores.0.items', ['Milk 2L', 'Eggs 12pk', 'Rice 5kg'])
            ->assertJsonPath('data.split.stores.1.store.name', 'Coles')
            ->assertJsonPath('data.split.stores.1.items', ['Bread']);
    }

    public function testAStoreMissingItemsIsNeverCheapestOverall()
    {
        $this->addAll();
        $this->price('Milk 2L', 'Aldi', 300);

        $this->be($this->user)->getJson("shopping-lists/{$this->list->id}/comparison")
            ->assertJsonPath('data.cheapest.store.name', 'Drakes')
            ->assertJsonPath('data.stores.3.store.name', 'Aldi')
            ->assertJsonPath('data.stores.3.total_cents', 300)
            ->assertJsonPath('data.stores.3.missing_count', 3)
            ->assertJsonPath('data.stores.3.missing', ['Bread', 'Eggs 12pk', 'Rice 5kg'])
            // The split shop can still use Aldi for milk.
            ->assertJsonPath('data.split.total_cents', 2450);
    }

    public function testQuantitiesNotesAndOutOfDatePrices()
    {
        ShoppingListItem::factory()->for($this->list)->create(['product_id' => $this->products['Milk 2L']->id, 'quantity' => 2]);
        ShoppingListItem::factory()->for($this->list)->create(['product_id' => null, 'free_text' => 'Bananas']);
        // An old special at Coles is ignored while current prices exist.
        $this->price('Milk 2L', 'Coles', 100, ['observed_on' => '2026-10-11']);
        PriceObservation::where('price_cents', 100)->update(['observed_on' => '2026-06-01']);

        $this->be($this->user)->getJson("shopping-lists/{$this->list->id}/comparison")
            ->assertJsonPath('data.cheapest.store.name', 'Drakes')
            ->assertJsonPath('data.cheapest.total_cents', 760)
            ->assertJsonPath('data.unpriced', ['Bananas'])
            ->assertJsonPath('data.items.0.prices.0.line_total_cents', 760);
    }

    public function testAnEmptyListHasNothingToCompare()
    {
        $this->be($this->user)->getJson("shopping-lists/{$this->list->id}/comparison")
            ->assertJsonPath('data', ['items' => [], 'stores' => [], 'cheapest' => null, 'saving' => null, 'split' => null, 'unpriced' => []]);
    }

    public function testUserManagesTheirListsAndItems()
    {
        $this->be($this->user);
        $list = $this->postJson('shopping-lists', ['name' => 'BBQ'])->assertCreated()->json('data');

        $milk = $this->postJson("shopping-lists/{$list['id']}/items", ['product_id' => $this->products['Milk 2L']->id])
            ->assertCreated()
            ->assertJsonPath('data.label', 'Milk 2L')
            ->assertJsonPath('data.quantity', 1)
            ->assertJsonPath('data.is_checked', false)
            ->json('data');
        // Adding the same product again adds to its quantity.
        $this->postJson("shopping-lists/{$list['id']}/items", ['product_id' => $this->products['Milk 2L']->id, 'quantity' => 2])
            ->assertJsonPath('data.id', $milk['id'])
            ->assertJsonPath('data.quantity', 3);
        $note = $this->postJson("shopping-lists/{$list['id']}/items", ['free_text' => 'Bread'])->assertJsonPath('data.label', 'Bread')->json('data');

        $this->putJson("shopping-lists/{$list['id']}/items/{$note['id']}", ['product_id' => $this->products['Bread']->id, 'is_checked' => true])
            ->assertJsonPath('data.label', 'Bread')
            ->assertJsonPath('data.product.id', $this->products['Bread']->id)
            ->assertJsonPath('data.is_checked', true);

        $this->getJson('shopping-lists')->assertJsonPath('data.0.name', 'BBQ')->assertJsonPath('data.0.items_count', 2)->assertJsonPath('data.0.unchecked_count', 1);
        $this->getJson("shopping-lists/{$list['id']}")->assertJsonCount(2, 'data.items');

        $this->deleteJson("shopping-lists/{$list['id']}/items/{$milk['id']}")->assertNoContent();
        $this->postJson("shopping-lists/{$list['id']}/items", [])->assertJsonValidationErrors(['product_id', 'free_text']);
        $this->deleteJson("shopping-lists/{$list['id']}")->assertNoContent();
        $this->assertDatabaseCount('shopping_list_items', 0);
    }

    public function testListsArePrivate()
    {
        $item = ShoppingListItem::factory()->for($this->list)->create();
        $otherList = ShoppingList::factory()->create();

        $this->beUser();
        $this->getJson('shopping-lists')->assertJsonCount(0, 'data');
        $this->getJson("shopping-lists/{$this->list->id}")->assertForbidden();
        $this->getJson("shopping-lists/{$this->list->id}/comparison")->assertForbidden();
        $this->postJson("shopping-lists/{$this->list->id}/items", ['free_text' => 'x'])->assertForbidden();

        // An item can't be reached through someone else's list.
        $this->be($otherList->user)->putJson("shopping-lists/{$otherList->id}/items/{$item->id}", ['is_checked' => true])->assertNotFound();
    }

    public function testMergingProductsMovesListItems()
    {
        $duplicate = Product::factory()->create();
        $item = ShoppingListItem::factory()->for($this->list)->create(['product_id' => $duplicate->id]);

        $this->beUserAbleTo(UserAbility::ManageCatalog->value)
            ->postJson("products/{$duplicate->id}/merge", ['into_product_id' => $this->products['Milk 2L']->id])
            ->assertSuccessful();

        $this->assertSame($this->products['Milk 2L']->id, $item->fresh()->product_id);
    }
}
