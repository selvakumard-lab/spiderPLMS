import { useMemo, useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Copy, Eraser, Search, Sparkles, Undo2, Wand2 } from 'lucide-react'
import { Alert, Badge, Button, Card, CardHeader, CountryChip, Input, Select } from '@/components/ui'
import { ConfirmButton, Switch } from '@/components/ui/extra'
import { useToast } from '@/context/ToastContext'
import { errorMessage } from '@/lib/api'
import { adminApi } from '@/lib/endpoints'
import { cn } from '@/lib/utils'
import type { Country, Currency, Plan } from '@/types'

type Cell = { monthly: string; yearly: string }
type Grid = Record<string, Cell>
type View = 'all' | 'fixed' | 'auto'

const key = (planId: number, code: string) => `${planId}|${code}`
const blank: Cell = { monthly: '', yearly: '' }
const round2 = (n: number) => String(Math.round(n * 100) / 100)

/** Our own validation messages are plain errors; anything else came from the server. */
const describe = (err: unknown) => (err instanceof Error && !('response' in err) ? err.message : errorMessage(err))

/** Mirrors the server's conversion: the USD price at the currency's rate, rounded to a tidy whole amount. */
function automaticPrice(usdMinor: number, currency: Currency | undefined): number {
  const rate = currency?.rate ?? 1
  const step = rate >= 50 ? 10 : 1
  return Math.max(step, Math.round(((usdMinor / 100) * rate) / step) * step)
}

/**
 * One grid for every plan and country. A blank price means "automatic" (converted from the USD price);
 * type a price only where you want to fix it. Everything saves together.
 */
export function CountryPricing({ plans, countries, currencies }: { plans: Plan[]; countries: Country[]; currencies: Currency[] }) {
  const toast = useToast()
  const queryClient = useQueryClient()

  const currencyOf = (code: string) => currencies.find((c) => c.code === code)
  const countryCurrency = (country: Country) => currencyOf(country.currency_code)
  const decimalsOf = (country: Country) => countryCurrency(country)?.decimals ?? 2

  const baseline = useMemo<Grid>(() => {
    const grid: Grid = {}
    for (const plan of plans) {
      for (const price of plan.prices ?? []) {
        const country = countries.find((c) => c.code === price.country_code)
        const d = country ? decimalsOf(country) : 2
        grid[key(plan.id, price.country_code)] = { monthly: round2(price.price_monthly / 10 ** d), yearly: round2(price.price_yearly / 10 ** d) }
      }
    }
    return grid
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [plans, countries, currencies])

  const [grid, setGrid] = useState<Grid>(baseline)
  const [search, setSearch] = useState('')
  const [view, setView] = useState<View>('all')
  const [currency, setCurrency] = useState('')
  const [autoYearly, setAutoYearly] = useState(true)
  const [factor, setFactor] = useState('10')

  const cell = (planId: number, code: string): Cell => grid[key(planId, code)] ?? blank
  const isFixed = (code: string) => plans.some((p) => cell(p.id, code).monthly !== '' || cell(p.id, code).yearly !== '')
  const yearlyFor = (monthly: string) => (monthly === '' || !(Number(factor) > 0) ? '' : round2(Number(monthly) * Number(factor)))

  const clean = (g: Grid) => JSON.stringify(Object.entries(g).filter(([, c]) => c.monthly !== '' || c.yearly !== '').sort(([a], [b]) => a.localeCompare(b)))
  const dirty = clean(grid) !== clean(baseline)

  const shown = countries.filter((c) => {
    const text = search.trim().toLowerCase()
    if (text && !`${c.name} ${c.code} ${c.currency_code}`.toLowerCase().includes(text)) return false
    if (currency && c.currency_code !== currency) return false
    if (view === 'fixed') return isFixed(c.code)
    if (view === 'auto') return !isFixed(c.code)
    return true
  })
  const fixedCount = countries.filter((c) => isFixed(c.code)).length
  const usedCurrencies = [...new Set(countries.map((c) => c.currency_code))].sort()

  function setCell(planId: number, code: string, field: keyof Cell, value: string) {
    setGrid((g) => {
      const current = g[key(planId, code)] ?? blank
      const next = { ...current, [field]: value }
      // Yearly follows monthly while it is blank or still the automatic value.
      if (field === 'monthly' && autoYearly && (current.yearly === '' || current.yearly === yearlyFor(current.monthly))) next.yearly = yearlyFor(value)
      return { ...g, [key(planId, code)]: next }
    })
  }

  /** Writes the automatic (converted) price into every blank cell of the countries shown, so it can be fine-tuned. */
  function fillShown() {
    setGrid((g) => {
      const next = { ...g }
      for (const country of shown) {
        const rate = countryCurrency(country)
        for (const plan of plans) {
          const current = next[key(plan.id, country.code)] ?? blank
          next[key(plan.id, country.code)] = {
            monthly: current.monthly || round2(automaticPrice(plan.price_monthly, rate)),
            yearly: current.yearly || round2(automaticPrice(plan.price_yearly, rate)),
          }
        }
      }
      return next
    })
  }

  function clearCountries(codes: string[]) {
    setGrid((g) => {
      const next = { ...g }
      for (const code of codes) for (const plan of plans) delete next[key(plan.id, code)]
      return next
    })
  }

  /** Copies one country's prices to every other country that uses the same currency (for example the euro countries). */
  function copyToSameCurrency(source: Country) {
    const targets = countries.filter((c) => c.code !== source.code && c.currency_code === source.currency_code)
    setGrid((g) => {
      const next = { ...g }
      for (const target of targets) for (const plan of plans) next[key(plan.id, target.code)] = { ...(g[key(plan.id, source.code)] ?? blank) }
      return next
    })
    toast.success(`Copied to ${targets.length} other ${source.currency_code} ${targets.length === 1 ? 'country' : 'countries'}.`)
  }

  const save = useMutation({
    mutationFn: () => {
      const rows: { plan_id: number; country_code: string; price_monthly: number; price_yearly: number }[] = []
      for (const country of countries) {
        const d = decimalsOf(country)
        for (const plan of plans) {
          const c = cell(plan.id, country.code)
          if (c.monthly === '' && c.yearly === '') continue
          if (c.monthly === '') throw new Error(`${country.name}, ${plan.name}: enter the monthly price too, or clear the yearly one.`)
          const monthly = Number(c.monthly)
          const yearly = c.yearly === '' ? Number(yearlyFor(c.monthly)) : Number(c.yearly)
          if (!(monthly >= 0) || !(yearly >= 0) || Number.isNaN(yearly)) throw new Error(`${country.name}, ${plan.name}: enter a valid price.`)
          rows.push({ plan_id: plan.id, country_code: country.code, price_monthly: Math.round(monthly * 10 ** d), price_yearly: Math.round(yearly * 10 ** d) })
        }
      }
      return adminApi.savePrices(rows)
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin'] })
      queryClient.invalidateQueries({ queryKey: ['pricing'] })
      toast.success('Country prices saved.')
    },
    onError: (err) => toast.error(describe(err)),
  })

  return (
    <Card id="country-pricing" className="mt-6 scroll-mt-6">
      <CardHeader
        title="Country pricing"
        subtitle="One grid for all plans. Leave a price blank to use the automatic price (converted from USD). Type a price only where you want to fix it."
        action={<Badge className="bg-brand-50 text-brand-700 ring-brand-200">{fixedCount} of {countries.length} countries fixed</Badge>}
      />

      <div className="flex flex-wrap items-center gap-2.5 px-5 pt-4 sm:px-6">
        <div className="relative min-w-[200px] flex-1 sm:max-w-xs">
          <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-400" />
          <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search country or currency" aria-label="Search countries" className="h-10 pl-9" />
        </div>
        <Select value={view} onChange={(e) => setView(e.target.value as View)} aria-label="Show" className="h-10 w-auto min-w-[150px] py-0 text-[13px]">
          <option value="all">All countries</option>
          <option value="fixed">With a fixed price</option>
          <option value="auto">Automatic only</option>
        </Select>
        <Select value={currency} onChange={(e) => setCurrency(e.target.value)} aria-label="Currency" className="h-10 w-auto min-w-[130px] py-0 text-[13px]">
          <option value="">Any currency</option>
          {usedCurrencies.map((code) => <option key={code} value={code}>{code}</option>)}
        </Select>
        <div className="ml-auto flex flex-wrap items-center gap-2">
          <Button size="sm" variant="secondary" icon={<Wand2 className="size-3.5" />} onClick={fillShown} title="Fills every blank price of the countries shown with the automatic price, so you can adjust it">
            Fill blanks with automatic
          </Button>
          <ConfirmButton
            danger
            title="Clear the shown countries?"
            message={`The fixed prices of the ${shown.length} countries shown will be removed. They will use the automatic price after you save.`}
            confirmLabel="Clear prices"
            onConfirm={() => clearCountries(shown.map((c) => c.code))}
            trigger={(open) => <Button size="sm" variant="secondary" icon={<Eraser className="size-3.5" />} onClick={open} disabled={!shown.some((c) => isFixed(c.code))}>Clear shown</Button>}
          />
        </div>
      </div>

      <div className="flex flex-wrap items-center gap-x-3 gap-y-2 px-5 pt-3 text-[12.5px] text-ink-500 sm:px-6">
        <label className="flex items-center gap-2 font-semibold text-ink-700">
          <Switch checked={autoYearly} onChange={setAutoYearly} label="Fill the yearly price automatically" />
          Fill yearly price from monthly
        </label>
        <span className="flex items-center gap-1.5">× <Input type="number" min={1} step="any" value={factor} onChange={(e) => setFactor(e.target.value)} disabled={!autoYearly} aria-label="Yearly multiplier" className="h-8 w-16 px-2 text-[13px]" /> months</span>
        <span className="hidden sm:inline">· Type a monthly price and the yearly one follows. You can still edit it.</span>
      </div>

      <div className="scrollbar-thin mt-4 overflow-x-auto">
        <table className="w-full min-w-[760px] border-separate border-spacing-0 text-[13px]">
          <thead>
            <tr>
              <th rowSpan={2} className="sticky left-0 z-10 border-y border-line bg-canvas px-5 py-2 text-left text-[11px] font-bold tracking-wider text-ink-500 uppercase sm:px-6">Country</th>
              {plans.map((p) => (
                <th key={p.id} colSpan={2} className="border-y border-l border-line bg-canvas px-3 pt-2 text-center text-[13px] font-bold text-ink-900">
                  {p.name}
                  <span className="ml-1.5 text-[11px] font-medium text-ink-400">base ${p.price_monthly / 100} / ${p.price_yearly / 100}</span>
                </th>
              ))}
              <th rowSpan={2} className="w-20 border-y border-l border-line bg-canvas" aria-label="Actions" />
            </tr>
            <tr>
              {plans.flatMap((p) => ['Monthly', 'Yearly'].map((label, i) => (
                <th key={`${p.id}${label}`} className={cn('border-b border-line bg-canvas px-3 pb-2 text-left text-[10.5px] font-bold tracking-wider text-ink-400 uppercase', i === 0 && 'border-l')}>{label}</th>
              )))}
            </tr>
          </thead>
          <tbody>
            {shown.length === 0 && <tr><td colSpan={plans.length * 2 + 2} className="px-6 py-10 text-center text-ink-500">No country matches. Change the search or filters.</td></tr>}
            {shown.map((country) => {
              const rate = countryCurrency(country)
              const fixed = isFixed(country.code)
              const sameCurrency = countries.filter((c) => c.code !== country.code && c.currency_code === country.currency_code).length
              return (
                <tr key={country.code} className="group hover:bg-canvas/70">
                  <td className="sticky left-0 z-10 border-b border-line bg-surface px-5 py-2 group-hover:bg-canvas sm:px-6">
                    <div className="flex items-center gap-2.5">
                      <CountryChip code={country.code} />
                      <div className="min-w-0">
                        <div className="truncate font-semibold text-ink-900">{country.name}</div>
                        <div className="flex items-center gap-1.5 text-[11.5px] text-ink-500">
                          <span className="font-semibold text-brand-600">{country.currency_code}</span>
                          <span className={cn('rounded-full px-1.5 py-px text-[10px] font-bold', fixed ? 'bg-brand-50 text-brand-700' : 'bg-slate-100 text-slate-500')}>{fixed ? 'Fixed' : 'Auto'}</span>
                        </div>
                      </div>
                    </div>
                  </td>
                  {plans.flatMap((plan) => (['monthly', 'yearly'] as const).map((field, i) => {
                    const value = cell(plan.id, country.code)[field]
                    const auto = automaticPrice(field === 'monthly' ? plan.price_monthly : plan.price_yearly, rate)
                    return (
                      <td key={`${plan.id}${field}`} className={cn('border-b border-line px-2 py-1.5', i === 0 && 'border-l')}>
                        <Input
                          type="number"
                          min={0}
                          step="any"
                          inputMode="decimal"
                          value={value}
                          placeholder={String(auto)}
                          aria-label={`${country.name} ${plan.name} ${field} price in ${country.currency_code}`}
                          onChange={(e) => setCell(plan.id, country.code, field, e.target.value)}
                          className={cn('h-9 min-w-[88px] px-2.5 text-[13px] tabular-nums', value !== '' ? 'border-brand-300 bg-brand-50/40 font-semibold' : 'placeholder:text-ink-400')}
                        />
                      </td>
                    )
                  }))}
                  <td className="border-b border-l border-line px-2 py-1.5">
                    <div className="flex items-center justify-end gap-0.5">
                      {sameCurrency > 0 && fixed && (
                        <button onClick={() => copyToSameCurrency(country)} title={`Copy to the other ${sameCurrency} ${country.currency_code} countries`} aria-label={`Copy ${country.name} prices to the other ${country.currency_code} countries`} className="rounded-md p-1.5 text-ink-400 transition hover:bg-slate-100 hover:text-ink-900"><Copy className="size-3.5" /></button>
                      )}
                      {fixed && (
                        <button onClick={() => clearCountries([country.code])} title="Use the automatic price" aria-label={`Clear ${country.name} prices`} className="rounded-md p-1.5 text-ink-400 transition hover:bg-rose-50 hover:text-rose-600"><Eraser className="size-3.5" /></button>
                      )}
                    </div>
                  </td>
                </tr>
              )
            })}
          </tbody>
        </table>
      </div>

      <div className="px-5 py-3 text-[12px] text-ink-500 sm:px-6">
        <Sparkles className="mr-1.5 inline size-3.5 text-brand-600" />
        The faint number in each box is the automatic price customers see when the box is blank.
      </div>

      <div className={cn('sticky bottom-3 z-20 mx-3 mb-3 flex flex-wrap items-center justify-between gap-3 rounded-2xl border px-4 py-3 shadow-pop transition-all duration-200 sm:mx-4', dirty ? 'border-brand-200 bg-surface opacity-100' : 'pointer-events-none translate-y-2 border-transparent opacity-0')} aria-hidden={!dirty}>
        <div className="flex items-center gap-2 text-[13px] font-semibold text-ink-900"><span className="size-2 animate-pulse rounded-full bg-brand-600" /> You have unsaved price changes</div>
        <div className="flex gap-2">
          <Button size="sm" variant="secondary" icon={<Undo2 className="size-3.5" />} onClick={() => setGrid(baseline)} tabIndex={dirty ? 0 : -1}>Discard</Button>
          <Button size="sm" loading={save.isPending} onClick={() => save.mutate()} tabIndex={dirty ? 0 : -1}>Save prices</Button>
        </div>
      </div>
      {save.error && <div className="px-5 pb-4 sm:px-6"><Alert>{describe(save.error)}</Alert></div>}
    </Card>
  )
}
