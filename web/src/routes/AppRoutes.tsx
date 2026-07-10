import { Navigate, Route, Routes } from 'react-router-dom'
import { LoginPage } from './LoginPage'
import { ProtectedRoute } from './ProtectedRoute'
import { ShellLayout } from './ShellLayout'
import { PlaceholderPage } from './PlaceholderPage'
import { SolicitacoesPage } from '../features/solicitacoes/SolicitacoesPage'
import { GuiasPage } from '../features/guias/GuiasPage'
import { AntecipacoesPage } from '../features/antecipacoes/AntecipacoesPage'
import { LancamentosPage } from '../features/lancamentos'

export function AppRoutes() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route element={<ProtectedRoute />}>
        <Route element={<ShellLayout />}>
          <Route path="/" element={<Navigate to="/solicitacoes" replace />} />
          <Route path="/solicitacoes" element={<SolicitacoesPage />} />
          <Route path="/guias" element={<GuiasPage />} />
          <Route path="/antecipacoes" element={<AntecipacoesPage />} />
          <Route path="/lancamentos" element={<LancamentosPage />} />
          <Route
            path="/conciliacao"
            element={
              <PlaceholderPage
                title="Conciliação"
                description="Tela reservada para o próximo bloco, já conectada ao menu."
              />
            }
          />
        </Route>
      </Route>
      <Route path="*" element={<Navigate to="/solicitacoes" replace />} />
    </Routes>
  )
}
