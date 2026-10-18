<?php

namespace Tests\Feature;

use App\Matching\ReceiptItemMatcher;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductAlias;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\Store;
use App\Support\Enums\MatchSource;
use App\Support\Enums\MatchStatus;
use App\Support\Enums\SizeUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The matching rules from the product spec.
 */
class ProductMatchingTest extends TestCase
{
    use RefreshDatabase;

    private Product $paulsFullCream;

    protected function setUp(): void
    {
        parent::setUp();

        $pauls = Brand::factory()->create(['name' => "Paul's", 'aliases' => ['PAULS']]);
        $this->paulsFullCream = $this->product('Full Cream Milk', 2000, SizeUnit::Millilitres, $pauls, 'Full Cream');
        $this->product('Light Milk', 2000, SizeUnit::Millilitres, $pauls, 'Light');
        $this->product('Full Cream Milk', 1000, SizeUnit::Millilitres, $pauls, 'Full Cream');
    }

    private function product(string $name, ?float $size, ?SizeUnit $unit, ?Brand $brand = null, ?string $variant = null, array $attributes = []): Product
    {
        return Product::factory()->create([
            'name' => $name, 'size_amount' => $size, 'size_unit' => $unit, 'brand_id' => $brand?->id, 'variant' => $variant, ...$attributes,
        ]);
    }

    private function match(string $line, array $attributes = []): ReceiptItem
    {
        $item = ReceiptItem::factory()->create(['original_name' => $line, ...$attributes]);
        app(ReceiptItemMatcher::class)->autoMatch($item);

        return $item->refresh();
    }

    public static function paulsWordings(): array
    {
        return [
            ['PAULS FULL CREAM MILK 2L', MatchStatus::AutoMatched],
            ['PAULS FULL CREAM MILK 2 LT', MatchStatus::AutoMatched],
            ['PAULS MILK FULL CREAM 2L', MatchStatus::AutoMatched],
            ['PAULS FC MILK 2L', MatchStatus::AutoMatched],
            // No "MILK" on the line: likely, but the user confirms.
            ['PAULS FULL CREAM 2LTR', MatchStatus::Suggested],
        ];
    }

    #[DataProvider('paulsWordings')]
    public function testDifferentWordingsOfTheSameProductMatch(string $line, MatchStatus $expected)
    {
        $item = $this->match($line);

        $this->assertSame($expected, $item->match_status, "{$line} scored {$item->match_confidence}");
        $this->assertSame($this->paulsFullCream->id, $item->product_id ?? $item->suggested_product_id);
    }

    public function testADifferentSizeIsNeverTheSameProduct()
    {
        $coke = Brand::factory()->create(['name' => 'Coca-Cola', 'aliases' => ['COCA COLA', 'COKE']]);
        $small = $this->product('Soft Drink', 1250, SizeUnit::Millilitres, $coke);
        $large = $this->product('Soft Drink', 2000, SizeUnit::Millilitres, $coke);

        $this->assertSame($large->id, $this->match('COCA COLA SOFT DRINK 2L')->product_id);
        $this->assertSame($small->id, $this->match('COCA COLA SOFT DRINK 1.25L')->product_id);
        $this->assertNotContains($large->id, [$this->match('COCA COLA 600ML')->suggested_product_id]);
    }

    public function testADifferentVariantIsNeverTheSameProduct()
    {
        $item = $this->match('PAULS LITE MILK 2L');

        $this->assertNotSame($this->paulsFullCream->id, $item->product_id);
        $this->assertNotSame($this->paulsFullCream->id, $item->suggested_product_id);
        $this->assertSame('Light Milk', Product::find($item->product_id ?? $item->suggested_product_id)?->name);
    }

    public function testZeroSugarIsNotTheRegularDrink()
    {
        $coke = Brand::factory()->create(['name' => 'Coca-Cola', 'aliases' => ['COCA COLA']]);
        $this->product('Classic', 1250, SizeUnit::Millilitres, $coke);

        $item = $this->match('COCA COLA NO SUGAR 1.25L');

        $this->assertSame(MatchStatus::Pending, $item->match_status);
        $this->assertNull($item->suggested_product_id);
    }

    public function testUnknownProductsAreLeftForTheUser()
    {
        $item = $this->match('SUNRICE JASMINE RICE 5KG');

        $this->assertSame(MatchStatus::Pending, $item->match_status);
        $this->assertNull($item->product_id);
    }

    public function testNeverAutoMatchesWhenTwoProductsAreEquallyGood()
    {
        // A duplicate that slipped in before merging existed.
        $this->product('Full Cream Milk', 2000, SizeUnit::Millilitres, Brand::firstWhere('name', "Paul's"), 'Full Cream');

        $item = $this->match('PAULS FULL CREAM MILK 2L');

        $this->assertNotSame(MatchStatus::AutoMatched, $item->match_status);
    }

    public function testABarcodeMatchesOutright()
    {
        $this->paulsFullCream->update(['barcode' => '9310036001234']);

        $item = $this->match('MILK', ['sku' => '9310036001234']);

        $this->assertSame([MatchStatus::AutoMatched, 100], [$item->match_status, $item->match_confidence]);
        $this->assertSame($this->paulsFullCream->id, $item->product_id);
    }

    public function testAWordingConfirmedBeforeMatchesOutrightAndFollowsMerges()
    {
        $store = Store::factory()->create();
        $duplicate = $this->product('Milk FC', 2000, SizeUnit::Millilitres);
        ProductAlias::create(['product_id' => $duplicate->id, 'store_id' => $store->id, 'normalized_text' => 'PAULS FULL CREAM MILK 2L', 'source' => MatchSource::User]);
        $duplicate->forceFill(['merged_into_id' => $this->paulsFullCream->id])->save();

        $item = $this->match('PAULS F/C MILK 2L', ['receipt_id' => Receipt::factory()->create(['store_id' => $store->id])->id]);

        $this->assertSame([MatchStatus::AutoMatched, 100], [$item->match_status, $item->match_confidence]);
        $this->assertSame($this->paulsFullCream->id, $item->product_id);
    }

    public function testAutoMatchedItemsRecordAPriceButSuggestionsDoNot()
    {
        $this->match('PAULS FULL CREAM MILK 2L');
        $this->match('PAULS FULL CREAM 2LTR');

        $this->assertDatabaseCount('price_observations', 1);
    }

    public function testAutoMatchingNeverOverridesTheUser()
    {
        $item = ReceiptItem::factory()->create(['original_name' => 'PAULS FULL CREAM MILK 2L', 'match_status' => MatchStatus::Ignored, 'matched_by' => MatchSource::User]);

        app(ReceiptItemMatcher::class)->autoMatch($item);

        $this->assertSame(MatchStatus::Ignored, $item->refresh()->match_status);
    }
}
