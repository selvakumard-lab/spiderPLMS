import { useState, type FormEvent } from 'react'
import { useNavigate } from 'react-router'
import { ArrowLeft } from 'lucide-react'
import { useAuth } from '@/context/AuthContext'
import { errorMessage, fieldErrors } from '@/lib/api'
import { authApi } from '@/lib/endpoints'
import { Alert, Button, Field, Input } from '@/components/ui'
import { OtpInput } from '@/components/OtpInput'

/** Two-step e-mail OTP sign-in for one workspace (or the platform when none): e-mail → 6-digit code. */
export function OtpLogin({ workspace, redirectTo = '/app' }: { workspace?: string; redirectTo?: string }) {
  const { signIn } = useAuth()
  const navigate = useNavigate()
  const [email, setEmail] = useState('')
  const [code, setCode] = useState('')
  const [step, setStep] = useState<'email' | 'code'>('email')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [codeError, setCodeError] = useState<string | undefined>()
  const [resetKey, setResetKey] = useState(0)

  async function sendCode(e?: FormEvent) {
    e?.preventDefault()
    const typed = email.trim()
    if (!typed) return setError('Enter your email address.')
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(typed)) return setError('Enter a valid email address, like name@company.com.')
    setBusy(true)
    setError(null)
    try {
      await authApi.requestOtp({ workspace, email: typed })
      setCode('')
      setResetKey((k) => k + 1)
      setStep('code')
    } catch (err) {
      const fe = fieldErrors(err)
      setError(fe.email ?? fe.workspace ?? errorMessage(err))
    } finally {
      setBusy(false)
    }
  }

  /** Called by the Sign in button, and automatically as soon as the last digit is typed or the code is pasted. */
  async function verify(entered: string = code) {
    if (busy) return
    if (!/^\d{6}$/.test(entered)) return setCodeError('Enter all 6 digits of the code.')
    setBusy(true)
    setCodeError(undefined)
    try {
      signIn(await authApi.verifyOtp({ workspace, email: email.trim(), code: entered }))
      navigate(redirectTo, { replace: true })
    } catch (err) {
      const fe = fieldErrors(err)
      if (fe.email && !fe.code) {
        setStep('email')
        setError(fe.email)
        setBusy(false)
        return
      }
      setCodeError(fe.code ?? errorMessage(err))
      setCode('')
      setResetKey((k) => k + 1)
      setBusy(false)
    }
  }

  if (step === 'email') {
    return (
      <form onSubmit={sendCode} className="space-y-5" noValidate>
        {error && <Alert>{error}</Alert>}
        <Field label="Email address" htmlFor="email">
          <Input id="email" type="email" autoComplete="email" autoFocus value={email} onChange={(e) => setEmail(e.target.value)} placeholder="you@company.com" required />
        </Field>
        <Button type="submit" size="lg" className="w-full" loading={busy}>
          Email me a sign-in code
        </Button>
      </form>
    )
  }

  return (
    <form onSubmit={(e: FormEvent) => { e.preventDefault(); void verify() }} className="space-y-5" noValidate>
      <div>
        <span className="label text-center">Enter the 6-digit code</span>
        <OtpInput value={code} onChange={(v) => { setCode(v); setCodeError(undefined) }} onComplete={(v) => void verify(v)} invalid={!!codeError} disabled={busy} resetKey={resetKey} />
        <p className={`mt-3 text-center text-[13px] ${codeError ? 'font-semibold text-rose-600' : 'text-ink-500'}`} aria-live="polite">
          {codeError ?? `Sent to ${email.trim()}. It expires in 10 minutes. Paste it and we sign you in.`}
        </p>
      </div>
      <Button type="submit" size="lg" className="w-full" loading={busy}>
        Sign in
      </Button>
      <div className="flex items-center justify-between text-[13px] font-semibold">
        <button type="button" onClick={() => { setStep('email'); setCode(''); setCodeError(undefined) }} className="inline-flex items-center gap-1 text-ink-500 hover:text-ink-900">
          <ArrowLeft className="size-3.5" /> Change email
        </button>
        <button type="button" onClick={() => sendCode()} disabled={busy} className="text-brand-600 hover:text-brand-700">
          Resend code
        </button>
      </div>
    </form>
  )
}
