<?php

namespace App\Http\Controllers;

use App\Http\Requests\Receipt\UpdateReceiptItemRequest;
use App\Http\Resources\ReceiptItemResource;
use App\Models\ReceiptItem;
use Illuminate\Support\Facades\DB;

class ReceiptItemController extends Controller
{
    /**
     * Correct a misread line. Its price history entry is rebuilt from the new values.
     */
    public function update(UpdateReceiptItemRequest $request, ReceiptItem $receiptItem): ReceiptItemResource
    {
        abort_if($receiptItem->receipt->trashed(), 404);
        $this->authorize('update', $receiptItem->receipt);

        DB::transaction(function () use ($request, $receiptItem) {
            $receiptItem->update($request->validated());
            $receiptItem->syncPriceObservation();
        });

        return new ReceiptItemResource($receiptItem->load('product.brand', 'suggestedProduct'));
    }
}
