import type { ReactNode } from 'react'
import { Link } from 'react-router'
import { CircleCheck } from 'lucide-react'
import { Logo } from '@/components/Logo'
import { ThemeToggle } from '@/components/ui/extra'
import { useConfig } from '@/context/ConfigContext'

const YEAR = new Date().getFullYear()

const POINTS = ['Daily activity, meetings and change requests in one place', 'Automatic status, delay and AI estimates', 'Isolated workspace with one-time-code sign-in']

export function AuthLayout({ title, subtitle, children, footer }: { title: string; subtitle: string; children: ReactNode; footer: ReactNode }) {
  const { config } = useConfig()

  return (
    <div className="grid min-h-dvh bg-surface lg:grid-cols-[1fr_minmax(0,560px)] xl:grid-cols-[1fr_minmax(0,640px)]">
      {/* Brand panel — hidden on phones and tablets */}
      <div className="relative hidden overflow-hidden bg-brand-950 lg:flex lg:flex-col lg:justify-between lg:p-12 xl:p-16">
        <div className="pointer-events-none absolute -top-40 -left-40 size-[520px] rounded-full bg-brand-600/40 blur-3xl" />
        <div className="pointer-events-none absolute -right-32 -bottom-48 size-[480px] rounded-full bg-orange-500/20 blur-3xl" />
        <Link to="/" className="relative">
          <Logo light />
        </Link>
        <div className="relative max-w-lg">
          <h2 className="text-4xl leading-[1.15] font-bold tracking-tight text-white xl:text-[44px]">{config.app.tagline || 'Always know where every project stands.'}</h2>
          <ul className="mt-8 space-y-4">
            {POINTS.map((p) => (
              <li key={p} className="flex items-center gap-3 text-[15px] font-medium text-brand-100">
                <CircleCheck className="size-5 shrink-0 text-brand-300" /> {p}
              </li>
            ))}
          </ul>
        </div>
        <p className="relative text-xs font-medium text-brand-300">© {YEAR} {config.app.name}. All rights reserved.</p>
      </div>

      <div className="flex flex-col px-4 py-6 sm:px-10 lg:px-14 xl:px-20">
        <div className="mb-8 flex items-center justify-between lg:mb-0 lg:justify-end">
          <Link to="/" className="lg:hidden">
            <Logo />
          </Link>
          <ThemeToggle />
        </div>
        <div className="mx-auto flex w-full max-w-[420px] flex-1 flex-col justify-center py-4">
          <h1 className="text-[26px] font-bold tracking-tight text-ink-900 sm:text-3xl">{title}</h1>
          <p className="mt-2 text-sm text-ink-500">{subtitle}</p>
          <div className="mt-8">{children}</div>
          <div className="mt-8 text-center text-sm text-ink-500">{footer}</div>
        </div>
      </div>
    </div>
  )
}
