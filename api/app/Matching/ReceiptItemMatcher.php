<?php

namespace App\Matching;

use App\Models\IgnoredWording;
use App\Models\Product;
use App\Models\ProductAlias;
use App\Models\ReceiptItem;
use App\Models\User;
use App\Support\Enums\MatchSource;
use App\Support\Enums\MatchStatus;
use Illuminate\Support\Facades\DB;

/**
 * Applies match decisions to receipt items: automatic ones from the matcher, and the user's
 * own (confirm, new product, ignore). Every decision rebuilds the item's price history entry,
 * and a user's decision is remembered (a product alias, or an ignored wording) so the same
 * wording is settled automatically next time.
 */
class ReceiptItemMatcher
{
    public function __construct(private ProductMatcher $matcher, private LineNormaliser $normaliser)
    {
    }

    /**
     * Match automatically. Leaves decisions the user already made alone.
     *
     * @param  bool  $mayIgnore  false when the user just said the line IS a product
     */
    public function autoMatch(ReceiptItem $item, bool $mayIgnore = true): void
    {
        if ($item->matched_by === MatchSource::User) {
            return;
        }

        if ($mayIgnore && $this->isNotAProduct($item)) {
            $this->markIgnoredByRule($item);

            return;
        }

        $candidates = $this->matcher->candidates($item, 2);
        [$best, $runnerUp] = [$candidates[0] ?? null, $candidates[1] ?? null];
        $confidence = $best?->confidence ?? 0;
        $isClearWinner = !$runnerUp || $confidence - $runnerUp->confidence >= config('matching.ambiguity_margin');

        $status = match (true) {
            $confidence >= config('matching.auto') && $isClearWinner => MatchStatus::AutoMatched,
            $confidence >= config('matching.suggest') => MatchStatus::Suggested,
            default => MatchStatus::Pending,
        };

        $item->update([
            'normalized_name' => $this->normalisedText($item),
            'product_id' => $status === MatchStatus::AutoMatched ? $best->product->id : null,
            'suggested_product_id' => $status === MatchStatus::Suggested ? $best->product->id : null,
            'match_status' => $status,
            'match_confidence' => $best ? $confidence : null,
            'matched_by' => $status === MatchStatus::Pending ? null : MatchSource::Rule,
            'matched_by_user_id' => null,
            'matched_at' => $status === MatchStatus::Pending ? null : now(),
        ]);

        $item->syncPriceObservation();
    }

    /**
     * The user says this line is this product (an existing one, or one they just created).
     */
    public function confirm(ReceiptItem $item, Product $product, User $user, bool $isNewProduct = false): void
    {
        DB::transaction(function () use ($item, $product, $user, $isNewProduct) {
            $this->decide($item, $user, $isNewProduct ? MatchStatus::NewProduct : MatchStatus::Confirmed, $product);
            $this->rememberAlias($item, $product, $user);
            $item->receipt->refreshStatus();
        });

        $this->matchSameWordingElsewhere($item);
    }

    /**
     * The line isn't a product (bag fee, deposit, rounding...), or undo that.
     */
    public function ignore(ReceiptItem $item, User $user, bool $ignore = true): void
    {
        DB::transaction(function () use ($item, $user, $ignore) {
            if ($ignore) {
                $this->decide($item, $user, MatchStatus::Ignored, null);
                IgnoredWording::firstOrCreate(
                    ['store_id' => $item->receipt->store_id, 'normalized_text' => $item->normalized_name],
                    ['confirmed_by' => $user->id],
                );
            } else {
                // Undo: stop skipping this wording at this store, then match the line normally.
                IgnoredWording::where('store_id', $item->receipt->store_id)->where('normalized_text', $item->normalized_name)->delete();
                $item->update(['matched_by' => null]);
                $this->autoMatch($item, mayIgnore: false);
            }

            $item->receipt->refreshStatus();
        });

        if ($ignore) {
            $this->matchSameWordingElsewhere($item);
        }
    }

    /**
     * A fee, deposit or adjustment (config/matching.php), or a wording the user marked
     * "not a product" at this store before.
     */
    private function isNotAProduct(ReceiptItem $item): bool
    {
        $text = mb_strtoupper($item->original_name);

        foreach (config('matching.not_products') as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return IgnoredWording::where('store_id', $item->receipt->store_id)
            ->where('normalized_text', $this->normalisedText($item))
            ->exists();
    }

    private function markIgnoredByRule(ReceiptItem $item): void
    {
        $item->update([
            'normalized_name' => $this->normalisedText($item),
            'product_id' => null,
            'suggested_product_id' => null,
            'match_status' => MatchStatus::Ignored,
            'match_confidence' => 100,
            'matched_by' => MatchSource::Rule,
            'matched_by_user_id' => null,
            'matched_at' => now(),
        ]);

        $item->syncPriceObservation();
    }

    private function decide(ReceiptItem $item, User $user, MatchStatus $status, ?Product $product): void
    {
        $item->update([
            'normalized_name' => $this->normalisedText($item),
            'product_id' => $product?->id,
            'suggested_product_id' => null,
            'match_status' => $status,
            'match_confidence' => 100,
            'matched_by' => MatchSource::User,
            'matched_by_user_id' => $user->id,
            'matched_at' => now(),
        ]);

        $item->syncPriceObservation();
    }

    private function rememberAlias(ReceiptItem $item, Product $product, User $user): void
    {
        $alias = ProductAlias::firstOrNew([
            'store_id' => $item->receipt->store_id,
            'normalized_text' => $item->normalized_name,
        ]);

        $alias->fill([
            'product_id' => $product->id,
            'sku' => $item->sku ?? $alias->sku,
            'source' => MatchSource::User,
            'confirmed_by' => $user->id,
            'times_seen' => $alias->exists ? $alias->times_seen + 1 : 1,
        ])->save();
    }

    /**
     * The user's other receipts waiting on the same wording at the same store match now too.
     */
    private function matchSameWordingElsewhere(ReceiptItem $item): void
    {
        ReceiptItem::query()
            ->whereKeyNot($item->id)
            ->where('normalized_name', $item->normalized_name)
            ->whereIn('match_status', [MatchStatus::Pending, MatchStatus::Suggested])
            ->whereHas('receipt', fn ($query) => $query
                ->where('user_id', $item->receipt->user_id)
                ->where('store_id', $item->receipt->store_id))
            ->with('receipt')
            ->get()
            ->each(function (ReceiptItem $other) {
                $this->autoMatch($other);
                $other->receipt->refreshStatus();
            });
    }

    private function normalisedText(ReceiptItem $item): string
    {
        return $this->normaliser->normalise($item->original_name)->text;
    }
}
