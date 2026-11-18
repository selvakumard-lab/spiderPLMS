import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { RouterProvider } from 'react-router/dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import '@fontsource-variable/montserrat'
import '../css/app.css'
import { AuthProvider } from '@/context/AuthContext'
import { ConfigProvider, useConfig } from '@/context/ConfigContext'
import { ThemeProvider } from '@/context/ThemeContext'
import { ToastProvider } from '@/context/ToastContext'
import { router } from '@/router'

const queryClient = new QueryClient({
  defaultOptions: {
    queries: { staleTime: 30_000, retry: 1, refetchOnWindowFocus: false },
  },
})

/** Needs the platform config to know the default theme, so it sits inside ConfigProvider. */
function Providers() {
  const { config } = useConfig()
  return (
    <ThemeProvider platformDefault={config.app.default_theme}>
      <ToastProvider>
        <AuthProvider>
          <RouterProvider router={router} />
        </AuthProvider>
      </ToastProvider>
    </ThemeProvider>
  )
}

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <QueryClientProvider client={queryClient}>
      <ConfigProvider>
        <Providers />
      </ConfigProvider>
    </QueryClientProvider>
  </StrictMode>,
)
