import clsx, { type ClassValue } from 'clsx'

export const cn = (...inputs: ClassValue[]) => clsx(inputs)

export function initials(name: string): string {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join('')
}

export function formatDate(value: string | null | undefined, opts: Intl.DateTimeFormatOptions = { day: 'numeric', month: 'short', year: 'numeric' }): string {
  if (!value) return '—'
  return new Intl.DateTimeFormat('en', opts).format(new Date(value))
}

export function formatDateTime(value: string | null | undefined): string {
  return formatDate(value, { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })
}

/** Number of fraction digits ISO-4217 defines for a currency (JPY → 0, USD → 2). */
export function currencyDigits(currency: string): number {
  try {
    return new Intl.NumberFormat('en', { style: 'currency', currency }).resolvedOptions().maximumFractionDigits ?? 2
  } catch {
    return 2
  }
}

/**
 * Format an amount in a currency. `minor` = the value is in minor units (cents),
 * e.g. plan prices; the divisor follows the currency (JPY has none).
 */
export function formatMoney(amount: number | string | null | undefined, currency = 'USD', minor = false): string {
  if (amount === null || amount === undefined || amount === '') return '—'
  const digits = currencyDigits(currency)
  const value = Number(amount) / (minor ? 10 ** digits : 1)
  try {
    return new Intl.NumberFormat('en', {
      style: 'currency',
      currency,
      minimumFractionDigits: Number.isInteger(value) ? 0 : digits,
      maximumFractionDigits: digits,
    }).format(value)
  } catch {
    return `${currency} ${value.toFixed(digits)}`
  }
}

export function relativeTime(value: string): string {
  const diff = (new Date(value).getTime() - Date.now()) / 1000
  const rtf = new Intl.RelativeTimeFormat('en', { numeric: 'auto' })
  const steps: [Intl.RelativeTimeFormatUnit, number][] = [
    ['year', 31536000],
    ['month', 2592000],
    ['week', 604800],
    ['day', 86400],
    ['hour', 3600],
    ['minute', 60],
  ]
  for (const [unit, seconds] of steps) {
    if (Math.abs(diff) >= seconds) return rtf.format(Math.round(diff / seconds), unit)
  }
  return 'just now'
}

export function daysUntil(value: string | null): number | null {
  if (!value) return null
  const ms = new Date(value).setHours(0, 0, 0, 0) - new Date().setHours(0, 0, 0, 0)
  return Math.round(ms / 86400000)
}

/** "2h 05m" style duration. */
export function formatDuration(minutes: number): string {
  const h = Math.floor(minutes / 60)
  const m = Math.round(minutes % 60)
  return h > 0 ? `${h}h ${String(m).padStart(2, '0')}m` : `${m}m`
}

export function formatBytes(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  const units = ['KB', 'MB', 'GB', 'TB']
  let value = bytes / 1024
  let i = 0
  while (value >= 1024 && i < units.length - 1) {
    value /= 1024
    i++
  }
  return `${value.toFixed(value >= 10 ? 0 : 1)} ${units[i]}`
}

/** Trigger a browser download for a blob. */
export function saveBlob(blob: Blob, filename: string): void {
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  document.body.appendChild(a)
  a.click()
  a.remove()
  URL.revokeObjectURL(url)
}
