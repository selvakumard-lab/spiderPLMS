import { AuthLayout } from '@/components/layout/AuthLayout'
import { OtpLogin } from '@/components/OtpLogin'

/** Super Admin sign-in at /superAdmin, by e-mail one-time code like every other login. */
export default function AdminLoginPage() {
  return (
    <AuthLayout title="Platform sign in" subtitle="Super Admin access. We will email you a one-time code." footer={<span>Authorised personnel only.</span>}>
      <OtpLogin redirectTo="/app/admin" />
    </AuthLayout>
  )
}
