import { createContext, useContext, useEffect, useMemo, type ReactNode } from 'react'
import { useQuery } from '@tanstack/react-query'
import { publicApi } from '@/lib/endpoints'
import type { Country, Currency, PublicConfig } from '@/types'

const FALLBACK: PublicConfig = {
  app: {
    name: 'Spider PLMS',
    tagline: 'Always know where every project stands.',
    support_email: 'selvakumar.d@spiderindia.net',
    default_theme: 'system',
    registration_open: true,
    trial_days: 14,
    maintenance_message: '',
    default_country: 'US',
    default_currency: 'USD',
  },
  countries: [],
  currencies: [],
}

interface ConfigState {
  config: PublicConfig
  loaded: boolean
  countryByCode: (code: string | null | undefined) => Country | undefined
  currencyByCode: (code: string | null | undefined) => Currency | undefined
}

const ConfigContext = createContext<ConfigState | null>(null)

/** Platform-wide settings (name, default theme, countries, currencies) set by the Super Admin. */
export function ConfigProvider({ children }: { children: ReactNode }) {
  const { data, isSuccess } = useQuery({ queryKey: ['public-config'], queryFn: publicApi.config, staleTime: 5 * 60_000, retry: 1 })
  const config = data ?? FALLBACK

  useEffect(() => {
    document.title = config.app.name
  }, [config.app.name])

  const value = useMemo<ConfigState>(
    () => ({
      config,
      loaded: isSuccess,
      countryByCode: (code) => config.countries.find((c) => c.code === code?.toUpperCase()),
      currencyByCode: (code) => config.currencies.find((c) => c.code === code?.toUpperCase()),
    }),
    [config, isSuccess],
  )

  return <ConfigContext.Provider value={value}>{children}</ConfigContext.Provider>
}

// eslint-disable-next-line react-refresh/only-export-components
export function useConfig(): ConfigState {
  const ctx = useContext(ConfigContext)
  if (!ctx) throw new Error('useConfig must be used inside <ConfigProvider>')
  return ctx
}
