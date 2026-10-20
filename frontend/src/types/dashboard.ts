import type { PriceUnit, StoreRef } from './pricing'
import type { ReceiptStatus } from './receipt'

type ProductRef = { id: number; name: string; size: string | null }

export type PriceChange = {
  product: ProductRef
  store: StoreRef
  unit: PriceUnit
  from_cents: number
  to_cents: number
  change_cents: number
  observed_on: string
}

/** GET /dashboard */
export type DashboardData = {
  counts: { receipts: number; needs_review: number; products: number; stores: number }
  spent_this_month_cents: number
  potential_savings_this_month_cents: number
  recent_changes: PriceChange[]
  cheaper: PriceChange[]
  dearer: PriceChange[]
  savings_opportunities: {
    product: ProductRef
    unit: PriceUnit
    paid_cents: number
    paid_at: StoreRef
    cheapest_cents: number
    cheapest_at: StoreRef
    saving_cents: number
  }[]
  recent_receipts: { id: number; store: string | null; purchased_at: string | null; total_cents: number | null; status: ReceiptStatus; created_at: string }[]
}
