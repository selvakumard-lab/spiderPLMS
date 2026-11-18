import { createContext, useCallback, useContext, useMemo, useRef, useState, type ReactNode } from 'react'
import { CircleAlert, CircleCheck, X } from 'lucide-react'
import { cn } from '@/lib/utils'

interface Toast {
  id: number
  tone: 'success' | 'error'
  message: string
}

interface ToastApi {
  success: (message: string) => void
  error: (message: string) => void
}

const ToastContext = createContext<ToastApi | null>(null)

/** Lightweight, accessible toasts (bottom-right on desktop, bottom on phones). */
export function ToastProvider({ children }: { children: ReactNode }) {
  const [toasts, setToasts] = useState<Toast[]>([])
  const nextId = useRef(1)

  const dismiss = useCallback((id: number) => setToasts((list) => list.filter((t) => t.id !== id)), [])
  const push = useCallback(
    (tone: Toast['tone'], message: string) => {
      const id = nextId.current++
      setToasts((list) => [...list.slice(-3), { id, tone, message }])
      window.setTimeout(() => dismiss(id), tone === 'error' ? 6000 : 3500)
    },
    [dismiss],
  )

  const api = useMemo<ToastApi>(() => ({ success: (m) => push('success', m), error: (m) => push('error', m) }), [push])

  return (
    <ToastContext.Provider value={api}>
      {children}
      <div className="no-print pointer-events-none fixed inset-x-0 bottom-0 z-[60] flex flex-col items-center gap-2 p-4 sm:items-end sm:p-6" role="status" aria-live="polite">
        {toasts.map((t) => (
          <div key={t.id} className={cn('pointer-events-auto flex w-full max-w-sm animate-fade-in items-start gap-3 rounded-xl border bg-surface px-4 py-3 shadow-pop', t.tone === 'error' ? 'border-rose-300' : 'border-line')}>
            {t.tone === 'success' ? <CircleCheck className="mt-0.5 size-[18px] shrink-0 text-emerald-600" /> : <CircleAlert className="mt-0.5 size-[18px] shrink-0 text-rose-600" />}
            <p className="flex-1 text-[13px] font-semibold text-ink-900">{t.message}</p>
            <button onClick={() => dismiss(t.id)} className="-mr-1 rounded p-0.5 text-ink-400 hover:text-ink-900" aria-label="Dismiss">
              <X className="size-4" />
            </button>
          </div>
        ))}
      </div>
    </ToastContext.Provider>
  )
}

// eslint-disable-next-line react-refresh/only-export-components
export function useToast(): ToastApi {
  const ctx = useContext(ToastContext)
  if (!ctx) throw new Error('useToast must be used inside <ToastProvider>')
  return ctx
}
