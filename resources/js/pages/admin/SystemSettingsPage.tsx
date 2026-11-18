import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useConfig } from '@/context/ConfigContext'
import { useToast } from '@/context/ToastContext'
import { errorMessage, fieldErrors } from '@/lib/api'
import { adminApi } from '@/lib/endpoints'
import { Alert, Button, Card, Field, Input, PageHeader, Select, Skeleton } from '@/components/ui'
import { Switch, Textarea } from '@/components/ui/extra'

type Settings = Record<string, unknown>

export default function SystemSettingsPage() {
  const { data, isLoading, error } = useQuery({ queryKey: ['admin', 'settings'], queryFn: adminApi.settings })
  return (
    <>
      <PageHeader eyebrow="Platform" title="System settings" description="Platform-wide defaults. They apply to the landing page, sign-up, new tenants and every workspace." />
      {error && <Alert>{errorMessage(error)}</Alert>}
      {isLoading || !data ? <Skeleton className="h-[560px] rounded-2xl" /> : <SettingsForm initial={data} />}
    </>
  )
}

function SettingsForm({ initial }: { initial: Settings }) {
  const { config } = useConfig()
  const toast = useToast()
  const queryClient = useQueryClient()
  const [form, setForm] = useState({
    app_name: String(initial.app_name ?? ''), tagline: String(initial.tagline ?? ''), support_email: String(initial.support_email ?? ''),
    default_country: String(initial.default_country ?? 'US'), default_currency: String(initial.default_currency ?? 'USD'), default_theme: String(initial.default_theme ?? 'system'),
    registration_open: Boolean(initial.registration_open), trial_days: String(initial.trial_days ?? 14), maintenance_message: String(initial.maintenance_message ?? ''),
    invoice_prefix: String(initial.invoice_prefix ?? 'INV'), default_tax_rate: String(initial.default_tax_rate ?? 0), max_upload_mb: String(initial.max_upload_mb ?? 25),
  })
  const [errors, setErrors] = useState<Record<string, string>>({})

  const save = useMutation({
    mutationFn: () => adminApi.updateSettings({ ...form, trial_days: Number(form.trial_days), default_tax_rate: Number(form.default_tax_rate), max_upload_mb: Number(form.max_upload_mb) }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['public-config'] })
      queryClient.invalidateQueries({ queryKey: ['admin'] })
      queryClient.invalidateQueries({ queryKey: ['pricing'] })
      toast.success('System settings saved.')
    },
    onError: (e) => { setErrors(fieldErrors(e)); toast.error(errorMessage(e)) },
  })

  return (
    <div className="space-y-6">
      <Card className="p-5 sm:p-6">
        <h3 className="mb-5 text-[15px] font-bold">Branding</h3>
        <div className="grid gap-5 sm:grid-cols-2">
          <Field label="Application name" htmlFor="ss-name" error={errors.app_name}><Input id="ss-name" value={form.app_name} onChange={(e) => setForm({ ...form, app_name: e.target.value })} /></Field>
          <Field label="Support email" htmlFor="ss-email" error={errors.support_email}><Input id="ss-email" type="email" value={form.support_email} onChange={(e) => setForm({ ...form, support_email: e.target.value })} /></Field>
          <div className="sm:col-span-2"><Field label="Tagline" htmlFor="ss-tag" error={errors.tagline}><Input id="ss-tag" value={form.tagline} onChange={(e) => setForm({ ...form, tagline: e.target.value })} /></Field></div>
          <Field label="Default theme" htmlFor="ss-theme" hint="Used until a person picks their own light/dark setting."><Select id="ss-theme" value={form.default_theme} onChange={(e) => setForm({ ...form, default_theme: e.target.value })}><option value="system">Follow device</option><option value="light">Light</option><option value="dark">Dark</option></Select></Field>
        </div>
      </Card>

      <Card className="p-5 sm:p-6">
        <h3 className="mb-1 text-[15px] font-bold">Regional defaults</h3>
        <p className="mb-5 text-[13px] text-ink-500">Shown to visitors whose country is unknown or not enabled, and pre-selected for new tenants.</p>
        <div className="grid gap-5 sm:grid-cols-2">
          <Field label="Default country" htmlFor="ss-country" error={errors.default_country}><Select id="ss-country" value={form.default_country} onChange={(e) => setForm({ ...form, default_country: e.target.value })}>{config.countries.map((c) => <option key={c.code} value={c.code}>{c.name} ({c.code})</option>)}</Select></Field>
          <Field label="Default currency" htmlFor="ss-cur" error={errors.default_currency}><Select id="ss-cur" value={form.default_currency} onChange={(e) => setForm({ ...form, default_currency: e.target.value })}>{config.currencies.map((c) => <option key={c.code} value={c.code}>{c.code} — {c.name}</option>)}</Select></Field>
        </div>
      </Card>

      <Card className="p-5 sm:p-6">
        <h3 className="mb-5 text-[15px] font-bold">Sign-up & workspace defaults</h3>
        <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
          <div className="flex items-center justify-between gap-4 rounded-xl border border-line p-4 sm:col-span-2 lg:col-span-3">
            <div><div className="text-[14px] font-bold">Open registration</div><div className="text-[13px] text-ink-500">When off, only you can add tenants. The “Start free trial” buttons are hidden.</div></div>
            <Switch checked={form.registration_open} label="Open registration" onChange={(v) => setForm({ ...form, registration_open: v })} />
          </div>
          <Field label="Trial length (days)" htmlFor="ss-trial" error={errors.trial_days}><Input id="ss-trial" type="number" min={1} max={365} value={form.trial_days} onChange={(e) => setForm({ ...form, trial_days: e.target.value })} /></Field>
          <Field label="Default invoice prefix" htmlFor="ss-prefix" error={errors.invoice_prefix}><Input id="ss-prefix" value={form.invoice_prefix} onChange={(e) => setForm({ ...form, invoice_prefix: e.target.value })} /></Field>
          <Field label="Default tax rate (%)" htmlFor="ss-tax" error={errors.default_tax_rate}><Input id="ss-tax" type="number" min={0} max={100} step="0.01" value={form.default_tax_rate} onChange={(e) => setForm({ ...form, default_tax_rate: e.target.value })} /></Field>
          <Field label="Max upload size (MB)" htmlFor="ss-up" error={errors.max_upload_mb} hint="Also limited by the server's PHP upload settings."><Input id="ss-up" type="number" min={1} max={500} value={form.max_upload_mb} onChange={(e) => setForm({ ...form, max_upload_mb: e.target.value })} /></Field>
        </div>
      </Card>

      <Card className="p-5 sm:p-6">
        <h3 className="mb-1 text-[15px] font-bold">Announcement banner</h3>
        <p className="mb-4 text-[13px] text-ink-500">Shown at the top of the landing page and the app for everyone. Leave empty for none.</p>
        <Textarea rows={2} value={form.maintenance_message} onChange={(e) => setForm({ ...form, maintenance_message: e.target.value })} placeholder="Scheduled maintenance on Sunday 02:00–03:00 UTC." aria-label="Announcement" />
        {errors.maintenance_message && <p className="mt-1.5 text-xs font-medium text-rose-600">{errors.maintenance_message}</p>}
      </Card>

      <div className="flex justify-end"><Button size="lg" loading={save.isPending} onClick={() => { setErrors({}); save.mutate() }}>Save system settings</Button></div>
    </div>
  )
}
