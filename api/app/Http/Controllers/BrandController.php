<?php

namespace App\Http\Controllers;

use App\Http\Requests\Catalog\SaveBrandRequest;
use App\Http\Resources\BrandResource;
use App\Models\Brand;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class BrandController extends Controller
{
    public function index()
    {
        return BrandResource::collection(
            QueryBuilder::for(Brand::class)
                ->allowedFilters(AllowedFilter::partial('search', 'name'))
                ->defaultSort('name')
                ->paginate(50)
        );
    }

    public function store(SaveBrandRequest $request): BrandResource
    {
        $this->authorize('create', Brand::class);

        return new BrandResource(Brand::create([...$request->validated(), 'created_by' => $request->user()->id]));
    }

    public function update(SaveBrandRequest $request, Brand $brand): BrandResource
    {
        $this->authorize('update', $brand);

        $brand->update($request->validated());

        return new BrandResource($brand);
    }

    /**
     * Products of a deleted brand are kept, just without a brand.
     */
    public function destroy(Brand $brand): Response
    {
        $this->authorize('delete', $brand);

        $brand->delete();

        return response()->noContent();
    }
}
