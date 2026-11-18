import { useEffect, type HTMLAttributes, type InputHTMLAttributes, type ReactNode, type SelectHTMLAttributes } from 'react'
import { X } from 'lucide-react'
import { cn, initials } from '@/lib/utils'

export { Button } from './Button'

/* ---------- Card ---------- */
export function Card({ className, ...rest }: HTMLAttributes<HTMLDivElement>) {
  return <div className={cn('card', className)} {...rest} />
}

export function CardHeader({ title, subtitle, action, className }: { title: ReactNode; subtitle?: ReactNode; action?: ReactNode; className?: string }) {
  return (
    <div className={cn('flex items-start justify-between gap-4 px-5 pt-5 sm:px-6', className)}>
      <div className="min-w-0">
        <h3 className="text-[15px] font-bold text-ink-900">{title}</h3>
        {subtitle && <p className="mt-0.5 text-[13px] text-ink-500">{subtitle}</p>}
      </div>
      {action}
    </div>
  )
}

/* ---------- Page header ---------- */
export function PageHeader({ title, description, actions, eyebrow }: { title: ReactNode; description?: ReactNode; actions?: ReactNode; eyebrow?: ReactNode }) {
  return (
    <div className="mb-6 flex flex-col gap-4 sm:mb-8 sm:flex-row sm:items-end sm:justify-between">
      <div className="min-w-0">
        {eyebrow && <div className="mb-1.5 text-xs font-semibold tracking-wider text-brand-600 uppercase">{eyebrow}</div>}
        <h1 className="text-2xl font-bold tracking-tight text-ink-900 sm:text-[28px]">{title}</h1>
        {description && <p className="mt-1.5 max-w-2xl text-sm text-ink-500">{description}</p>}
      </div>
      {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
    </div>
  )
}

/* ---------- Badges ---------- */
export function Badge({ className, children }: { className?: string; children: ReactNode }) {
  return (
    <span className={cn('inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap ring-1 ring-inset', className)}>
      {children}
    </span>
  )
}

/* ---------- Avatar ---------- */
const AVATAR_TONES = ['bg-brand-100 text-brand-700', 'bg-orange-100 text-orange-700', 'bg-emerald-100 text-emerald-700', 'bg-amber-100 text-amber-800', 'bg-rose-100 text-rose-700', 'bg-stone-100 text-stone-700']

export function Avatar({ name, src, size = 'md', className }: { name: string; src?: string | null; size?: 'xs' | 'sm' | 'md' | 'lg'; className?: string }) {
  const dims = { xs: 'size-6 text-[10px]', sm: 'size-8 text-xs', md: 'size-9 text-[13px]', lg: 'size-12 text-base' }[size]
  const tone = AVATAR_TONES[[...name].reduce((sum, c) => sum + c.charCodeAt(0), 0) % AVATAR_TONES.length]
  return src ? (
    <img src={src} alt={name} className={cn('rounded-full object-cover ring-2 ring-white', dims, className)} />
  ) : (
    <span className={cn('inline-flex shrink-0 items-center justify-center rounded-full font-bold ring-2 ring-white', dims, tone, className)} title={name}>
      {initials(name)}
    </span>
  )
}

export function AvatarStack({ names, max = 4 }: { names: string[]; max?: number }) {
  const extra = names.length - max
  return (
    <div className="flex -space-x-2">
      {names.slice(0, max).map((n) => (
        <Avatar key={n} name={n} size="sm" />
      ))}
      {extra > 0 && <span className="inline-flex size-8 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-ink-500 ring-2 ring-white">+{extra}</span>}
    </div>
  )
}

/* ---------- Progress ---------- */
export function Progress({ value, color, className }: { value: number; color?: string; className?: string }) {
  return (
    <div className={cn('h-1.5 w-full overflow-hidden rounded-full bg-slate-100', className)} role="progressbar" aria-valuenow={value} aria-valuemin={0} aria-valuemax={100}>
      <div className="h-full rounded-full bg-brand-600 transition-[width] duration-500" style={{ width: `${Math.min(100, Math.max(0, value))}%`, backgroundColor: color }} />
    </div>
  )
}

/* ---------- Form fields ---------- */
export function Field({ label, error, hint, children, htmlFor }: { label: string; error?: string; hint?: ReactNode; children: ReactNode; htmlFor?: string }) {
  return (
    <div>
      <label className="label" htmlFor={htmlFor}>
        {label}
      </label>
      {children}
      {error ? <p className="mt-1.5 text-xs font-medium text-rose-600">{error}</p> : hint ? <p className="mt-1.5 text-xs text-ink-500">{hint}</p> : null}
    </div>
  )
}

export function Input({ className, invalid, ...rest }: InputHTMLAttributes<HTMLInputElement> & { invalid?: boolean }) {
  return <input className={cn('input', invalid && 'border-rose-400 focus:border-rose-500 focus:ring-rose-100', className)} {...rest} />
}

export function Select({ className, children, ...rest }: SelectHTMLAttributes<HTMLSelectElement>) {
  return (
    <select className={cn('input appearance-none bg-[url("data:image/svg+xml,%3Csvg%20xmlns=%27http://www.w3.org/2000/svg%27%20viewBox=%270%200%2020%2020%27%20fill=%27%2364748b%27%3E%3Cpath%20d=%27M5.23%207.21a.75.75%200%20011.06.02L10%2011.17l3.71-3.94a.75.75%200%20111.08%201.04l-4.25%204.5a.75.75%200%2001-1.08%200l-4.25-4.5a.75.75%200%2001.02-1.06z%27/%3E%3C/svg%3E")] bg-[length:18px] bg-[right_0.75rem_center] bg-no-repeat pr-10', className)} {...rest}>
      {children}
    </select>
  )
}

/* ---------- Feedback ---------- */
export function Spinner({ className }: { className?: string }) {
  return <span className={cn('inline-block size-5 animate-spin rounded-full border-2 border-brand-200 border-t-brand-600', className)} aria-label="Loading" />
}

export function Skeleton({ className }: { className?: string }) {
  return <div className={cn('animate-pulse rounded-lg bg-slate-200/70', className)} />
}

export function EmptyState({ icon, title, description, action }: { icon: ReactNode; title: string; description?: string; action?: ReactNode }) {
  return (
    <div className="flex flex-col items-center px-6 py-14 text-center">
      <div className="mb-4 flex size-12 items-center justify-center rounded-2xl bg-brand-50 text-brand-600">{icon}</div>
      <h3 className="text-[15px] font-bold text-ink-900">{title}</h3>
      {description && <p className="mt-1 max-w-sm text-sm text-ink-500">{description}</p>}
      {action && <div className="mt-5">{action}</div>}
    </div>
  )
}

export function Alert({ tone = 'error', children }: { tone?: 'error' | 'info' | 'success'; children: ReactNode }) {
  const tones = { error: 'bg-rose-50 text-rose-700 ring-rose-200', info: 'bg-brand-50 text-brand-800 ring-brand-200', success: 'bg-emerald-50 text-emerald-700 ring-emerald-200' }
  return <div className={cn('rounded-xl px-4 py-3 text-[13px] font-medium ring-1 ring-inset', tones[tone])}>{children}</div>
}

/* ---------- Modal (bottom sheet on phones, centred dialog on larger screens) ---------- */
export function Modal({ open, onClose, title, description, children, footer, size = 'md' }: { open: boolean; onClose: () => void; title: string; description?: string; children: ReactNode; footer?: ReactNode; size?: 'md' | 'lg' }) {
  useEffect(() => {
    if (!open) return
    const onKey = (e: KeyboardEvent) => e.key === 'Escape' && onClose()
    document.addEventListener('keydown', onKey)
    document.body.style.overflow = 'hidden'
    return () => {
      document.removeEventListener('keydown', onKey)
      document.body.style.overflow = ''
    }
  }, [open, onClose])

  if (!open) return null

  return (
    <div className="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-6" role="dialog" aria-modal="true" aria-labelledby="modal-title">
      <div className="absolute inset-0 animate-fade-in bg-black/50 backdrop-blur-[2px]" onClick={onClose} />
      <div className={cn('relative flex max-h-[92dvh] w-full animate-fade-in flex-col rounded-t-3xl bg-white shadow-pop sm:rounded-2xl', size === 'lg' ? 'sm:max-w-2xl' : 'sm:max-w-lg')}>
        <div className="flex items-start justify-between gap-4 border-b border-line px-5 py-4 sm:px-6">
          <div>
            <h2 id="modal-title" className="text-base font-bold text-ink-900">
              {title}
            </h2>
            {description && <p className="mt-0.5 text-[13px] text-ink-500">{description}</p>}
          </div>
          <button onClick={onClose} className="-mr-2 rounded-lg p-2 text-ink-400 hover:bg-slate-100 hover:text-ink-900" aria-label="Close">
            <X className="size-5" />
          </button>
        </div>
        <div className="scrollbar-thin overflow-y-auto px-5 py-5 sm:px-6">{children}</div>
        {footer && <div className="flex flex-col-reverse gap-2 border-t border-line px-5 py-4 sm:flex-row sm:justify-end sm:px-6">{footer}</div>}
      </div>
    </div>
  )
}

/** Compact country marker. (Flag emoji are not rendered by Windows browsers, so a code chip is used everywhere.) */
export function CountryChip({ code, className }: { code: string | null | undefined; className?: string }) {
  return (
    <span className={cn('inline-flex h-[18px] min-w-[26px] items-center justify-center rounded border border-line bg-slate-50 px-1 text-[10px] leading-none font-bold tracking-wide text-ink-500', className)} aria-hidden>
      {code ? code.toUpperCase() : '—'}
    </span>
  )
}
