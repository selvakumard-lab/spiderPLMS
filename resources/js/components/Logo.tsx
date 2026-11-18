import { useConfig } from '@/context/ConfigContext'
import { cn } from '@/lib/utils'

/** Brand mark + platform name (name comes from Super Admin → System Settings). */
export function Logo({ className, light = false, compact = false }: { className?: string; light?: boolean; compact?: boolean }) {
  const { config } = useConfig()
  const name = config.app.name
  const match = name.match(/^(\S+)\s+(\S+)$/) // "Spider PLMS" → two-tone

  return (
    <span className={cn('inline-flex items-center gap-2.5', className)}>
      <img
        src="/brand/spider-mark.png"
        srcSet="/brand/spider-mark.png 1x, /brand/spider-mark@2x.png 2x"
        alt=""
        width={32}
        height={32}
        className={cn('size-8 shrink-0 object-contain', light && 'rounded-lg bg-white p-0.5')}
        aria-hidden
      />
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
