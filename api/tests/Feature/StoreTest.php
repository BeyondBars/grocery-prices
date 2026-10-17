<?php

namespace Tests\Feature;

use App\Models\Receipt;
use App\Models\Store;
use App\Support\Enums\UserAbility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    public function testAnyUserCanListAndAddStores()
    {
        Store::factory()->create(['name' => 'Coles']);

        $this->beUser()->postJson('stores', ['name' => 'Drakes', 'abn' => '12 345 678 901', 'aliases' => [' drakes ', 'DRAKES', '']])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'drakes')
            ->assertJsonPath('data.abn', '12345678901')
            ->assertJsonPath('data.aliases', ['DRAKES'])
            ->assertJsonPath('data.can.update', true)
            ->assertJsonPath('data.can.delete', false);

        $this->getJson('stores')->assertJsonCount(2, 'data')->assertJsonPath('data.0.name', 'Coles');
        $this->getJson('stores?filter[search]=dra')->assertJsonCount(1, 'data');
    }

    public function testStoreNamesAreUnique()
    {
        Store::factory()->create(['name' => 'Coles']);

        $this->beUser()->postJson('stores', ['name' => 'Coles'])->assertJsonValidationErrors('name');
    }

    public function testOnlyTheCreatorOrACatalogManagerCanEditAStore()
    {
        $store = Store::factory()->create(['name' => 'Coles']);

        $this->beUser()->putJson("stores/{$store->id}", ['name' => 'Coles Local'])->assertForbidden();
        $this->beUserAbleTo(UserAbility::ManageCatalog->value)->putJson("stores/{$store->id}", ['name' => 'Coles Local'])
            ->assertSuccessful()
            ->assertJsonPath('data.name', 'Coles Local');
    }

    public function testOnlyCatalogManagersCanDeleteStoresAndNeverOnesWithReceipts()
    {
        $user = $this->createStandardUser();
        $unused = Store::factory()->create(['created_by' => $user->id]);
        $used = Store::factory()->create();
        Receipt::factory()->create(['store_id' => $used->id]);

        $this->be($user)->deleteJson("stores/{$unused->id}")->assertForbidden();

        $this->beUserAbleTo(UserAbility::ManageCatalog->value);
        $this->deleteJson("stores/{$used->id}")->assertConflict();
        $this->deleteJson("stores/{$unused->id}")->assertNoContent();
        $this->assertModelMissing($unused);
    }
}
