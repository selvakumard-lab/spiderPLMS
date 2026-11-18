import { Link } from 'react-router'
import { useQuery } from '@tanstack/react-query'
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts'
import { ArrowUpRight, Plus } from 'lucide-react'
import { errorMessage } from '@/lib/api'
import { adminApi } from '@/lib/endpoints'
import { formatMoney } from '@/lib/utils'
import { Alert, Button, Card, CardHeader, CountryChip, PageHeader, Progress, Skeleton } from '@/components/ui'
import { StatTile } from '@/components/ui/extra'

export default function AdminOverviewPage() {
  const { data, isLoading, error } = useQuery({ queryKey: ['admin', 'stats'], queryFn: adminApi.stats })
  const maxCountry = Math.max(1, ...(data?.by_country.map((c) => c.tenants) ?? [1]))

  return (
    <>
      <PageHeader eyebrow="Platform" title="Platform overview" description="Tenants, revenue and reach across every country."
        actions={<Link to="/app/admin/tenants?new=1"><Button icon={<Plus className="size-4" />}>Add tenant</Button></Link>} />
      {error && <Alert>{errorMessage(error)}</Alert>}

      {isLoading || !data ? <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">{Array.from({ length: 4 }).map((_, i) => <Skeleton key={i} className="h-28 rounded-2xl" />)}</div> : (
        <>
          <div className="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
            <StatTile label="Tenants" value={data.totals.tenants} hint={`${data.totals.active} active · ${data.totals.trial} on trial`} />
            <StatTile label="Monthly recurring revenue" value={formatMoney(data.totals.mrr_usd_cents, 'USD', true)} hint="Active tenants, list price in USD" tone="success" />
            <StatTile label="Users" value={data.totals.users} />
            <StatTile label="Projects" value={data.totals.projects} hint={data.totals.suspended ? `${data.totals.suspended} suspended/cancelled tenants` : undefined} />
          </div>

          <div className="mt-6 grid gap-6 xl:grid-cols-3">
            <Card className="xl:col-span-2">
              <CardHeader title="New tenants" subtitle="Last 6 months" />
              <div className="h-[260px] px-2 pt-4 pb-3 sm:px-4">
                <ResponsiveContainer width="100%" height="100%">
                  <BarChart data={data.new_tenants} margin={{ top: 8, right: 8, left: -20, bottom: 0 }}>
                    <CartesianGrid vertical={false} stroke="var(--color-line)" />
                    <XAxis dataKey="month" tickLine={false} axisLine={false} tick={{ fill: 'var(--color-ink-400)', fontSize: 11, fontWeight: 600 }} />
                    <YAxis allowDecimals={false} tickLine={false} axisLine={false} tick={{ fill: 'var(--color-ink-400)', fontSize: 11 }} />
                    <Tooltip cursor={{ fill: 'var(--color-slate-100)' }} contentStyle={{ background: 'var(--color-surface)', border: '1px solid var(--color-line)', borderRadius: 12, color: 'var(--color-ink-900)', fontSize: 12 }} />
                    <Bar dataKey="tenants" name="New tenants" fill="var(--chart-brand)" radius={[4, 4, 0, 0]} maxBarSize={44} />
                  </BarChart>
                </ResponsiveContainer>
              </div>
            </Card>

            <Card>
              <CardHeader title="Tenants by plan" />
              <ul className="space-y-4 px-5 pt-4 pb-6 sm:px-6">
                {data.by_plan.map((p) => (
                  <li key={p.code}><div className="mb-1.5 flex justify-between text-[13px]"><span className="font-semibold text-ink-700">{p.name}</span><span className="tabular font-bold">{p.tenants}</span></div><Progress value={data.totals.tenants ? (p.tenants / data.totals.tenants) * 100 : 0} /></li>
                ))}
              </ul>
            </Card>
          </div>

          <Card className="mt-6">
            <CardHeader title="Tenants by country" subtitle="Where your customers are" action={<Link to="/app/admin/countries" className="inline-flex items-center gap-1 text-[13px] font-semibold text-brand-600 hover:text-brand-700">Manage countries <ArrowUpRight className="size-4" /></Link>} />
            <ul className="grid gap-x-10 gap-y-4 px-5 pt-5 pb-6 sm:px-6 md:grid-cols-2">
              {data.by_country.length === 0 && <li className="text-[13px] text-ink-500">No tenants yet.</li>}
              {data.by_country.map((c) => (
                <li key={c.code ?? 'unknown'}>
                  <div className="mb-1.5 flex justify-between text-[13px]"><span className="font-semibold text-ink-700"><CountryChip code={c.code} /> {c.name}</span><span className="tabular font-bold">{c.tenants}</span></div>
                  <Progress value={(c.tenants / maxCountry) * 100} />
                </li>
              ))}
            </ul>
          </Card>
        </>
      )}
    </>
  )
}
