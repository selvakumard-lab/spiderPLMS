import { useEffect, useState } from 'react'
import { useMutation } from '@tanstack/react-query'
import { MailCheck, ShieldCheck } from 'lucide-react'
import { useAuth } from '@/context/AuthContext'
import { useToast } from '@/context/ToastContext'
import { errorMessage, fieldErrors } from '@/lib/api'
import { authApi } from '@/lib/endpoints'
import { ROLE_LABELS } from '@/lib/constants'
import { formatDateTime } from '@/lib/utils'
import { Avatar, Button, Card, Field, Input, PageHeader } from '@/components/ui'
import { ConfirmButton, ThemeSelector } from '@/components/ui/extra'

export default function ProfilePage() {
  const { user, tenant, setUser } = useAuth()
  const toast = useToast()
  const [profile, setProfile] = useState({ name: user?.name ?? '', job_title: user?.job_title ?? '', phone: user?.phone ?? '' })
  const [errors, setErrors] = useState<Record<string, string>>({})

  useEffect(() => {
    if (window.location.hash === '#appearance') document.getElementById('appearance')?.scrollIntoView({ behavior: 'smooth' })
  }, [])

  const saveProfile = useMutation({
    mutationFn: () => authApi.updateProfile(profile),
    onSuccess: (u) => { setUser(u); toast.success('Profile updated.') },
    onError: (e) => { setErrors(fieldErrors(e)); toast.error(errorMessage(e)) },
  })
  const signOutOthers = useMutation({
    mutationFn: () => authApi.signOutOtherDevices(),
    onSuccess: (count) => toast.success(count > 0 ? `Signed out of ${count} other ${count === 1 ? 'device' : 'devices'}.` : 'You are not signed in anywhere else.'),
    onError: (e) => toast.error(errorMessage(e)),
  })

  if (!user) return null

  const role = user.is_super_admin ? 'Super Admin' : user.roles?.[0] ? ROLE_LABELS[user.roles[0]] : 'Member'

  return (
    <>
      <PageHeader title="My profile" description="Your details, sign-in security and appearance." />
      <div className="mx-auto max-w-3xl space-y-6">
        <Card className="p-5 sm:p-6">
          <div className="mb-6 flex items-center gap-4">
            <Avatar name={user.name} size="lg" />
            <div className="min-w-0">
              <div className="truncate text-[16px] font-bold">{user.name}</div>
              <div className="truncate text-[13px] text-ink-500">{role}{tenant ? ` · ${tenant.name}` : ''}</div>
            </div>
          </div>
          <div className="grid gap-5 sm:grid-cols-2">
            <Field label="Full name" htmlFor="pf-name" error={errors.name}><Input id="pf-name" value={profile.name} onChange={(e) => setProfile({ ...profile, name: e.target.value })} /></Field>
            <Field label="Job title" htmlFor="pf-title" error={errors.job_title}><Input id="pf-title" value={profile.job_title} onChange={(e) => setProfile({ ...profile, job_title: e.target.value })} /></Field>
            <Field label="Phone" htmlFor="pf-phone" error={errors.phone}><Input id="pf-phone" value={profile.phone} onChange={(e) => setProfile({ ...profile, phone: e.target.value })} /></Field>
            <Field label="Email" htmlFor="pf-email" hint="Contact your admin to change your sign-in email."><Input id="pf-email" value={user.email} disabled /></Field>
          </div>
          <div className="mt-6 flex justify-end"><Button loading={saveProfile.isPending} onClick={() => { setErrors({}); saveProfile.mutate() }}>Save profile</Button></div>
        </Card>

        <Card className="p-5 sm:p-6">
          <h3 className="mb-1 text-[15px] font-bold">Sign-in and security</h3>
          <div className="mt-3 flex items-start gap-3 text-[13.5px] text-ink-700">
            <span className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600"><MailCheck className="size-4" /></span>
            <p>You sign in with a one-time code sent to <strong>{user.email}</strong>. There is no password to remember, change or lose.</p>
          </div>
          <dl className="mt-4 grid gap-3 text-[13px] sm:grid-cols-2">
            <div><dt className="font-semibold text-ink-500">Last sign-in</dt><dd className="mt-0.5 text-ink-900">{user.last_login_at ? formatDateTime(user.last_login_at) : 'This is your first sign-in'}</dd></div>
            <div><dt className="font-semibold text-ink-500">Member since</dt><dd className="mt-0.5 text-ink-900">{formatDateTime(user.created_at)}</dd></div>
          </dl>
          <div className="mt-5 flex flex-col gap-3 border-t border-line pt-5 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-[13px] text-ink-500">Using a shared or lost device? Sign out everywhere except here.</p>
            <ConfirmButton title="Sign out of other devices" message="Every other browser and device will be signed out. You stay signed in here." confirmLabel="Sign out others" onConfirm={() => signOutOthers.mutateAsync()}
              trigger={(open) => <Button variant="secondary" icon={<ShieldCheck className="size-4" />} loading={signOutOthers.isPending} onClick={open}>Sign out other devices</Button>} />
          </div>
        </Card>

        <Card className="p-5 sm:p-6" id="appearance">
          <h3 className="mb-1 text-[15px] font-bold">Appearance</h3>
          <p className="mb-4 text-[13px] text-ink-500">Choose light, dark, or follow your device. Saved on this device.</p>
          <ThemeSelector />
        </Card>
      </div>
    </>
  )
}
