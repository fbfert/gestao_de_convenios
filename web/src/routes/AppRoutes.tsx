import { Navigate, Route, Routes } from 'react-router-dom'
import { LoginPage } from './LoginPage'
import { ProtectedRoute } from './ProtectedRoute'
import { ShellLayout } from './ShellLayout'
import { SolicitacoesPage } from '../features/solicitacoes/SolicitacoesPage'
import { GuiasPage } from '../features/guias/GuiasPage'
import { AntecipacoesPage } from '../features/antecipacoes/AntecipacoesPage'
import { LancamentosPage } from '../features/lancamentos'
import { ConciliacaoPage } from '../features/conciliacao'
import { MedicosPage } from '../features/medicos'
import { PermissoesPage } from '../features/permissoes'
import { UsuariosPage } from '../features/usuarios'

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
          <Route path="/conciliacao" element={<ConciliacaoPage />} />
          <Route path="/medicos" element={<MedicosPage />} />
          <Route path="/permissoes" element={<PermissoesPage />} />
          <Route path="/usuarios" element={<UsuariosPage />} />
        </Route>
      </Route>
      <Route path="*" element={<Navigate to="/solicitacoes" replace />} />
    </Routes>
  )
}
