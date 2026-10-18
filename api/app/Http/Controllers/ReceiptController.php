<?php

namespace App\Http\Controllers;

use App\Http\Requests\Receipt\UpdateReceiptRequest;
use App\Http\Requests\Receipt\UploadReceiptsRequest;
use App\Http\Resources\ReceiptResource;
use App\Jobs\ProcessReceipt;
use App\Matching\ReceiptItemMatcher;
use App\Models\Receipt;
use App\Receipts\ReceiptUploader;
use App\Support\Enums\ReceiptStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

    /**
     * Upload one or more receipts. Each is stored and queued for processing straight away, so
     * this returns 202 with the receipts in the "uploaded" state. Files the user already
     * uploaded are skipped and listed under "duplicates".
     */
    public function store(UploadReceiptsRequest $request, ReceiptUploader $uploader): JsonResponse
    {
        $receipts = [];
        $duplicates = [];

        foreach ($request->file('files') as $file) {
            $receipt = $uploader->upload($request->user(), $file);
            $receipt ? $receipts[] = $receipt : $duplicates[] = $file->getClientOriginalName();
        }

        return ReceiptResource::collection(collect($receipts)->map->refresh())
            ->additional(['duplicates' => $duplicates])
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }

    /**
     * The original upload. Receipts are never public; this is the only way to see the file.
     */
    public function file(Receipt $receipt)
    {
        $this->authorize('view', $receipt);

        return Storage::disk(config('receipts.disk'))->response($receipt->file_path, null, [
            'Content-Type' => $receipt->file_mime,
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    /**
     * Read the receipt again. Re-uses the saved OCR text (free) unless "ocr" is set, which pays
     * for a fresh OCR. Replaces the receipt's items, so corrections made to them are lost.
     */
    public function reprocess(Request $request, Receipt $receipt): ReceiptResource
    {
        $this->authorize('update', $receipt);

        abort_if(in_array($receipt->status, [ReceiptStatus::Uploaded, ReceiptStatus::Processing], true), Response::HTTP_CONFLICT, 'This receipt is already being processed.');

        if ($request->boolean('ocr')) {
            $receipt->ocr_text = null;
            $receipt->parsed_data = null;
        }

        $receipt->update(['status' => ReceiptStatus::Uploaded, 'failure_reason' => null]);
        ProcessReceipt::dispatch($receipt);

        return new ReceiptResource($receipt->refresh()->load('store'));
    }

    public function show(Receipt $receipt): ReceiptResource
    {
        $this->authorize('view', $receipt);

        return new ReceiptResource($receipt->load('store', 'items.product.brand', 'items.suggestedProduct'));
    }

    /**
     * Correct the header. A changed store or date moves the receipt's prices with it.
     */
    public function update(UpdateReceiptRequest $request, Receipt $receipt, ReceiptItemMatcher $matcher): ReceiptResource
    {
        $this->authorize('update', $receipt);

        DB::transaction(function () use ($request, $receipt, $matcher) {
            $receipt->update($request->validated());

            if ($receipt->wasChanged('store_id')) {
                $receipt->items->each(fn ($item) => $matcher->autoMatch($item->setRelation('receipt', $receipt)));
            }

            $receipt->syncPriceObservations();
            $receipt->refreshStatus();
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
