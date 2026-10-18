import type { ProductResource } from './catalog'
import type { PriceUnit, StoreRef } from './pricing'

/** Shape of App\Http\Resources\ShoppingListItemResource. */
export type ShoppingListItemResource = {
  id: number
  label: string
  free_text: string | null
  quantity: number
  is_checked: boolean
  position: number
  product?: ProductResource | null
}

/** Shape of App\Http\Resources\ShoppingListResource. */
export type ShoppingListResource = {
  id: number
  name: string
  items_count?: number
  unchecked_count?: number
  items?: ShoppingListItemResource[]
  updated_at: string
}

export type StoreTotal = {
  store: StoreRef
  total_cents: number
  priced_count: number
  missing_count: number
  missing: string[]
}

/** GET /shopping-lists/{id}/comparison */
export type ListComparison = {
  items: {
    id: number
    label: string
    quantity: number
    unit: PriceUnit | null
    prices: { store_id: number; store: StoreRef; price_cents: number; line_total_cents: number }[]
    cheapest_store_id: number | null
  }[]
  /** Stores with every item first, cheapest first. */
  stores: StoreTotal[]
  /** Cheapest store that has a price for everything. */
  cheapest: StoreTotal | null
  saving: { cents: number; compared_with: StoreRef } | null
  split: {
    stores: { store: StoreRef; items: string[]; total_cents: number }[]
    total_cents: number
    saving_cents: number | null
  } | null
  /** Items with no price anywhere yet. */
  unpriced: string[]
}

export type ShoppingListItemPayload = Partial<{ product_id: number | null; free_text: string | null; quantity: number; is_checked: boolean }>
