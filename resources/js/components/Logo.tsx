import { useConfig } from '@/context/ConfigContext'
import { cn } from '@/lib/utils'

/** Brand mark + platform name (name comes from Super Admin → System Settings). */
export function Logo({ className, light = false, compact = false }: { className?: string; light?: boolean; compact?: boolean }) {
  const { config } = useConfig()
  const name = config.app.name
  const match = name.match(/^(\S+)\s+(\S+)$/) // "Spider PLMS" → two-tone

  return (
    <span className={cn('inline-flex items-center gap-2.5', className)}>
      <svg viewBox="0 0 32 32" className="size-8 shrink-0" aria-hidden>
        <rect width="32" height="32" rx="9" fill={light ? '#fff' : '#D6121B'} />
        <path d="M9 21.5 16 9l7 12.5" fill="none" stroke={light ? '#D6121B' : '#fff'} strokeWidth="2.6" strokeLinecap="round" strokeLinejoin="round" />
        <circle cx="16" cy="18.5" r="2.4" fill={light ? '#F87171' : '#FCA5A5'} />
      </svg>
      {!compact && (
        <span className={cn('text-[17px] font-extrabold tracking-tight', light ? 'text-white' : 'text-ink-900')}>
          {match ? (
            <>
              {match[1]}
              <span className={light ? 'text-brand-200' : 'text-brand-600'}>{match[2]}</span>
            </>
          ) : (
            name
          )}
        </span>
      )}
    </span>
  )
}
