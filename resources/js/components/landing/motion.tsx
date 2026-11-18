import { useEffect, useRef, useState, type CSSProperties, type ElementType, type ReactNode } from 'react'
import { cn } from '@/lib/utils'

const prefersReducedMotion = () => typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches

/** True once the element has scrolled into view (stays true). Instantly true for reduced-motion users. */
export function useInView<T extends Element>(threshold = 0.2) {
  const ref = useRef<T>(null)
  const [seen, setSeen] = useState(false)

  useEffect(() => {
    const node = ref.current
    if (!node || seen) return
    if (prefersReducedMotion() || typeof IntersectionObserver === 'undefined') {
      setSeen(true)
      return
    }
    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          setSeen(true)
          observer.disconnect()
        }
      },
      { threshold, rootMargin: '0px 0px -8% 0px' },
    )
    observer.observe(node)
    return () => observer.disconnect()
  }, [seen, threshold])

  return [ref, seen] as const
}

/** Fades and lifts its children in when scrolled into view. `delay` is in milliseconds. */
export function Reveal({ children, delay = 0, as: Tag = 'div', className }: { children: ReactNode; delay?: number; as?: ElementType; className?: string }) {
  const [ref, seen] = useInView<HTMLElement>(0.15)
  return (
    <Tag ref={ref} className={cn('reveal', seen && 'is-visible', className)} style={{ '--d': `${delay}ms` } as CSSProperties}>
      {children}
    </Tag>
  )
}

/** Counts from 0 up to `value` once visible. */
export function CountUp({ value, duration = 1400, suffix = '', className }: { value: number; duration?: number; suffix?: string; className?: string }) {
  const [ref, seen] = useInView<HTMLSpanElement>(0.4)
  const [shown, setShown] = useState(0)

  useEffect(() => {
    if (!seen) return
    if (prefersReducedMotion()) {
      setShown(value)
      return
    }
    let frame = 0
    const start = performance.now()
    const tick = (now: number) => {
      const t = Math.min(1, (now - start) / duration)
      setShown(Math.round(value * (1 - Math.pow(1 - t, 3))))
      if (t < 1) frame = requestAnimationFrame(tick)
    }
    frame = requestAnimationFrame(tick)
    return () => cancelAnimationFrame(frame)
  }, [seen, value, duration])

  return <span ref={ref} className={cn('tabular', className)}>{shown}{suffix}</span>
}

/** A bar that grows to `percent` once visible. */
export function GrowBar({ percent, color, delay = 0, className }: { percent: number; color: string; delay?: number; className?: string }) {
  const [ref, seen] = useInView<HTMLDivElement>(0.3)
  return (
    <div ref={ref} className={cn('h-2 overflow-hidden rounded-full bg-slate-100', className)}>
      <div className="h-full rounded-full transition-[width] duration-[1400ms] ease-[cubic-bezier(0.22,1,0.36,1)]" style={{ width: seen ? `${percent}%` : '0%', backgroundColor: color, transitionDelay: `${delay}ms` }} />
    </div>
  )
}

/** Cycles through `words`, re-triggering a rise-in animation each time. */
export function RotatingWord({ words, interval = 2600, className }: { words: string[]; interval?: number; className?: string }) {
  const [index, setIndex] = useState(0)

  useEffect(() => {
    if (prefersReducedMotion()) return
    const id = window.setInterval(() => setIndex((i) => (i + 1) % words.length), interval)
    return () => window.clearInterval(id)
  }, [words.length, interval])

  return (
    <span key={index} className={cn('inline-block motion-safe:animate-word-in', className)}>
      {words[index]}
    </span>
  )
}

/** Thin brand-coloured bar at the top of the page showing how far the visitor has scrolled. */
export function ScrollProgress() {
  const barRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    const update = () => {
      const max = document.documentElement.scrollHeight - window.innerHeight
      const ratio = max > 0 ? Math.min(1, window.scrollY / max) : 0
      if (barRef.current) barRef.current.style.transform = `scaleX(${ratio})`
    }
    update()
    window.addEventListener('scroll', update, { passive: true })
    window.addEventListener('resize', update)
    return () => {
      window.removeEventListener('scroll', update)
      window.removeEventListener('resize', update)
    }
  }, [])

  return (
    <div className="fixed inset-x-0 top-0 z-50 h-[3px] bg-transparent" aria-hidden>
      <div ref={barRef} className="h-full origin-left scale-x-0 bg-gradient-to-r from-brand-600 via-orange-500 to-brand-500" />
    </div>
  )
}

/** The id of the section currently under the header, for highlighting the nav. */
export function useScrollSpy(ids: string[]) {
  const [active, setActive] = useState<string | null>(null)
  const key = ids.join('|')

  useEffect(() => {
    if (typeof IntersectionObserver === 'undefined') return
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) setActive(entry.target.id)
        })
      },
      { rootMargin: '-45% 0px -50% 0px' },
    )
    key.split('|').forEach((id) => {
      const el = document.getElementById(id)
      if (el) observer.observe(el)
    })
    return () => observer.disconnect()
  }, [key])

  return active
}

/** Floating button that appears after scrolling and returns to the top. */
export function BackToTop() {
  const [show, setShow] = useState(false)

  useEffect(() => {
    const onScroll = () => setShow(window.scrollY > 700)
    onScroll()
    window.addEventListener('scroll', onScroll, { passive: true })
    return () => window.removeEventListener('scroll', onScroll)
  }, [])

  return (
    <button
      type="button"
      aria-label="Back to top"
      onClick={() => window.scrollTo({ top: 0, behavior: prefersReducedMotion() ? 'auto' : 'smooth' })}
      className={cn(
        'fixed right-5 bottom-5 z-40 flex size-11 items-center justify-center rounded-full bg-brand-600 text-white shadow-lg shadow-brand-600/30 transition duration-300 hover:-translate-y-1 hover:bg-brand-700',
        show ? 'translate-y-0 opacity-100' : 'pointer-events-none translate-y-4 opacity-0',
      )}
    >
      <svg viewBox="0 0 24 24" className="size-5" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><path d="m5 15 7-7 7 7" /></svg>
    </button>
  )
}

/** Tilts its child toward the pointer for a subtle 3D feel. */
export function TiltFrame({ children, className }: { children: ReactNode; className?: string }) {
  const ref = useRef<HTMLDivElement>(null)

  function onMove(e: React.MouseEvent<HTMLDivElement>) {
    if (prefersReducedMotion() || !ref.current) return
    const rect = ref.current.getBoundingClientRect()
    const x = (e.clientX - rect.left) / rect.width - 0.5
    const y = (e.clientY - rect.top) / rect.height - 0.5
    ref.current.style.transform = `perspective(1400px) rotateX(${(-y * 3).toFixed(2)}deg) rotateY(${(x * 4).toFixed(2)}deg)`
  }

  function reset() {
    if (ref.current) ref.current.style.transform = ''
  }

  return (
    <div onMouseMove={onMove} onMouseLeave={reset}>
      <div ref={ref} className={cn('transition-transform duration-300 ease-out will-change-transform', className)}>{children}</div>
    </div>
  )
}

/** Card whose soft highlight follows the pointer. */
export function SpotlightCard({ children, className }: { children: ReactNode; className?: string }) {
  return (
    <div
      className={cn('group spotlight card relative overflow-hidden p-6 transition duration-300 hover:-translate-y-1 hover:shadow-pop', className)}
      onMouseMove={(e) => {
        const rect = e.currentTarget.getBoundingClientRect()
        e.currentTarget.style.setProperty('--mx', `${e.clientX - rect.left}px`)
        e.currentTarget.style.setProperty('--my', `${e.clientY - rect.top}px`)
      }}
    >
      <div className="relative">{children}</div>
    </div>
  )
}
