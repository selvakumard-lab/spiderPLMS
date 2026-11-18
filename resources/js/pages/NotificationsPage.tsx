import { Link } from 'react-router'
import { useAuth } from '@/context/AuthContext'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Bell, CheckCheck } from 'lucide-react'
import { Button, Card, EmptyState, PageHeader, Skeleton } from '@/components/ui'
import { NotificationIcon } from '@/components/notificationMeta'
import { notificationsApi } from '@/lib/endpoints'
import { formatDateTime } from '@/lib/utils'

/** Every update the team has sent about the client's projects, newest first. */
export default function NotificationsPage() {
  const { user } = useAuth()
  const base = user?.is_client ? '/app/portal' : '/app'
  const queryClient = useQueryClient()
  const { data, isLoading } = useQuery({ queryKey: ['notifications'], queryFn: notificationsApi.list })
  const markRead = useMutation({
    mutationFn: (id?: string) => notificationsApi.markRead(id),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['notifications'] }),
  })

  return (
    <>
      <PageHeader title="Updates" description="Everything that changed on your projects: steps, stages, documents, requests and replies."
        actions={data && data.unread > 0 ? <Button variant="secondary" icon={<CheckCheck className="size-4" />} loading={markRead.isPending} onClick={() => markRead.mutate(undefined)}>Mark all read</Button> : undefined} />
      <Card>
        {isLoading ? (
          <div className="space-y-3 p-5">{[0, 1, 2].map((i) => <Skeleton key={i} className="h-14" />)}</div>
        ) : !data?.data.length ? (
          <div className="p-6"><EmptyState icon={<Bell className="size-6" />} title="No updates yet" description="You will see an update here whenever the team makes progress on your project." /></div>
        ) : (
          <ul className="divide-y divide-line">
            {data.data.map((n) => (
              <li key={n.id}>
                <Link to={n.project_id ? `${base}/projects/${n.project_id}` : base} onClick={() => { if (!n.read) markRead.mutate(n.id) }}
                  className={`flex gap-3 px-4 py-4 hover:bg-slate-50 sm:px-5 ${n.read ? '' : 'bg-brand-50/40'}`}>
                  <NotificationIcon kind={n.kind} />
                  <span className="min-w-0 flex-1">
                    <span className="flex items-center gap-2 text-[14px] font-semibold text-ink-900">{n.title}{!n.read && <span className="size-2 rounded-full bg-brand-600" aria-label="Unread" />}</span>
                    {n.body && <span className="mt-0.5 block text-[13px] text-ink-700">{n.body}</span>}
                    <span className="mt-0.5 block text-[12px] text-ink-400">{n.project_name} · {formatDateTime(n.created_at)}</span>
                  </span>
                </Link>
              </li>
            ))}
          </ul>
        )}
      </Card>
    </>
  )
}
