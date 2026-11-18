import { useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router'
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Check, Copy, Plus, Search } from 'lucide-react'
import { useConfig } from '@/context/ConfigContext'
import { useToast } from '@/context/ToastContext'
import { errorMessage, fieldErrors } from '@/lib/api'
import { adminApi } from '@/lib/endpoints'
import { cn, formatDate } from '@/lib/utils'
import { Alert, Button, Card, CountryChip, EmptyState, Field, Input, Modal, PageHeader, Select, Skeleton } from '@/components/ui'
import { TenantStatusBadge } from './TenantStatusBadge'

export default function TenantsPage() {
  const { config, countryByCode } = useConfig()
  const navigate = useNavigate()
  const [params, setParams] = useSearchParams()
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const [country, setCountry] = useState('')
  const [page, setPage] = useState(1)
  const adding = params.get('new') === '1'

  const { data, isLoading, error } = useQuery({
    queryKey: ['admin', 'tenants', { search, status, country, page }],
    queryFn: () => adminApi.tenants({ search, status, country, page }),
    placeholderData: keepPreviousData,
  })

  return (
    <>
      <PageHeader eyebrow="Platform" title="Tenants" description="Every organisation on the platform — its plan, country, currency and usage."
        actions={<Button icon={<Plus className="size-4" />} onClick={() => setParams({ new: '1' })}>Add tenant</Button>} />

      <div className="mb-5 flex flex-col gap-3 md:flex-row">
        <div className="relative flex-1 md:max-w-sm">
          <Search className="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-ink-400" />
          <Input value={search} onChange={(e) => { setSearch(e.target.value); setPage(1) }} placeholder="Search by name or workspace…" className="pl-10" />
        </div>
        <Select value={status} onChange={(e) => { setStatus(e.target.value); setPage(1) }} className="md:w-44" aria-label="Status">
          <option value="">All statuses</option>
          {['trial', 'active', 'past_due', 'suspended', 'cancelled'].map((s) => <option key={s} value={s}>{s.replace('_', ' ').replace(/^./, (c) => c.toUpperCase())}</option>)}
        </Select>
        <Select value={country} onChange={(e) => { setCountry(e.target.value); setPage(1) }} className="md:w-56" aria-label="Country">
          <option value="">All countries</option>
          {config.countries.map((c) => <option key={c.code} value={c.code}>{c.name} ({c.code})</option>)}
        </Select>
      </div>

      {error && <Alert>{errorMessage(error)}</Alert>}

      <Card className="overflow-hidden">
        {isLoading ? <div className="space-y-2 p-4">{Array.from({ length: 5 }).map((_, i) => <Skeleton key={i} className="h-14" />)}</div> : !data?.data.length ? (
          <EmptyState icon={<Search className="size-6" />} title="No tenants found" description="Try different filters, or add a tenant." />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[900px] text-left text-[13px]">
              <thead className="border-b border-line bg-slate-50/70 text-xs font-bold tracking-wide text-ink-500 uppercase">
                <tr><th className="px-6 py-3">Organisation</th><th className="px-4 py-3">Country</th><th className="px-4 py-3">Currency</th><th className="px-4 py-3">Plan</th><th className="px-4 py-3">Status</th><th className="px-4 py-3 text-right">Users</th><th className="px-4 py-3 text-right">Projects</th><th className="px-6 py-3 text-right">Created</th></tr>
              </thead>
              <tbody className="divide-y divide-line">
                {data.data.map((t) => (
                  <tr key={t.id} onClick={() => navigate(`/app/admin/tenants/${t.id}`)} className="cursor-pointer hover:bg-slate-50/70">
                    <td className="px-6 py-3.5"><Link to={`/app/admin/tenants/${t.id}`} onClick={(e) => e.stopPropagation()} className="font-bold text-ink-900 hover:text-brand-600">{t.name}</Link><div className="text-xs text-ink-500">{t.slug}.plms.app</div></td>
                    <td className="px-4 py-3.5 whitespace-nowrap"><span className="inline-flex items-center gap-2"><CountryChip code={t.country_code} />{countryByCode(t.country_code)?.name ?? t.country_code ?? '—'}</span></td>
                    <td className="px-4 py-3.5 font-semibold">{t.currency}</td>
                    <td className="px-4 py-3.5 font-semibold">{t.plan?.name ?? '—'}</td>
                    <td className="px-4 py-3.5"><TenantStatusBadge status={t.status} /></td>
                    <td className="tabular px-4 py-3.5 text-right font-semibold">{t.users_count ?? 0}</td>
                    <td className="tabular px-4 py-3.5 text-right font-semibold">{t.projects_count ?? 0}</td>
                    <td className="px-6 py-3.5 text-right text-ink-500">{formatDate(t.created_at)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Card>

      {data && data.meta.last_page > 1 && (
        <div className="mt-5 flex items-center justify-between text-[13px] text-ink-500">
          <span>Page {data.meta.current_page} of {data.meta.last_page} · {data.meta.total} tenants</span>
          <div className="flex gap-2"><Button size="sm" variant="secondary" disabled={page <= 1} onClick={() => setPage(page - 1)}>Previous</Button><Button size="sm" variant="secondary" disabled={page >= data.meta.last_page} onClick={() => setPage(page + 1)}>Next</Button></div>
        </div>
      )}

      {adding && <AddTenantModal onClose={() => setParams({})} />}
    </>
  )
}

function AddTenantModal({ onClose }: { onClose: () => void }) {
  const { config, countryByCode } = useConfig()
  const toast = useToast()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { data: plans } = useQuery({ queryKey: ['admin', 'plans'], queryFn: adminApi.plans })
  const [form, setForm] = useState({ organization: '', slug: '', plan_id: '', country_code: config.app.default_country, currency: '', status: 'trial', trial_days: String(config.app.trial_days), name: '', email: '', password: '' })
  const [slugTouched, setSlugTouched] = useState(false)
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [created, setCreated] = useState<{ id: number; name: string; slug: string; email: string; password: string | null } | null>(null)
  const [copied, setCopied] = useState(false)

  const slugify = (v: string) => v.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 40)
  const country = countryByCode(form.country_code)
  const currency = form.currency || country?.currency_code || config.app.default_currency

  const create = useMutation({
    mutationFn: () => adminApi.createTenant({
      organization: form.organization, slug: form.slug, plan_id: Number(form.plan_id), country_code: form.country_code, currency,
      status: form.status, trial_days: form.status === 'trial' ? Number(form.trial_days) : undefined, name: form.name, email: form.email, password: form.password || undefined,
    }),
    onSuccess: (r) => {
      queryClient.invalidateQueries({ queryKey: ['admin'] })
      toast.success(`Tenant ${r.data.name} created.`)
      setCreated({ id: r.data.id, name: r.data.name, slug: r.data.slug, email: r.admin.email, password: r.temporary_password })
    },
    onError: (e) => setErrors({ ...fieldErrors(e), _: errorMessage(e) }),
  })

  if (created) {
    const text = `Workspace: ${created.slug}\nEmail: ${created.email}${created.password ? `\nTemporary password: ${created.password}` : ''}`
    return (
      <Modal open onClose={onClose} title="Tenant created" description={`${created.name} is ready to use.`}
        footer={<><Button variant="secondary" onClick={onClose}>Close</Button><Button onClick={() => navigate(`/app/admin/tenants/${created.id}`)}>Open tenant</Button></>}>
        <div className="space-y-4">
          <Alert tone="success">Share these sign-in details with the organisation's admin.</Alert>
          <pre className="rounded-xl border border-line bg-slate-50 p-4 text-[13px] leading-relaxed whitespace-pre-wrap">{text}</pre>
          {created.password && <p className="text-xs text-ink-500">This temporary password is shown only once. Ask the admin to change it after the first sign-in (My profile → Change password).</p>}
          <Button variant="secondary" size="sm" icon={copied ? <Check className="size-4" /> : <Copy className="size-4" />} onClick={async () => { await navigator.clipboard?.writeText(text); setCopied(true) }}>{copied ? 'Copied' : 'Copy details'}</Button>
        </div>
      </Modal>
    )
  }

  return (
    <Modal open onClose={onClose} title="Add tenant" description="Creates the workspace and its Organization Admin." size="lg"
      footer={<><Button variant="secondary" onClick={onClose}>Cancel</Button><Button loading={create.isPending} onClick={() => { setErrors({}); create.mutate() }}>Create tenant</Button></>}>
      <div className="grid gap-5 sm:grid-cols-2">
        {errors._ && !Object.keys(errors).some((k) => k !== '_') && <div className="sm:col-span-2"><Alert>{errors._}</Alert></div>}
        <Field label="Organisation name" htmlFor="t-org" error={errors.organization}><Input id="t-org" value={form.organization} invalid={!!errors.organization} autoFocus onChange={(e) => { setForm({ ...form, organization: e.target.value, slug: slugTouched ? form.slug : slugify(e.target.value) }) }} /></Field>
        <Field label="Workspace URL" htmlFor="t-slug" error={errors.slug}>
          <div className="flex rounded-xl focus-within:ring-4 focus-within:ring-brand-100"><Input id="t-slug" className="rounded-r-none focus:ring-0" value={form.slug} invalid={!!errors.slug} onChange={(e) => { setSlugTouched(true); setForm({ ...form, slug: slugify(e.target.value) }) }} /><span className="inline-flex items-center rounded-r-xl border border-l-0 border-line bg-slate-50 px-3 text-[13px] font-medium text-ink-500">.plms.app</span></div>
        </Field>
        <Field label="Country" htmlFor="t-country" error={errors.country_code}>
          <Select id="t-country" value={form.country_code} onChange={(e) => setForm({ ...form, country_code: e.target.value, currency: '' })}>
            {config.countries.map((c) => <option key={c.code} value={c.code}>{c.name} ({c.code})</option>)}
          </Select>
        </Field>
        <Field label="Currency" htmlFor="t-currency" error={errors.currency}>
          <Select id="t-currency" value={currency} onChange={(e) => setForm({ ...form, currency: e.target.value })}>
            {config.currencies.map((c) => <option key={c.code} value={c.code}>{c.code} — {c.name}</option>)}
          </Select>
        </Field>
        <Field label="Plan" htmlFor="t-plan" error={errors.plan_id}>
          <Select id="t-plan" value={form.plan_id} onChange={(e) => setForm({ ...form, plan_id: e.target.value })}>
            <option value="">Select a plan…</option>
            {plans?.filter((p) => p.is_active).map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
          </Select>
        </Field>
        <div className="grid grid-cols-2 gap-3">
          <Field label="Status" htmlFor="t-status"><Select id="t-status" value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value })}><option value="trial">Trial</option><option value="active">Active (paid)</option></Select></Field>
          <Field label="Trial days" htmlFor="t-trial" error={errors.trial_days}><Input id="t-trial" type="number" min={1} max={365} disabled={form.status !== 'trial'} value={form.trial_days} onChange={(e) => setForm({ ...form, trial_days: e.target.value })} /></Field>
        </div>
        <div className="border-t border-line pt-5 sm:col-span-2"><div className="text-[13px] font-bold">Organization Admin</div></div>
        <Field label="Full name" htmlFor="t-name" error={errors.name}><Input id="t-name" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} /></Field>
        <Field label="Email" htmlFor="t-email" error={errors.email}><Input id="t-email" type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} /></Field>
        <div className="sm:col-span-2"><Field label="Password" htmlFor="t-pass" error={errors.password} hint="Leave empty to generate a secure temporary password."><Input id="t-pass" type="text" autoComplete="off" value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} className={cn(form.password && 'font-mono')} /></Field></div>
      </div>
    </Modal>
  )
}
