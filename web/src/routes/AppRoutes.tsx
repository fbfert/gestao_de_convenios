import { Navigate, Route, Routes } from 'react-router-dom'
import { LoginPage } from './LoginPage'
import { ProtectedRoute } from './ProtectedRoute'
import { ShellLayout } from './ShellLayout'
import { SolicitacoesPage } from '../features/solicitacoes/SolicitacoesPage'
import { PacientesPage } from '../features/pacientes'
import { GuiaDetalhePage, GuiasPage } from '../features/guias'
import { AntecipacoesPage } from '../features/antecipacoes/AntecipacoesPage'
import { LancamentosPage } from '../features/lancamentos'
import { ConciliacaoPage } from '../features/conciliacao'
import { MedicosPage } from '../features/medicos'
import { PermissoesPage } from '../features/permissoes'
import { UsuariosPage } from '../features/usuarios'
import { ProfissionaisPage } from '../features/profissionais'
import { DashboardPage } from '../features/dashboard'
import { AuditoriaPage } from '../features/auditoria'
import { ConveniosPage, ConvenioDetalhePage } from '../features/convenios/ConveniosPage'
import { ConvenioAjudaPage } from '../features/convenios/ConvenioAjudaPage'

export function AppRoutes() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route element={<ProtectedRoute />}>
        <Route element={<ShellLayout />}>
          <Route path="/" element={<Navigate to="/dashboard" replace />} />
          <Route path="/dashboard" element={<DashboardPage />} />
          <Route path="/auditoria" element={<AuditoriaPage />} />
          <Route path="/solicitacoes" element={<SolicitacoesPage />} />
          <Route path="/pacientes" element={<PacientesPage />} />
          <Route path="/guias" element={<GuiasPage />} />
          <Route path="/guias/:id" element={<GuiaDetalhePage />} />
          <Route path="/convenios" element={<ConveniosPage />} />
          <Route path="/convenios/ajuda" element={<ConvenioAjudaPage />} />
          <Route path="/convenios/:id" element={<ConvenioDetalhePage />} />
          <Route path="/antecipacoes" element={<AntecipacoesPage />} />
          <Route path="/lancamentos" element={<LancamentosPage />} />
          <Route path="/conciliacao" element={<ConciliacaoPage />} />
          <Route path="/profissionais" element={<ProfissionaisPage />} />
          <Route path="/medicos" element={<MedicosPage />} />
          <Route path="/permissoes" element={<PermissoesPage />} />
          <Route path="/usuarios" element={<UsuariosPage />} />
        </Route>
      </Route>
      <Route path="*" element={<Navigate to="/solicitacoes" replace />} />
    </Routes>
  )
}
