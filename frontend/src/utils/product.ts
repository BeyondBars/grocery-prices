import type { ProductResource } from '@/types/catalog'

/** "Paul's Full Cream Milk · 2L" or "Eggs · 12 × 60g". */
export function productLabel(product: ProductResource): string {
  const name = [product.brand?.name, product.name].filter(Boolean).join(' ')
  const size = product.size ? (product.pack_count > 1 ? `${product.pack_count} × ${product.size}` : product.size) : null

  return size ? `${name} · ${size}` : name
}
