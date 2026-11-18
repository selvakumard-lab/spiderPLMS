import { useEffect, useRef, useState } from 'react'
import { Link } from 'react-router'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Bell, Volume2, VolumeX } from 'lucide-react'
import { useAuth } from '@/context/AuthContext'
import { useToast } from '@/context/ToastContext'
import { notificationsApi } from '@/lib/endpoints'
import { playAlertSound, soundPreference, unlockSound } from '@/lib/sound'
import { formatDateTime } from '@/lib/utils'
import { NotificationIcon } from './notificationMeta'

/** The bell for everyone in a workspace: checks for updates every 8 seconds, shows a badge, and plays a chime plus a toast when something new arrives. */
export function NotificationBell() {
  const { user } = useAuth()
  const toast = useToast()
  const queryClient = useQueryClient()
  const [open, setOpen] = useState(false)
  const [muted, setMuted] = useState(soundPreference.isMuted())
  const ref = useRef<HTMLDivElement>(null)
  const seen = useRef<string | null | undefined>(undefined)

  useEffect(() => unlockSound(), [])

  const { data } = useQuery({
    queryKey: ['notifications'],
    queryFn: notificationsApi.list,
    refetchInterval: 8_000,
    refetchIntervalInBackground: true,
    refetchOnWindowFocus: true,
  })
  const markRead = useMutation({
    mutationFn: (id?: string) => notificationsApi.markRead(id),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['notifications'] }),
  })

  // Anything newer than the last one we saw is new: chime and toast it (but not on the very first load).
  useEffect(() => {
    if (!data) return
    const newest = data.data[0]?.id ?? null
    if (seen.current !== undefined && newest !== seen.current) {
      const fresh = data.data.slice(0, data.data.findIndex((n) => n.id === seen.current) === -1 ? data.data.length : data.data.findIndex((n) => n.id === seen.current)).filter((n) => !n.read)
      if (fresh.length > 0) {
        playAlertSound()
        toast.success(fresh.length === 1 ? fresh[0].title : `${fresh.length} new updates. Latest: ${fresh[0].title}`)
      }
    }
    seen.current = newest
  }, [data, toast])

  useEffect(() => {
    const close = (e: MouseEvent) => ref.current && !ref.current.contains(e.target as Node) && setOpen(false)
    document.addEventListener('mousedown', close)
    return () => document.removeEventListener('mousedown', close)
  }, [])

  const unread = data?.unread ?? 0
  const projectLink = (id: number | null) => (id ? (user?.is_client ? `/app/portal/projects/${id}` : `/app/projects/${id}`) : '/app/notifications')

  function toggleSound() {
    const next = !muted
    setMuted(next)
    soundPreference.setMuted(next)
    if (!next) playAlertSound(true)
  }

  return (
    <div className="relative" ref={ref}>
      <button onClick={() => setOpen((v) => !v)} className="relative rounded-xl p-2 text-ink-700 hover:bg-slate-100" aria-label={`Notifications${unread ? `, ${unread} unread` : ''}`} aria-haspopup="menu" aria-expanded={open}>
        <Bell className="size-5" />
        {unread > 0 && <span className="absolute top-0.5 right-0.5 flex min-w-4 items-center justify-center rounded-full bg-rose-600 px-1 text-[10px] leading-4 font-bold text-white">{unread > 9 ? '9+' : unread}</span>}
      </button>
      {open && (
        <div className="absolute right-0 z-30 mt-2 w-[min(22rem,calc(100vw-2rem))] animate-fade-in overflow-hidden rounded-2xl border border-line bg-surface shadow-pop" role="menu">
          <div className="flex items-center justify-between gap-2 px-4 py-3">
            <span className="text-[13px] font-bold">Updates</span>
            <div className="flex items-center gap-3">
              {unread > 0 && <button onClick={() => markRead.mutate(undefined)} className="text-[12px] font-semibold text-brand-600 hover:text-brand-700">Mark all read</button>}
              <button onClick={toggleSound} className="rounded-md p-1 text-ink-500 hover:bg-slate-100" aria-label={muted ? 'Turn alert sound on' : 'Turn alert sound off'} title={muted ? 'Alert sound is off' : 'Alert sound is on'}>
                {muted ? <VolumeX className="size-4" /> : <Volume2 className="size-4" />}
              </button>
            </div>
          </div>
          <ul className="max-h-80 divide-y divide-line overflow-y-auto border-t border-line">
            {!data?.data.length && <li className="px-4 py-8 text-center text-[13px] text-ink-500">No updates yet.</li>}
            {data?.data.slice(0, 6).map((n) => (
              <li key={n.id}>
                <Link to={projectLink(n.project_id)} onClick={() => { setOpen(false); if (!n.read) markRead.mutate(n.id) }} className={`flex gap-3 px-4 py-3 hover:bg-slate-50 ${n.read ? '' : 'bg-brand-50/40'}`}>
                  <NotificationIcon kind={n.kind} />
                  <span className="min-w-0">
                    <span className="block text-[13px] font-semibold text-ink-900">{n.title}</span>
                    <span className="block truncate text-[12px] text-ink-500">{n.project_name} · {formatDateTime(n.created_at)}</span>
                  </span>
                </Link>
              </li>
            ))}
          </ul>
          <Link to="/app/notifications" onClick={() => setOpen(false)} className="block border-t border-line px-4 py-2.5 text-center text-[13px] font-semibold text-brand-600 hover:bg-slate-50">View all updates</Link>
        </div>
      )}
    </div>
  )
}
