export type StoreRef = { id: number; name: string }
export type PriceUnit = 'each' | 'kg'

export type PricePoint = {
  store: StoreRef
  price_cents: number
  /** "2026-10-08" */
  observed_on: string
}

/** A store's latest price for a product. */
export type StorePrice = PricePoint & {
  regular_price_cents: number | null
  is_promotion: boolean
  previous_price_cents: number | null
  /** Older than the "out of date" threshold: it may have changed since. */
  is_stale: boolean
}

/** GET /products/{id}/prices */
export type PriceSummary = {
  unit: PriceUnit | null
  /** Cheapest first. */
  stores: StorePrice[]
  cheapest: StorePrice | null
  saving: { cents: number; compared_with: StoreRef } | null
  stats: {
    lowest: PricePoint
    highest: PricePoint
    /** Average of each store's latest price. */
    average_cents: number
    average_recorded_cents: number
    count: number
  } | null
}

/** GET /products/{id}/price-history: one series per store. */
export type PriceHistorySeries = {
  store: StoreRef
  unit: PriceUnit
  points: { date: string; price_cents: number; is_promotion: boolean }[]
}

/** On the product list: the cheapest latest price the user has seen. */
export type CheapestPrice = {
  price_cents: number
  unit: PriceUnit
  store: StoreRef
  stores_count: number
}
