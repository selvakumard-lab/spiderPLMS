import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react'

export type ThemePreference = 'light' | 'dark' | 'system'

interface ThemeState {
  /** What the user picked (or the platform default when they have not picked). */
  preference: ThemePreference
  /** What is actually applied right now. */
  resolved: 'light' | 'dark'
  setPreference: (value: ThemePreference) => void
  toggle: () => void
}

const KEY = 'plms.theme'
const ThemeContext = createContext<ThemeState | null>(null)

function read(): ThemePreference | null {
  try {
    const value = localStorage.getItem(KEY)
    return value === 'light' || value === 'dark' || value === 'system' ? value : null
  } catch {
    return null
  }
}

const systemPrefersDark = () => window.matchMedia('(prefers-color-scheme: dark)').matches

function apply(preference: ThemePreference): 'light' | 'dark' {
  const resolved = preference === 'system' ? (systemPrefersDark() ? 'dark' : 'light') : preference
  document.documentElement.classList.toggle('dark', resolved === 'dark')
  return resolved
}

/**
 * Light / dark / system theme. A user's own choice (localStorage) always wins;
 * until they choose, the platform default from System Settings is used.
 */
export function ThemeProvider({ platformDefault, children }: { platformDefault: ThemePreference; children: ReactNode }) {
  const [stored, setStored] = useState<ThemePreference | null>(read)
  const preference = stored ?? platformDefault
  const [resolved, setResolved] = useState<'light' | 'dark'>(() => (document.documentElement.classList.contains('dark') ? 'dark' : 'light'))

  useEffect(() => {
    setResolved(apply(preference))
    if (preference !== 'system') return
    const mql = window.matchMedia('(prefers-color-scheme: dark)')
    const onChange = () => setResolved(apply('system'))
    mql.addEventListener('change', onChange)
    return () => mql.removeEventListener('change', onChange)
  }, [preference])

  const setPreference = useCallback((value: ThemePreference) => {
    try {
      localStorage.setItem(KEY, value)
    } catch {
      /* private mode: keep in memory only */
    }
    setStored(value)
  }, [])

  const value = useMemo<ThemeState>(
    () => ({ preference, resolved, setPreference, toggle: () => setPreference(resolved === 'dark' ? 'light' : 'dark') }),
    [preference, resolved, setPreference],
  )

  return <ThemeContext.Provider value={value}>{children}</ThemeContext.Provider>
}

// eslint-disable-next-line react-refresh/only-export-components
export function useTheme(): ThemeState {
  const ctx = useContext(ThemeContext)
  if (!ctx) throw new Error('useTheme must be used inside <ThemeProvider>')
  return ctx
}
