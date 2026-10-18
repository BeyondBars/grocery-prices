/**
 * One colour per store, fixed by the store (not by its rank or which stores are on screen), so
 * Drakes is the same colour on every chart. Validated categorical palette (light mode), in order.
 */
const PALETTE = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#6250d6', '#e34948']
const OTHER = '#8a8984'

/** @param storeIds every store's id, so a store's slot never depends on what's being shown */
export function storeColor(storeId: number, storeIds: number[]): string {
  const slot = [...storeIds].sort((a, b) => a - b).indexOf(storeId)

  return slot >= 0 && slot < PALETTE.length ? PALETTE[slot] : OTHER
}
