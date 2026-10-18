<?php

namespace App\Http\Controllers;

use App\Http\Requests\Catalog\SaveProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\PriceObservation;
use App\Models\Product;
use App\Models\ProductAlias;
use App\Models\ReceiptItem;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ProductController extends Controller
{
    public function index()
    {
        return ProductResource::collection(
            QueryBuilder::for(Product::active()->with('brand', 'category'))
                ->allowedFilters(
                    AllowedFilter::exact('brand', 'brand_id'),
                    AllowedFilter::exact('category', 'category_id'),
                    AllowedFilter::callback('search', function ($query, $value) {
                        $query->where(function ($query) use ($value) {
                            $query->where('match_key', 'like', '%'.Product::normalise($value).'%')
                                ->orWhere('barcode', $value);
                        });
                    }),
                )
                ->allowedSorts('name', 'created_at')
                ->defaultSort('name')
                ->paginate(25)
        );
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
