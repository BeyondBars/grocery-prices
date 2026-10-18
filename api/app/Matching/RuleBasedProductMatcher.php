<?php

namespace App\Matching;

use App\Models\Product;
use App\Models\ProductAlias;
use App\Models\ReceiptItem;
use Illuminate\Database\Eloquent\Builder;

/**
 * Scores products against a receipt line using what the line says: brand, size, pack count,
 * variant and the remaining words. Exact evidence (barcode, a wording a user confirmed before)
 * wins outright. A different size, pack count or variant rules a product out.
 */
class RuleBasedProductMatcher implements ProductMatcher
{
    public function __construct(private LineNormaliser $normaliser)
    {
    }

    public function candidates(ReceiptItem $item, int $limit = 5): array
    {
        $line = $this->normaliser->normalise($item->original_name);

        if ($exact = $this->exactMatch($item, $line)) {
            return [$exact];
        }

        return $this->candidateProducts($line)
            ->map(fn (Product $product) => $this->score($line, $product))
            ->filter(fn (MatchCandidate $candidate) => $candidate->confidence > 0)
            ->sortByDesc('confidence')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * A barcode/SKU, or a wording a user already confirmed (at this store: 100, elsewhere: 97).
     */
    private function exactMatch(ReceiptItem $item, NormalisedLine $line): ?MatchCandidate
    {
        $storeId = $item->receipt->store_id;

        if ($item->sku) {
            $product = Product::active()->where('barcode', $item->sku)->first()
                ?? ProductAlias::where('store_id', $storeId)->where('sku', $item->sku)->first()?->product;

            if ($product) {
                return new MatchCandidate($this->current($product), 100, 'Same barcode');
            }
        }

        $aliases = ProductAlias::with('product')->where('normalized_text', $line->text)->get();
        $alias = $aliases->firstWhere('store_id', $storeId) ?? $aliases->sortByDesc('times_seen')->first();

        return $alias
            ? new MatchCandidate($this->current($alias->product), $alias->store_id === $storeId ? 100 : 97, 'Matched before')
            : null;
    }

    /**
     * Products sharing the brand or one of the line's longest words.
     */
    private function candidateProducts(NormalisedLine $line)
    {
        $words = collect($line->tokens)->sortByDesc(fn ($token) => mb_strlen($token))->take(3);

        if (!$line->brand && $words->isEmpty()) {
            return collect();
        }

        return Product::active()
            ->with('brand')
            ->where(function (Builder $query) use ($line, $words) {
                $line->brand && $query->orWhere('brand_id', $line->brand->id);
                $words->each(fn ($word) => $query->orWhere('match_key', 'like', "%{$word}%"));
            })
            ->limit(100)
            ->get();
    }

    private function score(NormalisedLine $line, Product $product): MatchCandidate
    {
        $other = $this->normaliser->normaliseProduct($product);

        // Hard rules: these are different products, however similar the words.
        if ($line->size && $other->size && !$line->size->equals($other->size)) {
            return new MatchCandidate($product, 0, 'Different size');
        }

        if ($line->packCount && $other->packCount > 1 && $line->packCount !== $other->packCount) {
            return new MatchCandidate($product, 0, 'Different pack size');
        }

        // Markers count as words here too: "PAULS FULL CREAM 2L" is still close to "Full Cream Milk".
        $score = 60 * $this->similarity([...$line->tokens, ...$line->markers], [...$other->tokens, ...$other->markers]);
        $reasons = [];

        [$brandPoints, $brandReason] = $this->brandPoints($line, $product);
        $score += $brandPoints;
        $brandReason && $reasons[] = $brandReason;

        if ($line->size && $other->size || !$line->size && !$other->size) {
            $score += 15;
            $line->size && $reasons[] = 'same size';
        }

        $cap = 100;

        // A size missing on one side means we can't be sure, so never auto-match.
        if ((bool) $line->size !== (bool) $other->size) {
            $cap = min($cap, 90);
            $reasons[] = 'size not on both';
        }

        if ($line->markers !== $other->markers) {
            $cap = min($cap, 40);
            $reasons[] = 'different variant';
        }

        if ($brandPoints < 0) {
            $cap = min($cap, 40);
        }

        return new MatchCandidate($product, (int) round(max(0, min($cap, $score))), ucfirst(implode(', ', $reasons)) ?: 'Similar name');
    }

    /**
     * @return array{0: int, 1: ?string}
     */
    private function brandPoints(NormalisedLine $line, Product $product): array
    {
        if ($line->brand && $product->brand_id) {
            return $line->brand->id === $product->brand_id ? [25, 'same brand'] : [-1, 'different brand'];
        }

        // Neither names a brand (fruit, veg, store-brand basics).
        if (!$line->brand && !$product->brand_id) {
            return [15, null];
        }

        return [0, null];
    }

    /**
     * Dice similarity of two word lists, counting near-identical long words (OCR slips like
     * "CREAN" for "CREAM") as the same.
     */
    private function similarity(array $a, array $b): float
    {
        if (!$a && !$b) {
            return 1.0;
        }

        if (!$a || !$b) {
            return 0.0;
        }

        $shared = 0;

        foreach ($a as $word) {
            foreach ($b as $index => $other) {
                if ($word === $other || (mb_strlen($word) >= 5 && levenshtein($word, $other) <= 1)) {
                    $shared++;
                    unset($b[$index]);
                    break;
                }
            }
        }

        return 2 * $shared / (count($a) + count($b) + $shared);
    }

    /**
     * Follow merges to the product that's still in use.
     */
    private function current(Product $product): Product
    {
        while ($product->merged_into_id) {
            $product = Product::findOrFail($product->merged_into_id);
        }

        return $product;
    }
}
