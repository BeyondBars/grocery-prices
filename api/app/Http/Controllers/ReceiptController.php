<?php

namespace App\Http\Controllers;

use App\Http\Requests\Receipt\UpdateReceiptRequest;
use App\Http\Resources\ReceiptResource;
use App\Models\Receipt;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ReceiptController extends Controller
{
    /**
     * The signed in user's receipts, newest first.
     */
    public function index(Request $request)
    {
        return ReceiptResource::collection(
            QueryBuilder::for(Receipt::whereBelongsTo($request->user())->with('store')->withCount('items'))
                ->allowedFilters(AllowedFilter::exact('status'), AllowedFilter::exact('store', 'store_id'))
                ->allowedSorts('purchased_at', 'created_at', 'total_cents')
                ->defaultSort('-created_at')
                ->paginate(20)
        );
    }

    public function show(Receipt $receipt): ReceiptResource
    {
        $this->authorize('view', $receipt);

        return new ReceiptResource($receipt->load('store', 'items.product.brand', 'items.suggestedProduct'));
    }

    /**
     * Correct the header. A changed store or date moves the receipt's prices with it.
     */
    public function update(UpdateReceiptRequest $request, Receipt $receipt): ReceiptResource
    {
        $this->authorize('update', $receipt);

        DB::transaction(function () use ($request, $receipt) {
            $receipt->update($request->validated());
            $receipt->syncPriceObservations();
        });

        return new ReceiptResource($receipt->load('store', 'items.product.brand', 'items.suggestedProduct'));
    }

    /**
     * Soft delete: the receipt stops counting towards price history but can be restored.
     */
    public function destroy(Receipt $receipt): Response
    {
        $this->authorize('delete', $receipt);

        $receipt->delete();

        return response()->noContent();
    }
}
