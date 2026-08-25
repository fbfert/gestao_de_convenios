import { Navigate, Route, Routes } from 'react-router-dom'
import { LoginPage } from './LoginPage'
import { AutomacoesPage } from '../features/automacoes/AutomacoesPage'
import { AutomacoesConfiguracoesPage } from '../features/automacoes/AutomacoesConfiguracoesPage'
import { ProtectedRoute } from './ProtectedRoute'
import { ShellLayout } from './ShellLayout'
import { SolicitacoesPage } from '../features/solicitacoes/SolicitacoesPage'
import { LerPedidoMedicoPage } from '../features/solicitacoes/LerPedidoMedicoPage'
import { SolicitacaoEditarPage } from '../features/solicitacoes/SolicitacaoEditarPage'
import { PacientesPage } from '../features/pacientes'
import { GuiaDetalhePage, GuiaEditarPage, GuiasPage } from '../features/guias'
import { AntecipacoesPage } from '../features/antecipacoes/AntecipacoesPage'
import { AntecipacaoEditarPage } from '../features/antecipacoes/AntecipacaoEditarPage'
import { AnaliticosPage, AnaliticoDetalhePage } from '../features/analiticos'
import { LancamentosPage, LancamentoEditarPage } from '../features/lancamentos'
import { LancamentoTemplatesPage } from '../features/lancamentos/LancamentoTemplatesPage'
import { ImportarSessoesPage } from '../features/lancamentos/ImportarSessoesPage'
import { ConciliacaoPage } from '../features/conciliacao'
import { MedicosPage } from '../features/medicos'
import { EspecialidadesPage } from '../features/especialidades'
import { CidsPage } from '../features/cids'
import { PermissoesPage } from '../features/permissoes'
import { UsuariosPage } from '../features/usuarios'
import { ProfissionaisPage } from '../features/profissionais'
import { DashboardPage } from '../features/dashboard'
import { CadastrosPage, OperacaoConveniosPage } from '../features/grupos'
import { AuditoriaPage } from '../features/auditoria'
import {
  ConfiguracoesClinicaSyncPage,
  ConfiguracoesGeralPage,
  ConfiguracoesGlobaisPage,
  ConfiguracoesIaPage,
  ConfiguracoesPage,
  EmailTemplatesPage,
  PromptsOperacionaisPage,
} from '../features/configuracoes'
import { ConveniosPage, ConvenioDetalhePage } from '../features/convenios/ConveniosPage'
import { ConvenioAjudaPage } from '../features/convenios/ConvenioAjudaPage'
import { ManualPage } from '../features/manual'
import { TenantsPage } from '../features/tenants'

export function AppRoutes() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route element={<ProtectedRoute />}>
        <Route element={<ShellLayout />}>
          <Route path="/" element={<Navigate to="/dashboard" replace />} />
          <Route path="/dashboard" element={<DashboardPage />} />
          <Route path="/cadastros" element={<CadastrosPage />} />
          <Route path="/operacao-convenios" element={<OperacaoConveniosPage />} />
          <Route path="/auditoria" element={<AuditoriaPage />} />
          <Route path="/automacoes" element={<AutomacoesPage />} />
          <Route path="/automacoes/:id" element={<AutomacoesPage />} />
          <Route path="/automacoes/configuracoes" element={<AutomacoesConfiguracoesPage />} />
          <Route path="/solicitacoes" element={<SolicitacoesPage />} />
          <Route path="/solicitacoes/ler-pedido-medico" element={<LerPedidoMedicoPage />} />
          <Route path="/solicitacoes/nova" element={<SolicitacoesPage />} />
          <Route path="/solicitacoes/:id/editar" element={<SolicitacaoEditarPage />} />
          <Route path="/pacientes" element={<PacientesPage />} />
          <Route path="/pacientes/novo" element={<PacientesPage />} />
          <Route path="/pacientes/:id/editar" element={<PacientesPage />} />
          <Route path="/guias" element={<GuiasPage />} />
          <Route path="/guias/nova" element={<GuiasPage />} />
          <Route path="/guias/:id" element={<GuiaDetalhePage />} />
          <Route path="/guias/:id/editar" element={<GuiaEditarPage />} />
          <Route path="/convenios" element={<ConveniosPage />} />
          <Route path="/convenios/novo" element={<ConveniosPage />} />
          <Route path="/convenios/ajuda" element={<ConvenioAjudaPage />} />
          <Route path="/convenios/:id" element={<ConvenioDetalhePage />} />
          <Route path="/convenios/:id/editar" element={<ConveniosPage />} />
          <Route path="/antecipacoes" element={<AntecipacoesPage />} />
          <Route path="/antecipacoes/:id/editar" element={<AntecipacaoEditarPage />} />
          <Route path="/lancamentos/templates" element={<LancamentoTemplatesPage />} />
          <Route path="/lancamentos/templates/" element={<LancamentoTemplatesPage />} />
          <Route path="/lancamentos" element={<LancamentosPage />} />
          <Route path="/lancamentos/novo" element={<LancamentosPage />} />
          <Route path="/lancamentos/:id/editar" element={<LancamentoEditarPage />} />
          <Route path="/lancamentos/importar" element={<ImportarSessoesPage />} />
          <Route path="/analiticos" element={<AnaliticosPage />} />
          <Route path="/analiticos/:id" element={<AnaliticoDetalhePage />} />
          <Route path="/conciliacao" element={<ConciliacaoPage />} />
          <Route path="/profissionais" element={<ProfissionaisPage />} />
          <Route path="/profissionais/novo" element={<ProfissionaisPage />} />
          <Route path="/profissionais/:id/editar" element={<ProfissionaisPage />} />
          <Route path="/especialidades" element={<EspecialidadesPage />} />
          <Route path="/especialidades/nova" element={<EspecialidadesPage />} />
          <Route path="/especialidades/:id/editar" element={<EspecialidadesPage />} />
          <Route path="/cids" element={<CidsPage />} />
          <Route path="/cids/novo" element={<CidsPage />} />
          <Route path="/cids/:id/editar" element={<CidsPage />} />
          <Route path="/medicos" element={<MedicosPage />} />
          <Route path="/medicos/novo" element={<MedicosPage />} />
          <Route path="/medicos/:id/editar" element={<MedicosPage />} />
          <Route path="/permissoes" element={<PermissoesPage />} />
          <Route path="/permissoes/novo" element={<PermissoesPage />} />
          <Route path="/permissoes/:name/editar" element={<PermissoesPage />} />
          <Route path="/usuarios" element={<UsuariosPage />} />
          <Route path="/usuarios/novo" element={<UsuariosPage />} />
          <Route path="/usuarios/:id/editar" element={<UsuariosPage />} />
          <Route path="/configuracoes" element={<ConfiguracoesGeralPage />} />
          <Route path="/configuracoes/globais" element={<ConfiguracoesGlobaisPage />} />
          <Route path="/configuracoes/emails" element={<ConfiguracoesPage aba="emails" />} />
          <Route path="/configuracoes/ia" element={<ConfiguracoesIaPage />} />
          <Route path="/configuracoes/ia/prompts" element={<PromptsOperacionaisPage />} />
          <Route path="/configuracoes/ia/prompts/novo" element={<PromptsOperacionaisPage />} />
          <Route path="/configuracoes/ia/prompts/:id/editar" element={<PromptsOperacionaisPage />} />
          <Route path="/configuracoes/unimed" element={<ConfiguracoesPage aba="unimed" />} />
          <Route path="/configuracoes/clinica" element={<ConfiguracoesClinicaSyncPage />} />
          <Route path="/configuracoes/templates-emails" element={<EmailTemplatesPage />} />
          <Route path="/configuracoes/templates-emails/novo" element={<EmailTemplatesPage />} />
          <Route path="/configuracoes/templates-emails/:id/editar" element={<EmailTemplatesPage />} />
          <Route path="/manual" element={<ManualPage />} />
          <Route path="/clinicas" element={<TenantsPage />} />
        </Route>
      </Route>
      {/* Rota desconhecida cai na inicial, que agora e a Gestao de Convenios. */}
      <Route path="*" element={<Navigate to="/dashboard" replace />} />
    </Routes>
  )
}
