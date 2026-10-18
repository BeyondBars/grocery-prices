<?php

namespace App\Matching;

use App\Models\Brand;
use App\Models\Product;
use App\Support\Size;
use Illuminate\Support\Collection;

/**
 * Turns "PAULS FC MILK 2 LT" into brand Paul's, size 2000 ml, tokens [MILK], markers [FULL CREAM],
 * so receipt lines and products can be compared on what they mean rather than how they're written.
 */
class LineNormaliser
{
    private const STOP_WORDS = ['THE', 'AND', 'WITH', 'OF', 'PACK', 'EACH', 'EA', 'NET'];

    /** @var Collection<int, Brand>|null */
    private ?Collection $brands = null;

    public function normalise(string $text, ?Brand $knownBrand = null): NormalisedLine
    {
        $text = Size::standardise($this->expandAbbreviations(Product::normalise($text)));
        $size = Size::parse($text);
        $packCount = preg_match('/\b(\d{1,3})\s*(?:PK|PACK|PCE|PCS|X)\b/', $text, $match) ? (int) $match[1] : null;

        $rest = preg_replace('/\b\d{1,3}\s*(?:PK|PACK|PCE|PCS|X)\b/', ' ', Size::strip($text));
        $brand = $knownBrand ?? $this->findBrand($rest);
        $rest = $this->removeBrand($rest, $brand);
        $name = trim(preg_replace('/\s+/', ' ', $rest));
        [$markers, $rest] = $this->extractMarkers($rest);

        return new NormalisedLine($text, $this->tokens($rest), $markers, $size, $packCount, $brand, $name);
    }

    public function normaliseProduct(Product $product): NormalisedLine
    {
        $line = $this->normalise(implode(' ', [$product->brand?->name, $product->name, $product->variant]), $product->brand);

        // The product's own size and pack count win over anything written in its name.
        return new NormalisedLine($line->text, $line->tokens, $line->markers, $product->size() ?? $line->size, $product->pack_count, $product->brand);
    }

    private function expandAbbreviations(string $text): string
    {
        foreach (config('matching.abbreviations') as $short => $long) {
            $short = Product::normalise($short);
            $text = preg_replace('/(?<![A-Z0-9])'.preg_quote($short, '/').'(?![A-Z0-9])/', $long, $text);
        }

        // "LT" is litres after a number ("2 LT"), not "light"; Size handles that before this runs.
        return preg_replace('/(\d)\s*LIGHT\b/', '$1L', $text);
    }

    /**
     * The brand named in the text; the longest name or alias wins ("COCA COLA" over "COCA").
     */
    private function findBrand(string $text): ?Brand
    {
        $this->brands ??= Brand::all();
        $best = null;
        $bestLength = 0;

        foreach ($this->brands as $brand) {
            foreach ($this->brandNames($brand) as $name) {
                if (mb_strlen($name) > $bestLength && $this->containsPhrase($text, $name)) {
                    [$best, $bestLength] = [$brand, mb_strlen($name)];
                }
            }
        }

        return $best;
    }

    private function removeBrand(string $text, ?Brand $brand): string
    {
        foreach ($brand ? $this->brandNames($brand) : [] as $name) {
            $text = preg_replace('/(?<![A-Z0-9])'.preg_quote($name, '/').'(?![A-Z0-9])/', ' ', $text);
        }

        return $text;
    }

    /**
     * @return string[]
     */
    private function brandNames(Brand $brand): array
    {
        return array_filter(array_unique(array_map(Product::normalise(...), [$brand->name, ...($brand->aliases ?? [])])));
    }

    /**
     * @return array{0: string[], 1: string}
     */
    private function extractMarkers(string $text): array
    {
        $markers = [];

        // Longest first, so "ZERO SUGAR" is taken before "ZERO".
        $all = config('matching.variant_markers');
        usort($all, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        foreach ($all as $marker) {
            if ($this->containsPhrase($text, $marker)) {
                $markers[] = $marker;
                $text = preg_replace('/(?<![A-Z0-9])'.preg_quote($marker, '/').'(?![A-Z0-9])/', ' ', $text);
            }
        }

        sort($markers);

        return [$markers, $text];
    }

    /**
     * @return string[]
     */
    private function tokens(string $text): array
    {
        return collect(preg_split('/\s+/', $text))
            ->filter(fn ($token) => $token !== '' && !is_numeric($token) && mb_strlen($token) > 1 && !in_array($token, self::STOP_WORDS, true))
            ->unique()
            ->values()
            ->all();
    }

    private function containsPhrase(string $text, string $phrase): bool
    {
        return (bool) preg_match('/(?<![A-Z0-9])'.preg_quote($phrase, '/').'(?![A-Z0-9])/', $text);
    }
}
