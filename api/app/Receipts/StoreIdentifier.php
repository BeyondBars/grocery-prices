<?php

namespace App\Receipts;

use App\Models\Store;
use App\Receipts\Parsing\ParsedReceipt;
use Illuminate\Support\Str;

/**
 * Works out which store a receipt is from: by ABN when the store has one, otherwise by the
 * store's name or aliases printed in the header (the longest match wins, so "COLES EXPRESS"
 * beats "COLES").
 */
class StoreIdentifier
{
    public function identify(ParsedReceipt $parsed, string $text): ?Store
    {
        if ($parsed->abn && ($store = Store::firstWhere('abn', $parsed->abn))) {
            return $store;
        }

        $lines = array_values(array_filter(preg_split('/\R/', Str::upper($text)), fn ($line) => trim($line) !== ''));
        $header = implode("\n", array_slice($lines, 0, 10));

        return $this->bestMatch($header) ?? $this->bestMatch(implode("\n", $lines));
    }

    private function bestMatch(string $text): ?Store
    {
        $best = null;
        $bestLength = 0;

        foreach (Store::all() as $store) {
            foreach ([Str::upper($store->name), ...($store->aliases ?? [])] as $alias) {
                $found = preg_match('/(?<![A-Z0-9])'.preg_quote($alias, '/').'(?![A-Z0-9])/', $text);

                if ($found && mb_strlen($alias) > $bestLength) {
                    [$best, $bestLength] = [$store, mb_strlen($alias)];
                }
            }
        }

        return $best;
    }
}
