<?php

namespace App\Http\Controllers;

use App\Http\Requests\Catalog\SaveProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Response;
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

    public function destroy(Product $product): Response
    {
        $this->authorize('delete', $product);

        abort_if($product->isInUse(), Response::HTTP_CONFLICT, 'This product has receipts or prices. Merge it into another product instead.');

        $product->delete();

        return response()->noContent();
    }
}
