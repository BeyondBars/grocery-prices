# Grocery Prices: architecture

Upload grocery receipts, match each line to a canonical product, keep a price history per store,
and show where each product (or a whole shopping list) is cheapest. Australian supermarkets, AUD.

Built on the `htb-backend-base` starter (Laravel 13 API in `api/`, Vue 3 SPA in `frontend/`). The
starter is kept as the `upstream` remote so auth fixes can be merged in later.

## Core rules

1. **Receipt items are the source of truth.** They keep what was printed (name, quantity, prices,
   discount) and are never rewritten from the product.
2. **Price history is derived.** `price_observations` rows for receipts are rebuilt from receipt items
   by `ReceiptItem::syncPriceObservation()` whenever a match, a line or the receipt header changes, so
   a correction always fixes history. Deleting a receipt removes its prices; restoring brings them back.
3. **Shared catalog, private receipts.** Stores, brands, categories and products are shared by all
   users (anyone can add; the creator or a `manage catalog` user can edit; only `manage catalog` can
   delete). Receipts and shopping lists belong to one user.
4. **No silent duplicates.** A product is only created when a user confirms it. Duplicates are merged
   (`merged_into_id`), never deleted.

## Tables

| Table | Purpose |
|---|---|
| `stores` | Chains (Woolworths, Coles…). `abn` and `aliases` identify the store on a receipt. |
| `brands` | `aliases` hold how the brand is printed ("PAULS" for Paul's). |
| `categories` | Two-level tree via `parent_id`. |
| `products` | Canonical product. Size in base units (`size_amount` + `size_unit` g/ml/each), `pack_count`, `variant`, `barcode`, `match_key`. |
| `product_aliases` | Receipt wordings known to mean a product, per store. Confirmations land here so a wording is only reviewed once. |
| `receipts` | Header, status, private file, saved OCR text and parse output. Soft deleted. |
| `receipt_items` | Printed line + `product_id`, `match_status`, `match_confidence`, who matched it and when. |
| `price_observations` | Price history. `source` = receipt / online / promotion / manual. Paid price after discount, regular price, unit (each/kg), date. |

Money is stored as integer cents. Quantities are decimals (weighed items).

Statuses — receipt: `uploaded → processing → needs_review | completed`, or `failed`.
Item: `pending, auto_matched, suggested, confirmed, new_product, ignored`. Only `auto_matched`,
`confirmed` and `new_product` items with a store and date count towards price history.

## Receipt pipeline (Phase 3)

One queued job, `ProcessReceipt`, calls small classes in order: OCR (`OcrEngine` interface; text
PDFs via `pdftotext`, photos via AWS Textract AnalyzeExpense) → `ReceiptParser` → store
identification (ABN, then aliases) → `ProductMatcher` → price observations. OCR text is saved
before parsing so a re-run never pays for OCR twice. Retries with backoff; `failed()` records why.

## Matching (Phase 4)

Normalise the line and parse brand/size/variant (`App\Support\Size` parses "2 LT", "700G", "1.25L").
Exact barcode/SKU or confirmed alias → 100. Otherwise score candidates: a different size or pack
count disqualifies; a variant conflict (LIGHT vs FULL CREAM, ZERO vs regular) caps the score;
brand, token similarity and category add to it. ≥ 95 auto-match, 70–94 suggest, < 70 review /
new product (thresholds in `config/matching.php`). Never auto-match when the top two are close.
AI (Phase 8) only looks at the uncertain band, one line plus its candidates, never whole receipts.

## Phases

1. Design ✔
2. Catalog, receipts, receipt items, price history: schema, API, Stores and Products pages ✔
3. Receipt upload + OCR + queue worker
4. Product matching + review screen
5. Price comparison + history chart
6. Shopping lists (per-store totals, split shop)
7. Dashboard
8. AI-assisted matching
9. Online supermarket prices and promotions
