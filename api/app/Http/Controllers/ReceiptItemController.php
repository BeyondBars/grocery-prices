<?php

namespace App\Http\Controllers;

use App\Http\Requests\Receipt\MatchReceiptItemRequest;
use App\Http\Requests\Receipt\UpdateReceiptItemRequest;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ReceiptItemResource;
use App\Matching\LineNormaliser;
use App\Matching\MatchCandidate;
use App\Matching\ProductMatcher;
use App\Matching\ReceiptItemMatcher;
use App\Models\Product;
use App\Models\ReceiptItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReceiptItemController extends Controller
{
    /**
     * Correct a misread line. Its price history entry is rebuilt from the new values, and a
     * new name is matched again unless the user already chose the product.
     */
    public function update(UpdateReceiptItemRequest $request, ReceiptItem $receiptItem, ReceiptItemMatcher $matcher): ReceiptItemResource
    {
        $this->authorizeItem($receiptItem);

        DB::transaction(function () use ($request, $receiptItem, $matcher) {
            $receiptItem->update($request->validated());
            $receiptItem->wasChanged('original_name') ? $matcher->autoMatch($receiptItem) : $receiptItem->syncPriceObservation();
            $receiptItem->receipt->refreshStatus();
        });

        return $this->resource($receiptItem);
    }

    /**
     * Products this line could be, best first, plus a pre-filled "new product" from the line.
     */
    public function candidates(ReceiptItem $receiptItem, ProductMatcher $matcher, LineNormaliser $normaliser)
    {
        $this->authorizeItem($receiptItem);

        $line = $normaliser->normalise($receiptItem->original_name);
        $candidates = collect($matcher->candidates($receiptItem))->each(fn (MatchCandidate $candidate) => $candidate->product->loadMissing('brand', 'category'));

        return response()->json([
            'data' => $candidates->map(fn (MatchCandidate $candidate) => [
                'product' => new ProductResource($candidate->product),
                'confidence' => $candidate->confidence,
                'reason' => $candidate->reason,
            ])->values(),
            'new_product' => [
                'name' => Str::title($line->name),
                'brand' => $line->brand?->name,
                'variant' => $line->markers ? Str::title(implode(' ', $line->markers)) : null,
                'size' => $line->size?->label(),
                'pack_count' => $line->packCount ?? 1,
                'barcode' => strlen((string) $receiptItem->sku) >= 8 ? $receiptItem->sku : null,
            ],
        ]);
    }

    /**
     * The user's decision for a line: this product, a new product, or not a product at all.
     */
    public function match(MatchReceiptItemRequest $request, ReceiptItem $receiptItem, ReceiptItemMatcher $matcher): ReceiptItemResource
    {
        $this->authorizeItem($receiptItem);
        $user = $request->user();

        if ($request->has('ignore')) {
            $matcher->ignore($receiptItem, $user, $request->boolean('ignore'));
        } elseif ($request->filled('product_id')) {
            $matcher->confirm($receiptItem, Product::active()->findOrFail($request->integer('product_id')), $user);
        } else {
            $this->authorize('create', Product::class);
            $matcher->confirm($receiptItem, $this->createProduct($request), $user, isNewProduct: true);
        }

        return $this->resource($receiptItem);
    }

    /**
     * Create the product the user described, unless it already exists: same brand, name,
     * variant and size means they should pick that one instead of making a duplicate.
     */
    private function createProduct(MatchReceiptItemRequest $request): Product
    {
        $input = $request->validated('new_product');
        $product = new Product([
            ...collect($input)->except('size')->all(),
            'pack_count' => $input['pack_count'] ?? 1,
            ...Product::sizeAttributes($input['size'] ?? null),
            'created_by' => $request->user()->id,
        ]);

        $existing = Product::active()
            ->where('brand_id', $product->brand_id)
            ->where('match_key', Product::normalise(implode(' ', [$product->brand?->name, $product->name, $product->variant])))
            ->where('size_amount', $product->size_amount)
            ->where('pack_count', $product->pack_count)
            ->first();

        if ($existing) {
            throw ValidationException::withMessages(['new_product.name' => "This product already exists as \"{$existing->name}\". Choose it from the list instead."]);
        }

        $product->save();

        return $product;
    }

    private function authorizeItem(ReceiptItem $receiptItem): void
    {
        abort_if($receiptItem->receipt->trashed(), 404);
        $this->authorize('update', $receiptItem->receipt);
    }

    private function resource(ReceiptItem $receiptItem): ReceiptItemResource
    {
        return new ReceiptItemResource($receiptItem->refresh()->load('product.brand', 'suggestedProduct.brand'));
    }
}
