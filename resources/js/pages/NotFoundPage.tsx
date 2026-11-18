import { Link } from 'react-router'

export default function NotFoundPage() {
  return (
    <div className="flex min-h-dvh flex-col items-center justify-center bg-canvas px-4 text-center">
      <div className="text-6xl font-extrabold text-brand-600">404</div>
      <h1 className="mt-4 text-xl font-bold">Page not found</h1>
      <p className="mt-2 text-sm text-ink-500">The page you are looking for does not exist or was moved.</p>
      <Link to="/" className="mt-6 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">Back home</Link>
    </div>
  )
}
