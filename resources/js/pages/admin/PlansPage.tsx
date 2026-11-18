import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus, Trash2, X } from 'lucide-react'
import { useToast } from '@/context/ToastContext'
import { errorMessage, fieldErrors } from '@/lib/api'
import { adminApi } from '@/lib/endpoints'
import { Alert, Badge, Button, Card, Field, Input, PageHeader, Skeleton } from '@/components/ui'
import { ConfirmButton, Switch, Textarea } from '@/components/ui/extra'
import type { Plan } from '@/types'
import { CountryPricing } from './CountryPricing'

export default function PlansPage() {
  const plans = useQuery({ queryKey: ['admin', 'plans'], queryFn: adminApi.plans })
  const countries = useQuery({ queryKey: ['admin', 'countries'], queryFn: adminApi.countries })
  const currencies = useQuery({ queryKey: ['admin', 'currencies'], queryFn: adminApi.currencies })
  const [adding, setAdding] = useState(false)

  const activeCountries = (countries.data ?? []).filter((c) => c.is_active !== false)

  return (
    <>
      <PageHeader
        eyebrow="Platform"
        title="Plans & pricing"
        description="Set each plan's base price in USD and what it includes. Prices for other countries are converted automatically, and you can fix any of them in the Country pricing grid below."
        actions={!adding && <Button onClick={() => setAdding(true)}><Plus className="size-4" /> Add plan</Button>}
      />
      {plans.error && <Alert>{errorMessage(plans.error)}</Alert>}
      {adding && (
        <div className="mb-5">
          <PlanForm onDone={() => setAdding(false)} />
        </div>
      )}
      {plans.isLoading ? (
        <div className="grid gap-4 lg:grid-cols-3"><Skeleton className="h-[520px] rounded-2xl" /><Skeleton className="h-[520px] rounded-2xl" /><Skeleton className="h-[520px] rounded-2xl" /></div>
      ) : (
        <div className="grid gap-5 xl:grid-cols-3 md:grid-cols-2">
          {plans.data?.map((p) => <PlanForm key={p.id + p.price_monthly + p.price_yearly} plan={p} />)}
        </div>
      )}
      {plans.data && !countries.isLoading && !currencies.isLoading && (
        <CountryPricing key={JSON.stringify(plans.data.map((p) => p.prices))} plans={plans.data} countries={activeCountries} currencies={currencies.data ?? []} />
      )}
    </>
  )
}

function PlanForm({ plan, onDone }: { plan?: Plan; onDone?: () => void }) {
  const toast = useToast()
  const queryClient = useQueryClient()
  const uid = plan?.id ?? 'new'
  const [form, setForm] = useState({
    name: plan?.name ?? '', description: plan?.description ?? '', monthly: plan ? String(plan.price_monthly / 100) : '', yearly: plan ? String(plan.price_yearly / 100) : '',
    max_users: plan?.max_users == null ? '' : String(plan.max_users), max_projects: plan?.max_projects == null ? '' : String(plan.max_projects), storage_gb: String(plan?.storage_gb ?? 5),
    features: (plan?.features ?? []).join('\n'), is_active: plan?.is_active ?? true, whatsapp_chat: plan?.whatsapp_chat ?? false,
  })
  const [errors, setErrors] = useState<Record<string, string>>({})

  const body = () => ({
    name: form.name, description: form.description || null, price_monthly: Math.round(Number(form.monthly || 0) * 100), price_yearly: Math.round(Number(form.yearly || 0) * 100),
    max_users: form.max_users === '' ? null : Number(form.max_users), max_projects: form.max_projects === '' ? null : Number(form.max_projects), storage_gb: Number(form.storage_gb || 1),
    features: form.features.split('\n').map((f) => f.trim()).filter(Boolean), is_active: form.is_active, whatsapp_chat: form.whatsapp_chat,
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

  return (
    <Card className="flex flex-col p-5 sm:p-6">
      <div className="mb-4 flex items-center justify-between gap-3">
        <div className="flex items-center gap-2">
          <h3 className="text-[16px] font-bold">{plan ? plan.name : 'New plan'}</h3>
          {plan && <Badge className="bg-slate-100 text-slate-600 ring-slate-200">{plan.tenants_count ?? 0} tenants</Badge>}
        </div>
        <div className="flex items-center gap-2">
          <label className="flex items-center gap-2.5 text-[12.5px] font-semibold text-ink-700" title="Active plans can be chosen by new customers. Turn off to hide the plan from the pricing page and sign-up.">
            {form.is_active ? 'Active' : 'Hidden'}
            <Switch checked={form.is_active} label="Plan active" onChange={(v) => setForm({ ...form, is_active: v })} />
          </label>
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
        <div className="flex items-start justify-between gap-4 rounded-xl border border-line p-3.5">
          <div className="min-w-0">
            <p className="text-[13px] font-bold text-ink-900">WhatsApp chat</p>
            <p className="mt-0.5 text-[12px] leading-relaxed text-ink-500">Workspaces on this plan can link their own WhatsApp number by scanning a QR code and chat with clients. Off = the WhatsApp page shows an upgrade message.</p>
          </div>
          <Switch checked={form.whatsapp_chat} onChange={(v) => setForm({ ...form, whatsapp_chat: v })} label="WhatsApp chat included in this plan" />
        </div>
        <Field label="Features (one per line)" htmlFor={`p-f-${uid}`}><Textarea id={`p-f-${uid}`} rows={5} value={form.features} onChange={(e) => setForm({ ...form, features: e.target.value })} /></Field>

        <p className="rounded-xl bg-canvas px-3.5 py-3 text-[12.5px] text-ink-500">
          {plan ? `${plan.prices?.length ?? 0} countries have a fixed price for this plan. ` : 'After you create the plan, set its prices for other countries in the Country pricing grid. '}
          <a href="#country-pricing" className="font-semibold text-brand-700 hover:underline">Open the grid</a>
        </p>
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
