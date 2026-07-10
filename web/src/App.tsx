import { useEffect } from 'react'
import { BrowserRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AppRoutes } from './routes/AppRoutes'
import { AuthNavigationBridge } from './routes/AuthNavigationBridge'

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
      <BrowserRouter>
        <AuthNavigationBridge />
        <AppRoutes />
      </BrowserRouter>
    </QueryClientProvider>
  )
}

export default App
