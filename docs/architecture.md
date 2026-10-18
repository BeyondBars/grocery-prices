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

`POST /receipts` (multipart, up to 10 files of 10 MB) stores each file on the private
`receipts.disk` under `receipts/{user}/`, then queues `App\Jobs\ProcessReceipt`:

1. **Upload** (`App\Receipts\ReceiptUploader`): photos are turned upright, shrunk to 2000px and
   re-encoded as JPEG, which also strips EXIF/GPS data. The same file twice is reported as a duplicate.
2. **Read** (`ReceiptReader`): PDFs with a text layer use `pdftotext` (free); everything else goes
   to the `OcrEngine` (`textract` = AWS Textract AnalyzeExpense, `none` = photos fail with a clear
   message). Card numbers are masked. The OCR result is saved before parsing, so "Read again"
   never pays for OCR twice.
3. **Parse** (`TextReceiptParser`): ABN, date, receipt number, totals and items, including
   quantity lines, weighed items and discount lines. Warns when items don't add up to the total.
4. **Store** (`StoreIdentifier`): by ABN, else the longest store alias in the header.
5. Items are saved as `pending` and the receipt goes to **Needs review**. (Phase 4 adds matching.)

Known problems (unreadable file, no OCR configured) fail at once with a message for the user;
other errors retry 3 times with backoff. OCR calls are rate limited across workers
(`RECEIPTS_OCR_PER_MINUTE`). Deleted receipts are pruned with their files after 30 days.

### Turning on photo OCR

1. In AWS, allow `textract:AnalyzeExpense` for the app's IAM role (or an IAM user).
2. In `api/.env`: `RECEIPTS_OCR_ENGINE=textract`, `AWS_DEFAULT_REGION=ap-southeast-2`, and the
   `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` pair unless running on AWS with an instance role.
3. `QUEUE_CONNECTION=database` and a running worker (`docker compose up -d queue`).

## Matching (Phase 4)

`App\Matching\LineNormaliser` turns a line or product into what matching compares: abbreviations
expanded (`config/matching.php`: FC → FULL CREAM, LITE → LIGHT…), size standardised ("2 LTR" → 2L),
pack count ("12PK"), brand (from `brands.aliases`), variant markers (FULL CREAM, LIGHT, ZERO SUGAR…)
and the remaining words.

`RuleBasedProductMatcher` (behind the `ProductMatcher` interface, so an AI matcher can wrap it later):

1. **Exact:** barcode/SKU, or a wording a user confirmed before: 100 at the same store, 97 elsewhere.
   Merged products are followed to the one still in use.
2. **Rules out:** a different size or pack count scores 0. A different variant or brand caps at 40.
3. **Scores:** word similarity (Dice, tolerating 1-letter OCR slips) up to 60, same brand 25,
   same size 15. A size on only one side caps at 90, so it can't auto-match.
4. **Decides** (`ReceiptItemMatcher`): ≥ 95 with a clear winner (runner-up 5+ points behind) →
   auto-matched; ≥ 70 → suggested; otherwise the user chooses. Thresholds: `MATCH_AUTO`, `MATCH_SUGGEST`.

On the review screen the user confirms a suggestion, picks another product, creates a new one
(pre-filled from the line; refused if the same brand/name/variant/size already exists) or marks
the line "not a product". Each confirmation is saved as a `product_aliases` row, and the user's
other receipts waiting on the same wording at that store match straight away. Automatic matching
never overrides a user's decision. A receipt is **Completed** once it has a store, a date and no
pending or suggested lines; only matched lines count towards price history.

Catalog managers merge duplicates (`POST /products/{id}/merge`): receipt lines, prices and
wordings move to the kept product; the duplicate stays with `merged_into_id`.

## Phases

1. Design ✔
2. Catalog, receipts, receipt items, price history: schema, API, Stores and Products pages ✔
3. Receipt upload + OCR + queue worker ✔
4. Product matching + review screen ✔
5. Price comparison + history chart
6. Shopping lists (per-store totals, split shop)
7. Dashboard
8. AI-assisted matching
9. Online supermarket prices and promotions
