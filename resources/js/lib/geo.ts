import { useCallback, useState } from 'react'
import { useQuery } from '@tanstack/react-query'

const MANUAL_KEY = 'plms.country'
const DETECTED_KEY = 'plms.detected-country'
const ENDPOINT = 'https://api.country.is/'

function read(storage: Storage, key: string): string | null {
  try {
    return storage.getItem(key)
  } catch {
    return null
  }
}

function write(storage: Storage, key: string, value: string | null) {
  try {
    if (value) storage.setItem(key, value)
    else storage.removeItem(key)
  } catch {
    /* storage unavailable (private mode) */
  }
}

/**
 * Detect the visitor's country from their IP via https://api.country.is/ ({ ip, country }).
 * The lookup runs in the browser (so it reflects the visitor, not the server), is cached for the
 * session, times out after 4 s, and never blocks the page: failure simply returns null and the
 * platform default country is used.
 */
async function detectCountry(): Promise<string | null> {
  const cached = read(sessionStorage, DETECTED_KEY)
  if (cached) return cached

  const controller = new AbortController()
  const timer = window.setTimeout(() => controller.abort(), 4000)
  try {
    const response = await fetch(ENDPOINT, { signal: controller.signal, headers: { Accept: 'application/json' }, credentials: 'omit' })
    if (!response.ok) return null
    const body = (await response.json()) as { country?: string }
    const code = typeof body.country === 'string' && /^[A-Za-z]{2}$/.test(body.country) ? body.country.toUpperCase() : null
    if (code) write(sessionStorage, DETECTED_KEY, code)
    return code
  } catch {
    return null
  } finally {
    window.clearTimeout(timer)
  }
}

export interface VisitorCountry {
  /** ISO code to request pricing for (manual choice → detected → null = server default). */
  code: string | null
  source: 'manual' | 'detected' | 'default'
  detecting: boolean
  choose: () => void
}

/** The country whose page/pricing should be shown to this visitor. */
export function useVisitorCountry(): VisitorCountry {
  // Automatic only: the visitor's country is detected; no manual picker (drop any old saved choice).
  const [manual] = useState<string | null>(() => {
    write(localStorage, MANUAL_KEY, null)
    return null
  })
  const detection = useQuery({ queryKey: ['visitor-country'], queryFn: detectCountry, staleTime: Infinity, retry: false, enabled: manual === null })

  const choose = useCallback(() => undefined, [])

  if (manual) return { code: manual, source: 'manual', detecting: false, choose }
  if (detection.data) return { code: detection.data, source: 'detected', detecting: false, choose }
  return { code: null, source: 'default', detecting: detection.isLoading, choose }
}
