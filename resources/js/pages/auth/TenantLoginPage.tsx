import { Link, useParams } from 'react-router'
import { useQuery } from '@tanstack/react-query'
import { AuthLayout } from '@/components/layout/AuthLayout'
import { OtpLogin } from '@/components/OtpLogin'
import { Spinner } from '@/components/ui'
import { authApi } from '@/lib/endpoints'
import NotFoundPage from '@/pages/NotFoundPage'

/** A tenant's own sign-in page at /{slug}. */
export default function TenantLoginPage() {
  const { slug = '' } = useParams()
  const { data, isLoading, isError } = useQuery({ queryKey: ['workspace', slug], queryFn: () => authApi.workspace(slug), retry: false })

  if (isLoading) return <div className="flex min-h-dvh items-center justify-center"><Spinner className="size-8" /></div>
  if (isError || !data) return <NotFoundPage />

  return (
    <AuthLayout
      title={`Sign in to ${data.name}`}
      subtitle="We will email you a one-time code. No password needed."
      footer={
        <>
          Not your workspace?{' '}
          <Link to="/login" className="font-semibold text-brand-600 hover:text-brand-700">
            Find yours
          </Link>
        </>
      }
    >
      <OtpLogin workspace={data.slug} />
    </AuthLayout>
  )
}
