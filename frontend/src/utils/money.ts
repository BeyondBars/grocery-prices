/** 450 => "$4.50", -100 => "-$1.00", null => "–". */
export function formatCents(cents: number | null | undefined): string {
  if (cents === null || cents === undefined) return '–'

  return `${cents < 0 ? '-' : ''}$${(Math.abs(cents) / 100).toFixed(2)}`
}

/** "4.50" or "$4.50" => 450. Returns null for anything that isn't an amount. */
export function toCents(value: string | number | null | undefined): number | null {
  const amount = Number(String(value ?? '').replace(/[$,\s]/g, ''))

  return value === '' || value === null || Number.isNaN(amount) ? null : Math.round(amount * 100)
}
