<?php

namespace App\Http\Controllers;

use App\Http\Requests\Catalog\SaveStoreRequest;
use App\Http\Resources\StoreResource;
use App\Models\Store;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class StoreController extends Controller
{
    /**
     * All stores (there are only ever a handful of chains, so no pagination).
     */
    public function index()
    {
        return StoreResource::collection(
            QueryBuilder::for(Store::class)
                ->allowedFilters(AllowedFilter::partial('search', 'name'))
                ->defaultSort('name')
                ->get()
        );
    }

    public function show(Store $store): StoreResource
    {
        return new StoreResource($store);
    }

    public function store(SaveStoreRequest $request): StoreResource
    {
        $this->authorize('create', Store::class);

        return new StoreResource(Store::create([...$request->validated(), 'created_by' => $request->user()->id]));
    }

    public function update(SaveStoreRequest $request, Store $store): StoreResource
    {
        $this->authorize('update', $store);

        $store->update($request->validated());

        return new StoreResource($store);
    }

    public function destroy(Store $store): Response
    {
        $this->authorize('delete', $store);

        abort_if($store->isInUse(), Response::HTTP_CONFLICT, 'This store has receipts or prices, so it cannot be deleted.');

        $store->delete();

        return response()->noContent();
    }
}
