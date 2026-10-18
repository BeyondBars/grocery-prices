import type { ProductPayload, ProductResource, StoreResource } from './catalog'

export type ReceiptStatus = 'uploaded' | 'processing' | 'needs_review' | 'completed' | 'failed'
export type MatchStatus = 'pending' | 'auto_matched' | 'suggested' | 'confirmed' | 'new_product' | 'ignored'

/** Shape of App\Http\Resources\ReceiptItemResource. Money is in cents. */
export type ReceiptItemResource = {
  id: number
  line_number: number
  original_name: string
  sku: string | null
  quantity: number
  unit: 'each' | 'kg'
  unit_price_cents: number | null
  total_price_cents: number
  discount_cents: number
  paid_unit_price_cents: number
  match_status: MatchStatus
  match_confidence: number | null
  matched_by: 'rule' | 'ai' | 'user' | null
  product?: ProductResource | null
  suggested_product?: ProductResource | null
}

/** Shape of App\Http\Resources\ReceiptResource. */
export type ReceiptResource = {
  id: number
  status: ReceiptStatus
  store?: StoreResource | null
  store_location: string | null
  /** Local time as printed on the receipt: "2026-10-08 10:15". */
  purchased_at: string | null
  receipt_number: string | null
  subtotal_cents: number | null
  discount_cents: number
  total_cents: number | null
  currency: string
  failure_reason: string | null
  warnings: string[]
  file_mime: string | null
  has_file: boolean
  processed_at: string | null
  created_at: string
  items_count?: number
  items?: ReceiptItemResource[]
}

/** A product a receipt line could be, from GET /receipt-items/{id}/candidates. */
export type MatchCandidate = {
  product: ProductResource
  /** 0-100 */
  confidence: number
  reason: string
}

/** A new product pre-filled from the receipt line. */
export type NewProductDraft = {
  name: string
  brand: string | null
  variant: string | null
  size: string | null
  pack_count: number
  barcode: string | null
}

export type MatchPayload =
  | { product_id: number }
  | { ignore: boolean }
  | { new_product: Omit<ProductPayload, 'brand_id'> & { brand_id: number | null } }

export type ReceiptHeaderPayload = Partial<
  Pick<ReceiptResource, 'store_location' | 'purchased_at' | 'receipt_number' | 'total_cents'> & { store_id: number | null }
>

export type ReceiptItemPayload = Partial<
  Pick<ReceiptItemResource, 'original_name' | 'quantity' | 'unit' | 'unit_price_cents' | 'total_price_cents' | 'discount_cents'>
>
