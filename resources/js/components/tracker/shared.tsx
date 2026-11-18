import { useState } from 'react'
import { Download, FileSpreadsheet, FileText } from 'lucide-react'
import { Badge, Button } from '@/components/ui'
import { useToast } from '@/context/ToastContext'
import { errorMessage } from '@/lib/api'
import { trackerApi } from '@/lib/endpoints'
import { saveBlob } from '@/lib/utils'
import type { TrackingStatus } from '@/types/tracker'

export const STATUS_META: Record<TrackingStatus, { label: string; badge: string; bar: string }> = {
  on_time: { label: 'On Time', badge: 'bg-emerald-50 text-emerald-700 ring-emerald-200', bar: '#10b981' },
  slightly_delayed: { label: 'Slightly Delayed', badge: 'bg-amber-50 text-amber-700 ring-amber-200', bar: '#f59e0b' },
  delayed: { label: 'Delayed', badge: 'bg-orange-50 text-orange-700 ring-orange-200', bar: '#f97316' },
  significantly_delayed: { label: 'Significantly Delayed', badge: 'bg-rose-50 text-rose-700 ring-rose-200', bar: '#e11d48' },
  completed: { label: 'Completed', badge: 'bg-brand-50 text-brand-700 ring-brand-200', bar: '#4f46e5' },
}

export function TrackingBadge({ status }: { status: TrackingStatus }) {
  const meta = STATUS_META[status] ?? STATUS_META.on_time
  return <Badge className={meta.badge}>{meta.label}</Badge>
}

const REPORTS = [
  { type: 'progress' as const, label: 'Progress report' },
  { type: 'performance' as const, label: 'Performance & delay report' },
]

/** Download buttons for the two reports, in PDF and Excel. */
export function ReportButtons({ projectId, projectCode }: { projectId: number; projectCode: string }) {
  const toast = useToast()
  const [busy, setBusy] = useState<string | null>(null)

  async function download(type: 'progress' | 'performance', format: 'pdf' | 'xlsx') {
    const id = `${type}-${format}`
    setBusy(id)
    try {
      saveBlob(await trackerApi.report(projectId, type, format), `${projectCode}-${type}-report.${format}`)
    } catch (err) {
      toast.error(errorMessage(err, 'Could not generate the report.'))
    } finally {
      setBusy(null)
    }
  }

  return (
    <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap">
      {REPORTS.map((r) => (
        <div key={r.type} className="flex items-center gap-2 rounded-xl border border-line px-3 py-2">
          <Download className="size-4 text-ink-400" />
          <span className="mr-2 text-[13px] font-semibold text-ink-700">{r.label}</span>
          <Button size="sm" variant="secondary" icon={<FileText className="size-3.5" />} loading={busy === `${r.type}-pdf`} onClick={() => download(r.type, 'pdf')}>PDF</Button>
          <Button size="sm" variant="secondary" icon={<FileSpreadsheet className="size-3.5" />} loading={busy === `${r.type}-xlsx`} onClick={() => download(r.type, 'xlsx')}>Excel</Button>
        </div>
      ))}
    </div>
  )
}

export const todayISO = () => new Date().toISOString().slice(0, 10)
