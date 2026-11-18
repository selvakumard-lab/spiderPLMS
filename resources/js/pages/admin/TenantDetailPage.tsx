import { useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft, ExternalLink, Pause, Play, Timer, Trash2 } from 'lucide-react'
import { useAuth } from '@/context/AuthContext'
import { useConfig } from '@/context/ConfigContext'
import { useToast } from '@/context/ToastContext'
import { errorMessage, fieldErrors } from '@/lib/api'
import { adminApi } from '@/lib/endpoints'
import { ROLE_LABELS } from '@/lib/constants'
import { formatDate, formatDateTime, relativeTime } from '@/lib/utils'
import { Alert, Avatar, Badge, Button, Card, CardHeader, Field, Input, Select, Skeleton } from '@/components/ui'
import { ConfirmButton, StatTile, Textarea } from '@/components/ui/extra'
import { TenantStatusBadge } from './TenantStatusBadge'
import type { Tenant } from '@/types'

export default function TenantDetailPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const toast = useToast()
  const queryClient = useQueryClient()
  const { enterTenant } = useAuth()
  const { data, isLoading, error } = useQuery({ queryKey: ['admin', 'tenant', id], queryFn: () => adminApi.tenant(id!) })
  const { data: plans } = useQuery({ queryKey: ['admin', 'plans'], queryFn: adminApi.plans })

  const update = useMutation({
    mutationFn: (body: Record<string, unknown>) => adminApi.updateTenant(Number(id), body),
    onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['admin'] }); toast.success('Tenant updated.') },
    onError: (e) => toast.error(errorMessage(e)),
  })
  const remove = useMutation({ mutationFn: () => adminApi.deleteTenant(Number(id)), onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['admin'] }); navigate('/app/admin/tenants') } })

  if (isLoading) return <div className="space-y-6"><Skeleton className="h-12 w-80" /><Skeleton className="h-64 rounded-2xl" /></div>
  if (error || !data) return <Alert>{errorMessage(error, 'Tenant not found.')}</Alert>

  const t = data.data
  const suspended = t.status === 'suspended' || t.status === 'cancelled'

  return (
    <>
      <Link to="/app/admin/tenants" className="mb-4 inline-flex items-center gap-1.5 text-[13px] font-semibold text-ink-500 hover:text-ink-900"><ArrowLeft className="size-4" /> All tenants</Link>

      <div className="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div className="flex min-w-0 items-center gap-4">
          <span className="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-brand-600 text-lg font-bold text-white">{t.name.slice(0, 2).toUpperCase()}</span>
          <div className="min-w-0">
            <div className="flex flex-wrap items-center gap-2"><h1 className="text-2xl font-bold tracking-tight">{t.name}</h1><TenantStatusBadge status={t.status} /></div>
            <div className="mt-1 text-[13px] text-ink-500">{t.slug}.plms.app · {t.country_code ?? '—'} · {t.currency} · created {formatDate(t.created_at)}</div>
          </div>
        </div>
        <div className="flex flex-wrap gap-2">
          <Button icon={<ExternalLink className="size-4" />} disabled={suspended && false} onClick={async () => { await enterTenant(t.slug); navigate('/app') }}>Open workspace</Button>
          {suspended ? (
            <Button variant="secondary" icon={<Play className="size-4" />} loading={update.isPending} onClick={() => update.mutate({ status: 'active' })}>Reactivate</Button>
          ) : (
            <ConfirmButton title="Suspend tenant" message={`Suspend ${t.name}? Their members will be blocked until you reactivate the workspace.`} confirmLabel="Suspend" danger onConfirm={() => update.mutateAsync({ status: 'suspended' })} trigger={(o) => <Button variant="secondary" icon={<Pause className="size-4" />} onClick={o}>Suspend</Button>} />
          )}
          <Button variant="secondary" icon={<Timer className="size-4" />} loading={update.isPending} onClick={() => update.mutate({ extend_trial_days: 14 })}>Extend trial +14d</Button>
          <ConfirmButton danger title="Delete tenant" message={`Delete ${t.name}? The workspace is archived and members can no longer sign in.`} confirmLabel="Delete tenant" onConfirm={() => remove.mutateAsync()} trigger={(o) => <Button variant="ghost" icon={<Trash2 className="size-4" />} onClick={o}>Delete</Button>} />
        </div>
      </div>

      <div className="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-3">
        <StatTile label="Users" value={data.stats.users} hint={t.plan?.max_users ? `of ${t.plan.max_users}` : 'Unlimited'} />
        <StatTile label="Projects" value={data.stats.projects} hint={t.plan?.max_projects ? `of ${t.plan.max_projects}` : 'Unlimited'} />
        <StatTile label="Last activity" value={data.stats.last_activity_at ? relativeTime(data.stats.last_activity_at) : '—'} />
      </div>

      <div className="mt-6 grid gap-6 xl:grid-cols-3">
        <div className="space-y-6 xl:col-span-2">
          <TenantForm key={t.id + t.status + t.currency} tenant={t} plans={plans ?? []} onSave={(body) => update.mutateAsync(body)} saving={update.isPending} />

          <Card>
            <CardHeader title="Members" subtitle={`${data.users.length} ${data.users.length === 1 ? 'person' : 'people'} in this workspace`} />
            <div className="mt-3 overflow-x-auto">
              <table className="w-full min-w-[560px] text-left text-[13px]">
                <thead className="border-y border-line bg-slate-50/70 text-xs font-bold tracking-wide text-ink-500 uppercase"><tr><th className="px-6 py-2.5">Person</th><th className="px-4 py-2.5">Role</th><th className="px-4 py-2.5">Status</th><th className="px-6 py-2.5 text-right">Last sign-in</th></tr></thead>
                <tbody className="divide-y divide-line">
                  {data.users.map((u) => (
                    <tr key={u.id}>
                      <td className="px-6 py-3"><div className="flex items-center gap-3"><Avatar name={u.name} size="sm" /><div className="min-w-0"><div className="truncate font-semibold">{u.name}</div><div className="truncate text-xs text-ink-500">{u.email}</div></div></div></td>
                      <td className="px-4 py-3">{u.roles?.[0] ? ROLE_LABELS[u.roles[0]] : '—'}</td>
                      <td className="px-4 py-3"><Badge className="bg-slate-100 text-slate-600 ring-slate-200 capitalize">{u.status}</Badge></td>
                      <td className="px-6 py-3 text-right text-ink-500">{u.last_login_at ? formatDateTime(u.last_login_at) : 'Never'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </Card>
        </div>

        <div className="space-y-6">
          <Card className="p-5 sm:p-6">
            <h3 className="mb-4 text-[15px] font-bold">Subscription</h3>
            <dl className="space-y-3 text-[13px]">
              <div className="flex justify-between"><dt className="text-ink-500">Plan</dt><dd className="font-semibold">{t.plan?.name ?? '—'}</dd></div>
              <div className="flex justify-between"><dt className="text-ink-500">Status</dt><dd><TenantStatusBadge status={t.status} /></dd></div>
              <div className="flex justify-between"><dt className="text-ink-500">Trial ends</dt><dd className="font-semibold">{t.trial_ends_at ? formatDate(t.trial_ends_at) : '—'}</dd></div>
              <div className="flex justify-between"><dt className="text-ink-500">Time zone</dt><dd className="font-semibold">{t.timezone}</dd></div>
            </dl>
          </Card>

          <Card>
            <CardHeader title="Recent activity" />
            <ul className="mt-2 px-5 pb-5 sm:px-6">
              {data.activity.length === 0 && <li className="py-4 text-[13px] text-ink-500">No activity yet.</li>}
              {data.activity.map((a) => (
                <li key={a.id} className="flex gap-3 border-b border-line py-2.5 text-[13px] last:border-0">
                  <span className="mt-1.5 size-2 shrink-0 rounded-full bg-brand-500" />
                  <div className="min-w-0 flex-1"><code className="text-[12px]">{a.event}</code><div className="text-xs text-ink-500">{a.user ?? 'System'} · {relativeTime(a.created_at)}</div></div>
                </li>
              ))}
            </ul>
          </Card>
        </div>
      </div>
    </>
  )
}

function TenantForm({ tenant, plans, onSave, saving }: { tenant: Tenant; plans: { id: number; name: string }[]; onSave: (body: Record<string, unknown>) => Promise<unknown>; saving: boolean }) {
  const { config } = useConfig()
  const [form, setForm] = useState({
    name: tenant.name, slug: tenant.slug, status: tenant.status, plan_id: String(tenant.plan?.id ?? ''), country_code: tenant.country_code ?? '', currency: tenant.currency,
    contact_email: tenant.contact_email ?? '', phone: tenant.phone ?? '', address: tenant.address ?? '', tax_id: tenant.tax_id ?? '', admin_notes: tenant.admin_notes ?? '',
  })
  const [errors, setErrors] = useState<Record<string, string>>({})

  async function submit() {
    setErrors({})
    try {
      await onSave({ ...form, plan_id: form.plan_id ? Number(form.plan_id) : undefined, country_code: form.country_code || null })
    } catch (e) {
      setErrors(fieldErrors(e))
    }
  }

  return (
    <Card className="p-5 sm:p-6">
      <h3 className="mb-5 text-[15px] font-bold">Tenant details</h3>
      <div className="grid gap-5 sm:grid-cols-2">
        <Field label="Organisation name" htmlFor="td-name" error={errors.name}><Input id="td-name" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} /></Field>
        <Field label="Workspace URL" htmlFor="td-slug" error={errors.slug}><Input id="td-slug" value={form.slug} onChange={(e) => setForm({ ...form, slug: e.target.value.toLowerCase() })} /></Field>
        <Field label="Status" htmlFor="td-status"><Select id="td-status" value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value as Tenant['status'] })}>{['trial', 'active', 'past_due', 'suspended', 'cancelled'].map((s) => <option key={s} value={s}>{s.replace('_', ' ').replace(/^./, (c) => c.toUpperCase())}</option>)}</Select></Field>
        <Field label="Plan" htmlFor="td-plan"><Select id="td-plan" value={form.plan_id} onChange={(e) => setForm({ ...form, plan_id: e.target.value })}>{plans.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}</Select></Field>
        <Field label="Country" htmlFor="td-country" error={errors.country_code}><Select id="td-country" value={form.country_code} onChange={(e) => setForm({ ...form, country_code: e.target.value })}><option value="">—</option>{config.countries.map((c) => <option key={c.code} value={c.code}>{c.name} ({c.code})</option>)}</Select></Field>
        <Field label="Currency" htmlFor="td-currency" error={errors.currency}><Select id="td-currency" value={form.currency} onChange={(e) => setForm({ ...form, currency: e.target.value })}>{config.currencies.map((c) => <option key={c.code} value={c.code}>{c.code} — {c.name}</option>)}</Select></Field>
        <Field label="Contact email" htmlFor="td-email" error={errors.contact_email}><Input id="td-email" type="email" value={form.contact_email} onChange={(e) => setForm({ ...form, contact_email: e.target.value })} /></Field>
        <Field label="Phone" htmlFor="td-phone"><Input id="td-phone" value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} /></Field>
        <Field label="Tax ID" htmlFor="td-tax"><Input id="td-tax" value={form.tax_id} onChange={(e) => setForm({ ...form, tax_id: e.target.value })} /></Field>
        <div className="sm:col-span-2"><Field label="Address" htmlFor="td-addr"><Textarea id="td-addr" rows={2} value={form.address} onChange={(e) => setForm({ ...form, address: e.target.value })} /></Field></div>
        <div className="sm:col-span-2"><Field label="Internal notes (visible to super admins only)" htmlFor="td-notes"><Textarea id="td-notes" rows={3} value={form.admin_notes} onChange={(e) => setForm({ ...form, admin_notes: e.target.value })} /></Field></div>
      </div>
      <div className="mt-6 flex justify-end"><Button loading={saving} onClick={submit}>Save changes</Button></div>
    </Card>
  )
}
