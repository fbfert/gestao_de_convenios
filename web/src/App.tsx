import { useEffect } from 'react'
import { BrowserRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AppRoutes } from './routes/AppRoutes'
import { AppErrorBoundary } from './routes/AppErrorBoundary'
import { AuthNavigationBridge } from './routes/AuthNavigationBridge'
import { ConfirmDialogProvider } from './components/ui/ConfirmDialog'

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: false,
      refetchOnWindowFocus: false,
    },
    mutations: {
      retry: false,
    },
  },
})

function App() {
  useEffect(() => {
    document.title = 'Gestão de Convênios'
  }, [])

  return (
    <QueryClientProvider client={queryClient}>
      <ConfirmDialogProvider>
        <BrowserRouter>
          <AuthNavigationBridge />
          {/* Dentro do router para o fallback poder navegar — ver o
              componente. Fora dele, "Voltar ao início" só poderia trocar
              window.location, que recarrega a aplicação inteira. */}
          <AppErrorBoundary>
            <AppRoutes />
          </AppErrorBoundary>
        </BrowserRouter>
      </ConfirmDialogProvider>
    </QueryClientProvider>
  )
}

export default App
