import { useState, type ReactNode, type TextareaHTMLAttributes } from 'react'
import { Monitor, Moon, Sun } from 'lucide-react'
import { useTheme, type ThemePreference } from '@/context/ThemeContext'
import { cn } from '@/lib/utils'
import { Alert, Button, Modal } from './index'

/* ---------- Tabs (scroll horizontally on small screens) ---------- */
export function Tabs<T extends string>({ tabs, value, onChange, className }: { tabs: { value: T; label: string; count?: number }[]; value: T; onChange: (value: T) => void; className?: string }) {
  return (
    <div className={cn('scrollbar-thin -mx-4 overflow-x-auto border-b border-line px-4 sm:mx-0 sm:px-0', className)} role="tablist">
      <div className="flex min-w-max gap-1">
        {tabs.map((tab) => (
          <button
            key={tab.value}
            role="tab"
            aria-selected={value === tab.value}
            onClick={() => onChange(tab.value)}
            className={cn(
              '-mb-px flex items-center gap-2 border-b-2 px-3.5 py-3 text-[13.5px] font-semibold whitespace-nowrap transition-colors',
              value === tab.value ? 'border-brand-600 text-brand-700' : 'border-transparent text-ink-500 hover:text-ink-900',
            )}
          >
            {tab.label}
            {tab.count !== undefined && <span className="tabular rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-bold text-ink-500">{tab.count}</span>}
          </button>
        ))}
      </div>
    </div>
  )
}

/* ---------- Switch ---------- */
export function Switch({ checked, onChange, label, disabled }: { checked: boolean; onChange: (checked: boolean) => void; label: string; disabled?: boolean }) {
  return (
    <button
      type="button"
      role="switch"
      aria-checked={checked}
      aria-label={label}
      disabled={disabled}
      onClick={() => onChange(!checked)}
      className={cn('relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors disabled:opacity-50', checked ? 'bg-brand-600' : 'bg-slate-300')}
    >
      <span className={cn('inline-block size-5 rounded-full bg-white shadow transition-transform', checked ? 'translate-x-[22px]' : 'translate-x-0.5')} />
    </button>
  )
}

export function Textarea({ className, ...rest }: TextareaHTMLAttributes<HTMLTextAreaElement>) {
  return <textarea className={cn('input h-auto py-2.5', className)} {...rest} />
}

/* ---------- Theme toggle ---------- */
const OPTIONS: { value: ThemePreference; label: string; icon: typeof Sun }[] = [
  { value: 'light', label: 'Light', icon: Sun },
  { value: 'dark', label: 'Dark', icon: Moon },
  { value: 'system', label: 'System', icon: Monitor },
]

/** Compact icon button: flips light ↔ dark. */
export function ThemeToggle({ className }: { className?: string }) {
  const { resolved, toggle } = useTheme()
  const next = resolved === 'dark' ? 'light' : 'dark'
  return (
    <button onClick={toggle} className={cn('rounded-xl p-2.5 text-ink-500 transition hover:bg-slate-100 hover:text-ink-900', className)} aria-label={`Switch to ${next} mode`} title={`Switch to ${next} mode`}>
      {resolved === 'dark' ? <Sun className="size-[18px]" /> : <Moon className="size-[18px]" />}
    </button>
  )
}

/** Three-way selector (light / dark / system) for settings screens. */
export function ThemeSelector() {
  const { preference, setPreference } = useTheme()
  return (
    <div className="grid grid-cols-3 gap-2 sm:max-w-md" role="radiogroup" aria-label="Theme">
      {OPTIONS.map(({ value, label, icon: Icon }) => (
        <button
          key={value}
          role="radio"
          aria-checked={preference === value}
          onClick={() => setPreference(value)}
          className={cn('flex flex-col items-center gap-2 rounded-xl border px-3 py-3.5 text-[13px] font-semibold transition', preference === value ? 'border-brand-600 bg-brand-50 text-brand-700 ring-2 ring-brand-100' : 'border-line text-ink-700 hover:border-slate-300')}
        >
          <Icon className="size-5" />
          {label}
        </button>
      ))}
    </div>
  )
}

/* ---------- Confirm dialog ---------- */
export function ConfirmButton({ children, title, message, confirmLabel = 'Confirm', danger = false, onConfirm, trigger }: {
  children?: ReactNode
  title: string
  message: string
  confirmLabel?: string
  danger?: boolean
  onConfirm: () => Promise<unknown> | unknown
  trigger: (open: () => void) => ReactNode
}) {
  const [open, setOpen] = useState(false)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function run() {
    setBusy(true)
    setError(null)
    try {
      await onConfirm()
      setOpen(false)
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Action failed.')
    } finally {
      setBusy(false)
    }
  }

  return (
    <>
      {trigger(() => setOpen(true))}
      <Modal open={open} onClose={() => setOpen(false)} title={title} footer={
        <>
          <Button variant="secondary" onClick={() => setOpen(false)}>Cancel</Button>
          <Button variant={danger ? 'danger' : 'primary'} loading={busy} onClick={run}>{confirmLabel}</Button>
        </>
      }>
        <p className="text-[13.5px] leading-relaxed text-ink-700">{message}</p>
        {children}
        {error && <div className="mt-4"><Alert>{error}</Alert></div>}
      </Modal>
    </>
  )
}

/* ---------- Small stat tile ---------- */
export function StatTile({ label, value, hint, tone }: { label: string; value: ReactNode; hint?: ReactNode; tone?: 'default' | 'danger' | 'success' }) {
  return (
    <div className="card p-4 sm:p-5">
      <div className="text-[12.5px] font-semibold text-ink-500">{label}</div>
      <div className={cn('tabular mt-1.5 text-2xl font-bold tracking-tight sm:text-[28px]', tone === 'danger' ? 'text-rose-600' : tone === 'success' ? 'text-emerald-600' : 'text-ink-900')}>{value}</div>
      {hint && <div className="mt-1 text-xs font-medium text-ink-400">{hint}</div>}
    </div>
  )
}
