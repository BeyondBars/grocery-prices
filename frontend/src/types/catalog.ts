/** What the signed in user may do with a catalog entry (from the API policies). */
export type CatalogPermissions = { update: boolean; delete: boolean; merge?: boolean }

/** Shape of App\Http\Resources\StoreResource. */
export type StoreResource = {
  id: number
  name: string
  slug: string
  abn: string | null
  aliases: string[]
  can: CatalogPermissions
}

/** Shape of App\Http\Resources\BrandResource. */
export type BrandResource = {
  id: number
  name: string
  aliases: string[]
}

/** Shape of App\Http\Resources\CategoryResource. */
export type CategoryResource = {
  id: number
  parent_id: number | null
  name: string
  slug: string
}

/** Shape of App\Http\Resources\ProductResource. */
export type ProductResource = {
  id: number
  name: string
  variant: string | null
  /** Display size, e.g. "2L" or "700g". */
  size: string | null
  size_amount: number | null
  size_unit: 'g' | 'ml' | 'each' | null
  pack_count: number
  barcode: string | null
  merged_into_id: number | null
  brand?: BrandResource | null
  category?: CategoryResource | null
  can: CatalogPermissions
}

export type StorePayload = { name: string; abn: string | null; aliases: string[] }

export type ProductPayload = {
  name: string
  brand_id: number | null
  category_id: number | null
  variant: string | null
  size: string | null
  pack_count: number
  barcode: string | null
}
