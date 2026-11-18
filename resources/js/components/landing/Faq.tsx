import { useState } from 'react'
import { ChevronDown } from 'lucide-react'
import { cn } from '@/lib/utils'

export type FaqItem = { q: string; a: string }

/** Accessible accordion; one answer open at a time, with a smooth height transition. */
export function Faq({ items }: { items: FaqItem[] }) {
  const [open, setOpen] = useState<number | null>(0)

  return (
    <div className="divide-y divide-line overflow-hidden rounded-2xl border border-line bg-surface shadow-card">
      {items.map((item, i) => {
        const isOpen = open === i
        return (
          <div key={item.q}>
            <h3>
              <button
                type="button" aria-expanded={isOpen} aria-controls={`faq-${i}`}
                onClick={() => setOpen(isOpen ? null : i)}
                className="flex w-full items-center justify-between gap-4 px-5 py-4 text-left text-[15px] font-semibold text-ink-900 transition hover:bg-slate-50 sm:px-6"
              >
                {item.q}
                <ChevronDown className={cn('size-5 shrink-0 text-brand-600 transition-transform duration-300', isOpen && 'rotate-180')} />
              </button>
            </h3>
            <div id={`faq-${i}`} role="region" className={cn('grid transition-[grid-template-rows] duration-300 ease-out', isOpen ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]')}>
              <div className="overflow-hidden"><p className="px-5 pb-5 text-[14px] leading-relaxed text-ink-500 sm:px-6">{item.a}</p></div>
            </div>
          </div>
        )
      })}
    </div>
  )
}
