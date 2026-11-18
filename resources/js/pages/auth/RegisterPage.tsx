import { useState, type FormEvent } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router'
import { useQuery } from '@tanstack/react-query'
import { ArrowLeft, Check } from 'lucide-react'
import { useAuth } from '@/context/AuthContext'
import { errorMessage, fieldErrors } from '@/lib/api'
import { authApi, publicApi } from '@/lib/endpoints'
import { payWithRazorpay } from '@/lib/razorpay'
import { useVisitorCountry } from '@/lib/geo'
import { useConfig } from '@/context/ConfigContext'
import { cn, formatMoney } from '@/lib/utils'
import { AuthLayout } from '@/components/layout/AuthLayout'
import { Alert, Button, Field, Input, Select } from '@/components/ui'

/** "Acme Digital" → "acmeDigital" */
const slugify = (v: string) =>
  v.trim().split(/[^A-Za-z0-9]+/).filter(Boolean).map((w, i) => (i === 0 ? w.charAt(0).toLowerCase() + w.slice(1) : w.charAt(0).toUpperCase() + w.slice(1))).join('').slice(0, 40)

/** Two-step onboarding: 1) organisation & workspace URL, 2) admin account. */
export default function RegisterPage() {
  const { register } = useAuth()
  const [created, setCreated] = useState<string | null>(null)
  const navigate = useNavigate()
  const [params] = useSearchParams()
  const { config } = useConfig()
  const visitor = useVisitorCountry()
  const [country, setCountry] = useState<string | null>(null)
  const countryCode = country ?? visitor.code ?? config.app.default_country
  const { data: pricing } = useQuery({ queryKey: ['pricing', countryCode], queryFn: () => publicApi.pricing(countryCode), retry: false })
  const plans = pricing?.plans ?? []
  const effectiveCountry = pricing?.country?.code ?? countryCode

  const [step, setStep] = useState<1 | 2>(1)
  const [slugTouched, setSlugTouched] = useState(false)
  const [form, setForm] = useState({ organization: '', slug: '', plan: params.get('plan') ?? 'growth', name: '', email: '', phone: '' })
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  const set = (key: keyof typeof form, value: string) => setForm((f) => ({ ...f, [key]: value }))

  function next(e: FormEvent) {
    e.preventDefault()
    const errs: Record<string, string> = {}
    if (!form.organization.trim()) errs.organization = 'Enter your organisation name.'
    if (form.slug.length < 3) errs.slug = 'Use at least 3 characters.'
    setErrors(errs)
    if (!Object.keys(errs).length) setStep(2)
  }

  async function submit(e: FormEvent) {
    e.preventDefault()
    setBusy(true)
    setError(null)
    try {
      const order = await authApi.registerOrder({ ...form, country_code: effectiveCountry })
      const planName = plans.find((p) => p.code === form.plan)?.name ?? order.plan
      const payment = await payWithRazorpay(order, `${planName} plan - first month`)
      await register(payment)
      setCreated(`${window.location.origin}/${form.slug}`)
    } catch (err) {
      const errs = fieldErrors(err)
      setErrors(errs)
      if (errs.organization || errs.slug || errs.plan || errs.country_code) setStep(1)
      if (errs.payment) setError(errs.payment)
      if (!Object.keys(errs).length) setError(errorMessage(err))
    } finally {
      setBusy(false)
    }
  }

  return (
    <AuthLayout
      title={created ? 'Your workspace is ready' : step === 1 ? 'Create your workspace' : 'Your account details'}
      subtitle={created ? 'Registration is complete.' : step === 1 ? 'Choose your package and company details. You pay securely with Razorpay.' : 'You will sign in with a one-time code sent to this email. No password needed.'}
      footer={
        <>
          Already have a workspace?{' '}
          <Link to="/login" className="font-semibold text-brand-600 hover:text-brand-700">
            Sign in
          </Link>
        </>
      }
    >
      {created && (
        <div className="space-y-5">
          <Alert tone="success">Bookmark your workspace link. Team members sign in here with an email code.</Alert>
          <div className="rounded-xl border border-line bg-slate-50 px-4 py-3 text-[15px] font-bold break-all text-ink-900">{created}</div>
          <Button size="lg" className="w-full" onClick={() => navigate('/app', { replace: true })}>Go to my dashboard</Button>
        </div>
      )}
      <div className={cn('mb-6 flex items-center gap-3', created && 'hidden')}>
        {[1, 2].map((n) => (
          <div key={n} className="flex flex-1 items-center gap-3">
            <span className={cn('flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-bold', step >= n ? 'bg-brand-600 text-white' : 'bg-slate-100 text-ink-400')}>
              {step > n ? <Check className="size-3.5" strokeWidth={3} /> : n}
            </span>
            <span className={cn('text-[13px] font-semibold', step >= n ? 'text-ink-900' : 'text-ink-400')}>{n === 1 ? 'Workspace' : 'Account'}</span>
            {n === 1 && <span className="h-px flex-1 bg-line" />}
          </div>
        ))}
      </div>

      {!config.app.registration_open && <div className="mb-5"><Alert>New workspace sign-ups are currently closed. Please contact {config.app.support_email}.</Alert></div>}
      {error && <div className="mb-5"><Alert>{error}</Alert></div>}

      {created ? null : step === 1 ? (
        <form onSubmit={next} className="space-y-5" noValidate>
          <Field label="Organisation name" htmlFor="organization" error={errors.organization}>
            <Input id="organization" value={form.organization} invalid={!!errors.organization} placeholder="Acme Digital" autoFocus onChange={(e) => {
              set('organization', e.target.value)
              if (!slugTouched) set('slug', slugify(e.target.value))
            }} />
          </Field>
          <Field label="Country" htmlFor="country" error={errors.country_code} hint={pricing?.currency ? `Your workspace currency will be ${pricing.currency.code}. You can change it later in Settings.` : undefined}>
            <Select id="country" value={effectiveCountry} onChange={(e) => setCountry(e.target.value)}>
              {config.countries.map((c) => <option key={c.code} value={c.code}>{c.name} ({c.code})</option>)}
            </Select>
          </Field>
          <Field label="Workspace URL" htmlFor="slug" error={errors.slug}>
            <div className="flex rounded-xl focus-within:ring-4 focus-within:ring-brand-100">
              <Input id="slug" className="rounded-r-none focus:ring-0" value={form.slug} invalid={!!errors.slug} placeholder="acmeDigital" onChange={(e) => { setSlugTouched(true); set('slug', slugify(e.target.value)) }} />
              <span className="inline-flex items-center rounded-r-xl border border-l-0 border-line bg-slate-50 px-3 text-[13px] font-medium text-ink-500">/{form.slug || 'yourCompany'}</span>
            </div>
          </Field>
          {plans.length > 0 && (
            <div>
              <span className="label">Plan</span>
              <div className="grid gap-2 sm:grid-cols-3">
                {plans.map((p) => (
                  <button type="button" key={p.code} onClick={() => set('plan', p.code)} className={cn('rounded-xl border p-3 text-left transition', form.plan === p.code ? 'border-brand-600 bg-brand-50 ring-2 ring-brand-100' : 'border-line hover:border-slate-300')}>
                    <div className="text-[13px] font-bold">{p.name}</div>
                    <div className="mt-0.5 text-xs font-medium text-ink-500">{formatMoney(p.price_monthly, p.currency, true)}/mo</div>
                  </button>
                ))}
              </div>
            </div>
          )}
          <Button type="submit" size="lg" className="w-full">Continue</Button>
        </form>
      ) : (
        <form onSubmit={submit} className="space-y-5" noValidate>
          <Field label="Full name" htmlFor="name" error={errors.name}>
            <Input id="name" value={form.name} autoFocus autoComplete="name" invalid={!!errors.name} onChange={(e) => set('name', e.target.value)} placeholder="Olivia Bennett" />
          </Field>
          <Field label="Work email" htmlFor="email" error={errors.email}>
            <Input id="email" type="email" autoComplete="email" value={form.email} invalid={!!errors.email} onChange={(e) => set('email', e.target.value)} placeholder="you@company.com" />
          </Field>
          <Field label="Phone (optional)" htmlFor="phone" error={errors.phone}>
            <Input id="phone" type="tel" autoComplete="tel" value={form.phone} invalid={!!errors.phone} onChange={(e) => set('phone', e.target.value)} placeholder="+91 98765 43210" />
          </Field>
          <div className="flex gap-3">
            <Button type="button" variant="secondary" size="lg" onClick={() => setStep(1)} icon={<ArrowLeft className="size-4" />}>Back</Button>
            <Button type="submit" size="lg" className="flex-1" loading={busy}>Pay & create workspace</Button>
          </div>
        </form>
      )}
    </AuthLayout>
  )
}
