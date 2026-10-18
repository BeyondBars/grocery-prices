<?php

namespace App\Http\Controllers;

use App\Http\Requests\Catalog\SaveProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\PriceObservation;
use App\Models\Product;
use App\Models\ProductAlias;
use App\Models\ReceiptItem;
use App\Models\ShoppingListItem;
use App\Models\User;
use App\Pricing\ProductPriceSummary;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ProductController extends Controller
{
    /**
     * Products with the cheapest latest price the user has seen for each. "filter[priced]=1"
     * lists only products the user has prices for.
     */
    public function index(Request $request)
    {
        $products = QueryBuilder::for(Product::active()->with('brand', 'category'))
            ->allowedFilters(
                AllowedFilter::exact('brand', 'brand_id'),
                AllowedFilter::exact('category', 'category_id'),
                AllowedFilter::callback('priced', fn ($query, $value) => $value && $query->whereHas(
                    'priceObservations',
                    fn ($query) => $query->visibleTo($request->user())
                )),
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($query) use ($value) {
                        $query->where('match_key', 'like', '%'.Product::normalise($value).'%')
                            ->orWhere('barcode', $value);
                    });
                }),
            )
            ->allowedSorts('name', 'created_at')
            ->defaultSort('name')
            ->paginate(25);

        $this->attachCheapestPrices($products->getCollection(), $request->user());

        return ProductResource::collection($products);
    }

    /**
     * Latest price at each store, cheapest first, with lowest / highest / average.
     */
    public function prices(Request $request, Product $product): JsonResponse
    {
        return response()->json(['data' => (new ProductPriceSummary($product, $request->user()))->toArray()]);
    }

    /**
     * Every recorded price over time, grouped by store, for the price chart.
     */
    public function priceHistory(Request $request, Product $product): JsonResponse
    {
        return response()->json(['data' => (new ProductPriceSummary($product, $request->user()))->history()]);
    }

    /**
     * One query for the whole page: each product's cheapest latest store price, preferring
     * prices that aren't out of date.
     */
    private function attachCheapestPrices(Collection $products, User $user): void
    {
        $cheapest = PriceObservation::latestPerStore($user, $products->modelKeys())
            ->with('store')
            ->get()
            ->sortBy([fn ($a, $b) => $a->isStale() <=> $b->isStale(), ['price_cents', 'asc']])
            ->groupBy('product_id')
            ->map(fn ($prices) => [
                'price_cents' => $prices->first()->price_cents,
                'unit' => $prices->first()->unit->value,
                'store' => ['id' => $prices->first()->store->id, 'name' => $prices->first()->store->name],
                'stores_count' => $prices->count(),
            ]);

        $products->each(fn (Product $product) => $product->cheapest_price = $cheapest->get($product->id));
    }

    public function show(Product $product): ProductResource
    {
        return new ProductResource($product->load('brand', 'category'));
    }

    public function store(SaveProductRequest $request): ProductResource
    {
        $this->authorize('create', Product::class);

        $product = Product::create([...$request->productAttributes(), 'created_by' => $request->user()->id]);

        return new ProductResource($product->load('brand', 'category'));
    }

    public function update(SaveProductRequest $request, Product $product): ProductResource
    {
        $this->authorize('update', $product);

        $product->update($request->productAttributes());

        return new ProductResource($product->load('brand', 'category'));
    }

    /**
     * Merge a duplicate into the product to keep. Its receipt lines, prices and receipt wordings
     * move across; the duplicate stays (with merged_into_id) so nothing that pointed at it breaks.
     */
    public function merge(Request $request, Product $product): ProductResource
    {
        $this->authorize('merge', $product);

        $request->validate([
            'into_product_id' => ['required', 'integer', Rule::notIn([$product->id]), Rule::exists('products', 'id')->whereNull('merged_into_id')],
        ]);
        abort_if($product->merged_into_id !== null, Response::HTTP_CONFLICT, 'This product was already merged.');

        $into = Product::findOrFail($request->integer('into_product_id'));

        DB::transaction(function () use ($product, $into) {
            ReceiptItem::where('product_id', $product->id)->update(['product_id' => $into->id]);
            ReceiptItem::where('suggested_product_id', $product->id)->update(['suggested_product_id' => $into->id]);
            PriceObservation::where('product_id', $product->id)->update(['product_id' => $into->id]);
            ProductAlias::where('product_id', $product->id)->update(['product_id' => $into->id]);
            ShoppingListItem::where('product_id', $product->id)->update(['product_id' => $into->id]);

            $barcode = $product->barcode;
            $product->forceFill(['merged_into_id' => $into->id, 'barcode' => null])->save();

            if ($barcode && !$into->barcode) {
                $into->update(['barcode' => $barcode]);
            }
        });

        return new ProductResource($into->refresh()->load('brand', 'category'));
    }

    public function destroy(Product $product): Response
    {
        $this->authorize('delete', $product);

        abort_if($product->isInUse(), Response::HTTP_CONFLICT, 'This product has receipts or prices. Merge it into another product instead.');

        $product->delete();

        return response()->noContent();
    }
}
