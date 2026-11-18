import { useState, type FormEvent } from 'react'
import { Link, useNavigate } from 'react-router'
import { AuthLayout } from '@/components/layout/AuthLayout'
import { Button, Field, Input } from '@/components/ui'

/** Entry point for people who do not have their workspace link: type the workspace name, continue to its page. */
export default function LoginPage() {
  const navigate = useNavigate()
  const [slug, setSlug] = useState('')

  function submit(e: FormEvent) {
    e.preventDefault()
    const clean = slug.trim().replace(/[^A-Za-z0-9]/g, '')
    if (clean) navigate(`/${clean}`)
  }

  return (
    <AuthLayout
      title="Sign in"
      subtitle="Enter your workspace name to continue."
      footer={
        <>
          New to Spider PLMS?{' '}
          <Link to="/register" className="font-semibold text-brand-600 hover:text-brand-700">
            Create a workspace
          </Link>
        </>
      }
    >
      <form onSubmit={submit} className="space-y-5">
        <Field label="Workspace" htmlFor="workspace" hint="This is the last part of your workspace link, e.g. acmeDigital in /acmeDigital.">
          <Input id="workspace" autoFocus value={slug} onChange={(e) => setSlug(e.target.value)} placeholder="yourCompany" autoComplete="organization" />
        </Field>
        <Button type="submit" size="lg" className="w-full" disabled={!slug.trim()}>
          Continue
        </Button>
      </form>
    </AuthLayout>
  )
}
