import { Navigate, Route, Routes } from 'react-router-dom'
import { LoginPage } from './LoginPage'
import { ProtectedRoute } from './ProtectedRoute'
import { ShellLayout } from './ShellLayout'
import { SolicitacoesPage } from '../features/solicitacoes/SolicitacoesPage'
import { LerPedidoMedicoPage } from '../features/solicitacoes/LerPedidoMedicoPage'
import { PacientesPage } from '../features/pacientes'
import { GuiaDetalhePage, GuiasPage } from '../features/guias'
import { AntecipacoesPage } from '../features/antecipacoes/AntecipacoesPage'
import { AnaliticosPage, AnaliticoDetalhePage } from '../features/analiticos'
import { LancamentosPage } from '../features/lancamentos'
import { LancamentoTemplatesPage } from '../features/lancamentos/LancamentoTemplatesPage'
import { ConciliacaoPage } from '../features/conciliacao'
import { MedicosPage } from '../features/medicos'
import { EspecialidadesPage } from '../features/especialidades'
import { PermissoesPage } from '../features/permissoes'
import { UsuariosPage } from '../features/usuarios'
import { ProfissionaisPage } from '../features/profissionais'
import { DashboardPage } from '../features/dashboard'
import { AuditoriaPage } from '../features/auditoria'
import { ConfiguracoesPage, EmailTemplatesPage } from '../features/configuracoes'
import { ConveniosPage, ConvenioDetalhePage } from '../features/convenios/ConveniosPage'
import { ConvenioAjudaPage } from '../features/convenios/ConvenioAjudaPage'
import { ManualPage } from '../features/manual'

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
          <Route path="/solicitacoes/ler-pedido-medico" element={<LerPedidoMedicoPage />} />
          <Route path="/solicitacoes/nova" element={<SolicitacoesPage />} />
          <Route path="/pacientes" element={<PacientesPage />} />
          <Route path="/pacientes/novo" element={<PacientesPage />} />
          <Route path="/guias" element={<GuiasPage />} />
          <Route path="/guias/nova" element={<GuiasPage />} />
          <Route path="/guias/:id" element={<GuiaDetalhePage />} />
          <Route path="/convenios" element={<ConveniosPage />} />
          <Route path="/convenios/novo" element={<ConveniosPage />} />
          <Route path="/convenios/ajuda" element={<ConvenioAjudaPage />} />
          <Route path="/convenios/:id" element={<ConvenioDetalhePage />} />
          <Route path="/antecipacoes" element={<AntecipacoesPage />} />
          <Route path="/lancamentos/templates" element={<LancamentoTemplatesPage />} />
          <Route path="/lancamentos/templates/" element={<LancamentoTemplatesPage />} />
          <Route path="/lancamentos" element={<LancamentosPage />} />
          <Route path="/lancamentos/novo" element={<LancamentosPage />} />
          <Route path="/analiticos" element={<AnaliticosPage />} />
          <Route path="/analiticos/:id" element={<AnaliticoDetalhePage />} />
          <Route path="/conciliacao" element={<ConciliacaoPage />} />
          <Route path="/profissionais" element={<ProfissionaisPage />} />
          <Route path="/profissionais/novo" element={<ProfissionaisPage />} />
          <Route path="/especialidades" element={<EspecialidadesPage />} />
          <Route path="/especialidades/nova" element={<EspecialidadesPage />} />
          <Route path="/medicos" element={<MedicosPage />} />
          <Route path="/medicos/novo" element={<MedicosPage />} />
          <Route path="/permissoes" element={<PermissoesPage />} />
          <Route path="/usuarios" element={<UsuariosPage />} />
          <Route path="/usuarios/novo" element={<UsuariosPage />} />
          <Route path="/configuracoes" element={<ConfiguracoesPage />} />
          <Route path="/configuracoes/templates-emails" element={<EmailTemplatesPage />} />
          <Route path="/manual" element={<ManualPage />} />
        </Route>
      </Route>
      <Route path="*" element={<Navigate to="/solicitacoes" replace />} />
    </Routes>
  )
}
