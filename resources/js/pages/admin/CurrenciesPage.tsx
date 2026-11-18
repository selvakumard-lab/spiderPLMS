import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Coins, Plus, Trash2 } from 'lucide-react'
import { useToast } from '@/context/ToastContext'
import { errorMessage, fieldErrors } from '@/lib/api'
import { adminApi } from '@/lib/endpoints'
import { formatMoney } from '@/lib/utils'
import { Alert, Badge, Button, Card, EmptyState, Field, Input, Modal, PageHeader, Skeleton } from '@/components/ui'
import { ConfirmButton, Switch } from '@/components/ui/extra'
import type { Currency } from '@/types'

export default function CurrenciesPage() {
  const toast = useToast()
  const queryClient = useQueryClient()
  const [adding, setAdding] = useState(false)
  const [rates, setRates] = useState<Record<number, string>>({})
  const { data, isLoading, error } = useQuery({ queryKey: ['admin', 'currencies'], queryFn: adminApi.currencies })

  const refresh = () => {
    queryClient.invalidateQueries({ queryKey: ['admin'] })
    queryClient.invalidateQueries({ queryKey: ['public-config'] })
    queryClient.invalidateQueries({ queryKey: ['pricing'] })
  }
  const update = useMutation({
    mutationFn: ({ id, body }: { id: number; body: Record<string, unknown> }) => adminApi.updateCurrency(id, body),
    onSuccess: () => { refresh(); toast.success('Currency updated.') },
    onError: (e) => toast.error(errorMessage(e)),
  })
  const remove = useMutation({ mutationFn: (id: number) => adminApi.deleteCurrency(id), onSuccess: () => { refresh(); toast.success('Currency deleted.') } })

  const commitRate = (c: Currency) => {
    const value = rates[c.id!]
    if (value === undefined || Number(value) === c.rate) return
    update.mutate({ id: c.id!, body: { rate: Number(value) } })
  }

  return (
    <>
      <PageHeader eyebrow="Platform" title="Currencies" description="Exchange rates are units of each currency per 1 USD. Plan prices are stored in USD and converted with these rates for each visitor's country."
        actions={<Button icon={<Plus className="size-4" />} onClick={() => setAdding(true)}>Add currency</Button>} />
      <div className="mb-5"><Alert tone="info">The rates that ship with the template are placeholders — set the real rates here before going live.</Alert></div>
      {error && <Alert>{errorMessage(error)}</Alert>}
      <Card className="overflow-hidden">
        {isLoading ? <div className="space-y-2 p-4">{Array.from({ length: 6 }).map((_, i) => <Skeleton key={i} className="h-12" />)}</div> : !data?.length ? <EmptyState icon={<Coins className="size-6" />} title="No currencies" /> : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[820px] text-left text-[13px]">
              <thead className="border-b border-line bg-slate-50/70 text-xs font-bold tracking-wide text-ink-500 uppercase"><tr><th className="px-6 py-3">Currency</th><th className="px-4 py-3">Symbol</th><th className="px-4 py-3">Rate (per 1 USD)</th><th className="px-4 py-3">Example · Growth plan</th><th className="px-4 py-3 text-right">Countries</th><th className="px-4 py-3 text-center">Enabled</th><th className="px-6 py-3" /></tr></thead>
              <tbody className="divide-y divide-line">
                {data.map((c) => {
                  const base = c.code === 'USD'
                  const sample = c.rate >= 50 ? Math.round((49 * c.rate) / 10) * 10 : Math.max(1, Math.round(49 * c.rate))
                  return (
                    <tr key={c.id}>
                      <td className="px-6 py-3"><div className="flex items-center gap-2"><span className="font-bold">{c.code}</span><span className="text-ink-500">{c.name}</span>{base && <Badge className="bg-slate-100 text-slate-600 ring-slate-200">Base</Badge>}{c.is_default && <Badge className="bg-brand-50 text-brand-700 ring-brand-200">Default</Badge>}</div></td>
                      <td className="px-4 py-3 font-semibold">{c.symbol}</td>
                      <td className="px-4 py-3"><Input aria-label={`Rate for ${c.code}`} type="number" min={0} step="any" disabled={base} value={rates[c.id!] ?? String(c.rate)} onChange={(e) => setRates({ ...rates, [c.id!]: e.target.value })} onBlur={() => commitRate(c)} onKeyDown={(e) => e.key === 'Enter' && (e.target as HTMLInputElement).blur()} className="h-9 w-36 py-0 text-[13px]" /></td>
                      <td className="tabular px-4 py-3 text-ink-700">{formatMoney(sample, c.code)} / mo</td>
                      <td className="tabular px-4 py-3 text-right">{c.countries_count ?? 0}</td>
                      <td className="px-4 py-3 text-center"><Switch checked={!!c.is_active} label={`Enable ${c.code}`} disabled={c.is_default} onChange={(v) => update.mutate({ id: c.id!, body: { is_active: v } })} /></td>
                      <td className="px-6 py-3 text-right">{!base && !c.is_default && <ConfirmButton danger title="Delete currency" message={`Delete ${c.code}? Currencies in use must be disabled instead.`} confirmLabel="Delete" onConfirm={() => remove.mutateAsync(c.id!).catch((e) => { throw new Error(errorMessage(e)) })} trigger={(o) => <button onClick={o} className="rounded-lg p-2 text-ink-400 hover:bg-rose-50 hover:text-rose-600" aria-label={`Delete ${c.code}`}><Trash2 className="size-4" /></button>} />}</td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        )}
      </Card>
      {adding && <AddCurrencyModal onClose={() => setAdding(false)} onSaved={() => { setAdding(false); refresh(); toast.success('Currency added.') }} />}
    </>
  )
}

function AddCurrencyModal({ onClose, onSaved }: { onClose: () => void; onSaved: () => void }) {
  const [form, setForm] = useState({ code: '', name: '', symbol: '', rate: '1', decimals: '2' })
  const [errors, setErrors] = useState<Record<string, string>>({})
  const save = useMutation({
    mutationFn: () => adminApi.createCurrency({ ...form, rate: Number(form.rate), decimals: Number(form.decimals), is_active: true }),
    onSuccess: onSaved,
    onError: (e) => setErrors(fieldErrors(e)),
  })
  return (
    <Modal open onClose={onClose} title="Add currency" footer={<><Button variant="secondary" onClick={onClose}>Cancel</Button><Button loading={save.isPending} onClick={() => { setErrors({}); save.mutate() }}>Add currency</Button></>}>
      <div className="grid gap-5 sm:grid-cols-2">
        <Field label="ISO code (3 letters)" htmlFor="nc-code" error={errors.code}><Input id="nc-code" maxLength={3} value={form.code} onChange={(e) => setForm({ ...form, code: e.target.value.toUpperCase() })} placeholder="QAR" autoFocus /></Field>
        <Field label="Name" htmlFor="nc-name" error={errors.name}><Input id="nc-name" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} placeholder="Qatari Riyal" /></Field>
        <Field label="Symbol" htmlFor="nc-sym" error={errors.symbol}><Input id="nc-sym" value={form.symbol} onChange={(e) => setForm({ ...form, symbol: e.target.value })} placeholder="QR" /></Field>
        <Field label="Decimals" htmlFor="nc-dec" error={errors.decimals}><Input id="nc-dec" type="number" min={0} max={4} value={form.decimals} onChange={(e) => setForm({ ...form, decimals: e.target.value })} /></Field>
        <div className="sm:col-span-2"><Field label="Rate (units per 1 USD)" htmlFor="nc-rate" error={errors.rate}><Input id="nc-rate" type="number" min={0} step="any" value={form.rate} onChange={(e) => setForm({ ...form, rate: e.target.value })} /></Field></div>
      </div>
    </Modal>
  )
}
