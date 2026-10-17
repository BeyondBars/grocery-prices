<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Support\Enums\UserAbility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryAndBrandTest extends TestCase
{
    use RefreshDatabase;

    public function testOnlyCatalogManagersCanChangeCategories()
    {
        $dairy = Category::factory()->create(['name' => 'Dairy']);

        $this->beUser()->getJson('categories')->assertJsonCount(1, 'data');
        $this->postJson('categories', ['name' => 'Milk', 'parent_id' => $dairy->id])->assertForbidden();

        $this->beUserAbleTo(UserAbility::ManageCatalog->value)
            ->postJson('categories', ['name' => 'Milk', 'parent_id' => $dairy->id])
            ->assertCreated()
            ->assertJsonPath('data.parent_id', $dairy->id)
            ->assertJsonPath('data.slug', 'milk');
    }

    public function testCategoryNamesMustBeUniqueAndNotTheirOwnParent()
    {
        $dairy = Category::factory()->create(['name' => 'Soft Drinks']);

        $this->beUserAbleTo(UserAbility::ManageCatalog->value);
        $this->postJson('categories', ['name' => 'Soft-drinks'])->assertJsonValidationErrors('name');
        $this->putJson("categories/{$dairy->id}", ['name' => 'Soft Drinks', 'parent_id' => $dairy->id])
            ->assertJsonValidationErrors('parent_id')
            ->assertJsonMissingValidationErrors('name');
    }

    public function testUsersCanAddBrandsWithReceiptAliases()
    {
        $this->beUser()->postJson('brands', ['name' => "Paul's", 'aliases' => ['pauls']])
            ->assertCreated()
            ->assertJsonPath('data.aliases', ['PAULS']);

        Brand::factory()->create(['name' => 'Coca-Cola']);
        $this->getJson('brands?filter[search]=coca')->assertJsonCount(1, 'data');
    }
}
