<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ReceiptItem;
use App\Support\Enums\SizeUnit;
use App\Support\Enums\UserAbility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function testUserCanAddAProductWithASize()
    {
        $brand = Brand::factory()->create(['name' => "Paul's"]);
        $milk = Category::factory()->create(['name' => 'Milk']);

        $this->beUser()->postJson('products', [
            'name' => 'Full Cream Milk',
            'brand_id' => $brand->id,
            'category_id' => $milk->id,
            'variant' => 'Full Cream',
            'size' => '2 LT',
            'barcode' => '9310036001234',
        ])
            ->assertCreated()
            ->assertJsonPath('data.size', '2L')
            ->assertJsonPath('data.size_amount', 2000)
            ->assertJsonPath('data.size_unit', 'ml')
            ->assertJsonPath('data.pack_count', 1)
            ->assertJsonPath('data.brand.name', "Paul's")
            ->assertJsonPath('data.category.name', 'Milk');

        $this->assertDatabaseHas('products', [
            'name' => 'Full Cream Milk',
            'size_unit' => SizeUnit::Millilitres->value,
            'match_key' => 'PAULS FULL CREAM MILK FULL CREAM',
        ]);
    }

    public function testRejectsUnreadableSizesAndDuplicateBarcodes()
    {
        Product::factory()->create(['barcode' => '9310036001234']);

        $this->beUser()->postJson('products', ['name' => 'Milk', 'size' => 'big', 'barcode' => '9310036001234'])
            ->assertJsonValidationErrors(['size', 'barcode']);
    }

    public function testSearchMatchesNameBrandAndBarcode()
    {
        $brand = Brand::factory()->create(['name' => "Paul's"]);
        Product::factory()->create(['name' => 'Full Cream Milk', 'brand_id' => $brand->id]);
        Product::factory()->create(['name' => 'Wonder White Bread', 'barcode' => '9300000000001']);

        $this->beUser();
        $this->getJson('products?filter[search]=pauls full')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Full Cream Milk');
        $this->getJson('products?filter[search]=9300000000001')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Wonder White Bread');
    }

    public function testMergedProductsAreNotListed()
    {
        $kept = Product::factory()->create();
        Product::factory()->create(['merged_into_id' => $kept->id]);

        $this->beUser()->getJson('products')->assertJsonCount(1, 'data');
    }

    public function testOnlyTheCreatorOrACatalogManagerCanEditAProduct()
    {
        $owner = $this->createStandardUser();
        $product = Product::factory()->create(['created_by' => $owner->id]);

        $this->beUser()->putJson("products/{$product->id}", ['name' => 'Changed'])->assertForbidden();
        $this->be($owner)->putJson("products/{$product->id}", ['name' => 'Changed', 'size' => '700g'])
            ->assertSuccessful()
            ->assertJsonPath('data.size', '700g');
    }

    public function testProductsWithReceiptsCannotBeDeleted()
    {
        $product = Product::factory()->create();
        ReceiptItem::factory()->matchedTo($product)->create();

        $this->beUserAbleTo(UserAbility::ManageCatalog->value)->deleteJson("products/{$product->id}")->assertConflict();
        $this->assertModelExists($product);
    }
}
