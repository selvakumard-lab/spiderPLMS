import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus, Trash2, X } from 'lucide-react'
import { useToast } from '@/context/ToastContext'
import { errorMessage, fieldErrors } from '@/lib/api'
import { adminApi } from '@/lib/endpoints'
import { formatMoney } from '@/lib/utils'
import { Alert, Badge, Button, Card, CountryChip, Field, Input, PageHeader, Select, Skeleton } from '@/components/ui'
import { ConfirmButton, Switch, Textarea } from '@/components/ui/extra'
import type { Country, Currency, Plan } from '@/types'

interface PriceRow {
  country_code: string
  monthly: string
  yearly: string
}

export default function PlansPage() {
  const plans = useQuery({ queryKey: ['admin', 'plans'], queryFn: adminApi.plans })
  const countries = useQuery({ queryKey: ['admin', 'countries'], queryFn: adminApi.countries })
  const currencies = useQuery({ queryKey: ['admin', 'currencies'], queryFn: adminApi.currencies })
  const [adding, setAdding] = useState(false)

  const ref = { countries: (countries.data ?? []).filter((c) => c.is_active !== false), currencies: currencies.data ?? [] }

  return (
    <>
      <PageHeader
        eyebrow="Platform"
        title="Plans & pricing"
        description="Set each plan's base price in USD, then add a fixed price for any country in its own currency. Countries without a fixed price are converted from USD using the currency rate."
        actions={!adding && <Button onClick={() => setAdding(true)}><Plus className="size-4" /> Add plan</Button>}
      />
      {plans.error && <Alert>{errorMessage(plans.error)}</Alert>}
      {adding && (
        <div className="mb-5">
          <PlanForm ref_={ref} onDone={() => setAdding(false)} />
        </div>
      )}
      {plans.isLoading ? (
        <div className="grid gap-4 lg:grid-cols-3"><Skeleton className="h-[520px] rounded-2xl" /><Skeleton className="h-[520px] rounded-2xl" /><Skeleton className="h-[520px] rounded-2xl" /></div>
      ) : (
        <div className="grid gap-5 xl:grid-cols-3 md:grid-cols-2">
          {plans.data?.map((p) => <PlanForm key={p.id + JSON.stringify(p.prices) + p.price_monthly} plan={p} ref_={ref} />)}
        </div>
      )}
    </>
  )
}

function PlanForm({ plan, ref_, onDone }: { plan?: Plan; ref_: { countries: Country[]; currencies: Currency[] }; onDone?: () => void }) {
  const toast = useToast()
  const queryClient = useQueryClient()
  const uid = plan?.id ?? 'new'
  const decimalsOf = (countryCode: string): number => {
    const code = ref_.countries.find((c) => c.code === countryCode)?.currency_code
    return ref_.currencies.find((c) => c.code === code)?.decimals ?? 2
  }
  const currencyOf = (countryCode: string) => ref_.countries.find((c) => c.code === countryCode)?.currency_code ?? 'USD'

  const [form, setForm] = useState({
    name: plan?.name ?? '', description: plan?.description ?? '', monthly: plan ? String(plan.price_monthly / 100) : '', yearly: plan ? String(plan.price_yearly / 100) : '',
    max_users: plan?.max_users == null ? '' : String(plan.max_users), max_projects: plan?.max_projects == null ? '' : String(plan.max_projects), storage_gb: String(plan?.storage_gb ?? 5),
    features: (plan?.features ?? []).join('\n'), is_active: plan?.is_active ?? true,
  })
  const [rows, setRows] = useState<PriceRow[]>(
    (plan?.prices ?? []).map((r) => {
      const d = decimalsOf(r.country_code)
      return { country_code: r.country_code, monthly: String(r.price_monthly / 10 ** d), yearly: String(r.price_yearly / 10 ** d) }
    }),
  )
  const [errors, setErrors] = useState<Record<string, string>>({})

  const body = () => ({
    name: form.name, description: form.description || null, price_monthly: Math.round(Number(form.monthly || 0) * 100), price_yearly: Math.round(Number(form.yearly || 0) * 100),
    max_users: form.max_users === '' ? null : Number(form.max_users), max_projects: form.max_projects === '' ? null : Number(form.max_projects), storage_gb: Number(form.storage_gb || 1),
    features: form.features.split('\n').map((f) => f.trim()).filter(Boolean), is_active: form.is_active,
    prices: rows.map((r) => {
      const d = decimalsOf(r.country_code)
      return { country_code: r.country_code, price_monthly: Math.round(Number(r.monthly || 0) * 10 ** d), price_yearly: Math.round(Number(r.yearly || 0) * 10 ** d) }
    }),
  })

  const save = useMutation({
    mutationFn: () => (plan ? adminApi.updatePlan(plan.id, body()) : adminApi.createPlan(body())),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin'] })
      queryClient.invalidateQueries({ queryKey: ['pricing'] })
      toast.success(plan ? `${form.name} updated.` : `${form.name} created.`)
      onDone?.()
    },
    onError: (e) => { setErrors(fieldErrors(e)); toast.error(errorMessage(e)) },
  })

  const remove = useMutation({
    mutationFn: () => adminApi.deletePlan(plan!.id),
    onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['admin'] }); queryClient.invalidateQueries({ queryKey: ['pricing'] }); toast.success('Plan deleted.') },
    onError: (e) => toast.error(errorMessage(e)),
  })

  const free = ref_.countries.filter((c) => !rows.some((r) => r.country_code === c.code))
  const setRow = (i: number, patch: Partial<PriceRow>) => setRows(rows.map((r, idx) => (idx === i ? { ...r, ...patch } : r)))

  return (
    <Card className="flex flex-col p-5 sm:p-6">
      <div className="mb-4 flex items-center justify-between gap-3">
        <div className="flex items-center gap-2">
          <h3 className="text-[16px] font-bold">{plan ? plan.name : 'New plan'}</h3>
          {plan && <Badge className="bg-slate-100 text-slate-600 ring-slate-200">{plan.tenants_count ?? 0} tenants</Badge>}
        </div>
        <div className="flex items-center gap-2">
          <Switch checked={form.is_active} label="Active" onChange={(v) => setForm({ ...form, is_active: v })} />
          {onDone && <button aria-label="Cancel" onClick={onDone} className="rounded-lg p-1.5 text-ink-500 hover:bg-slate-100"><X className="size-4" /></button>}
        </div>
      </div>

      <div className="grid gap-4">
        <Field label="Name" htmlFor={`p-name-${uid}`} error={errors.name}><Input id={`p-name-${uid}`} value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} /></Field>
        <Field label="Description" htmlFor={`p-desc-${uid}`}><Input id={`p-desc-${uid}`} value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} /></Field>
        <div className="grid grid-cols-2 gap-3">
          <Field label="Base monthly (USD)" htmlFor={`p-m-${uid}`} error={errors.price_monthly}><Input id={`p-m-${uid}`} type="number" min={0} step="0.01" value={form.monthly} onChange={(e) => setForm({ ...form, monthly: e.target.value })} /></Field>
          <Field label="Base yearly (USD)" htmlFor={`p-y-${uid}`} error={errors.price_yearly}><Input id={`p-y-${uid}`} type="number" min={0} step="0.01" value={form.yearly} onChange={(e) => setForm({ ...form, yearly: e.target.value })} /></Field>
        </div>
        <div className="grid grid-cols-3 gap-3">
          <Field label="Max users" htmlFor={`p-u-${uid}`} hint="Blank = ∞"><Input id={`p-u-${uid}`} type="number" min={1} value={form.max_users} onChange={(e) => setForm({ ...form, max_users: e.target.value })} /></Field>
          <Field label="Max projects" htmlFor={`p-p-${uid}`} hint="Blank = ∞"><Input id={`p-p-${uid}`} type="number" min={1} value={form.max_projects} onChange={(e) => setForm({ ...form, max_projects: e.target.value })} /></Field>
          <Field label="Storage GB" htmlFor={`p-s-${uid}`}><Input id={`p-s-${uid}`} type="number" min={1} value={form.storage_gb} onChange={(e) => setForm({ ...form, storage_gb: e.target.value })} /></Field>
        </div>
        <Field label="Features (one per line)" htmlFor={`p-f-${uid}`}><Textarea id={`p-f-${uid}`} rows={5} value={form.features} onChange={(e) => setForm({ ...form, features: e.target.value })} /></Field>

        <div className="rounded-xl border border-line p-3.5">
          <div className="mb-2.5 flex items-center justify-between gap-2">
            <p className="text-[13px] font-bold">Country prices</p>
            <Select
              aria-label="Add country price"
              value=""
              onChange={(e) => e.target.value && setRows([...rows, { country_code: e.target.value, monthly: '', yearly: '' }])}
              className="h-9 w-auto min-w-[150px] rounded-lg py-0 text-[13px]"
              disabled={!free.length}
            >
              <option value="">+ Add country…</option>
              {free.map((c) => <option key={c.code} value={c.code}>{c.name} · {c.currency_code}</option>)}
            </Select>
          </div>
          {rows.length === 0 && <p className="text-xs text-ink-500">No fixed prices — every country is converted from the USD base price.</p>}
          <div className="grid gap-2.5">
            {rows.map((r, i) => (
              <div key={r.country_code} className="rounded-lg bg-slate-50 p-2.5">
                <div className="mb-1.5 flex items-center justify-between">
                  <span className="flex items-center gap-1.5 text-[13px] font-semibold">
                    <CountryChip code={r.country_code} /> {ref_.countries.find((c) => c.code === r.country_code)?.name ?? r.country_code}
                    <span className="text-brand-600">{currencyOf(r.country_code)}</span>
                  </span>
                  <button aria-label={`Remove ${r.country_code}`} onClick={() => setRows(rows.filter((_, idx) => idx !== i))} className="rounded-md p-1 text-ink-400 hover:bg-rose-50 hover:text-rose-600"><Trash2 className="size-3.5" /></button>
                </div>
                <div className="grid grid-cols-2 gap-2">
                  <Input aria-label={`${r.country_code} monthly`} type="number" min={0} step="any" placeholder="Monthly" value={r.monthly} onChange={(e) => setRow(i, { monthly: e.target.value })} />
                  <Input aria-label={`${r.country_code} yearly`} type="number" min={0} step="any" placeholder="Yearly" value={r.yearly} onChange={(e) => setRow(i, { yearly: e.target.value })} />
                </div>
                <p className="mt-1 text-[11px] text-ink-500">
                  {formatMoney(Math.round(Number(r.monthly || 0) * 10 ** decimalsOf(r.country_code)), currencyOf(r.country_code), true)}/mo · {formatMoney(Math.round(Number(r.yearly || 0) * 10 ** decimalsOf(r.country_code)), currencyOf(r.country_code), true)}/yr
                </p>
              </div>
            ))}
          </div>
        </div>
      </div>

      <div className="mt-5 flex gap-2">
        <Button className="flex-1" loading={save.isPending} onClick={() => { setErrors({}); save.mutate() }}>{plan ? 'Save plan' : 'Create plan'}</Button>
        {plan && (
          <ConfirmButton
            danger
            title={`Delete ${plan.name}?`}
            message="Plans used by tenants can't be deleted — deactivate them instead."
            confirmLabel="Delete"
            onConfirm={() => remove.mutateAsync()}
            trigger={(open) => <button aria-label="Delete plan" onClick={open} className="inline-flex size-10 items-center justify-center rounded-xl border border-line text-ink-500 hover:bg-rose-50 hover:text-rose-600"><Trash2 className="size-4" /></button>}
          />
        )}
      </div>
    </Card>
  )
}
