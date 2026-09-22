import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import './index.css'
import App from './App.tsx'
import { aplicarTema, useTemaStore } from './stores/temaStore.ts'
import { instalarCapturaDeErrosGlobais } from './lib/reportClientError.ts'

// Antes de montar a árvore: um erro durante a própria montagem também precisa
// ser relatado, e o ErrorBoundary lá dentro só pega o que acontece na
// renderização — não o que escapa de um listener ou de uma promessa.
instalarCapturaDeErrosGlobais()

aplicarTema(useTemaStore.getState().tema)

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <App />
  </StrictMode>,
)
