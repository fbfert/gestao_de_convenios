<?php

use App\Http\Controllers\AlertaController;
use App\Http\Controllers\AlertaDestinatarioController;
use App\Http\Controllers\AntecipacaoController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AutomacaoController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailSettingsController;
use App\Http\Controllers\EmailTemplateController;
use App\Http\Controllers\ConvenioController;
use App\Http\Controllers\ConvenioEspecialidadeMapeamentoController;
use App\Http\Controllers\ConvenioProfissionalMapeamentoController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\CidController;
use App\Http\Controllers\EspecialidadeController;
use App\Http\Controllers\ConciliacaoController;
use App\Http\Controllers\ConciliacaoImportController;
use App\Http\Controllers\GuiaController;
use App\Http\Controllers\GuiaImportController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\MedicoController;
use App\Http\Controllers\LancamentoController;
use App\Http\Controllers\LancamentoImportController;
use App\Http\Controllers\LancamentoPrintTemplateController;
use App\Http\Controllers\AnaliticoController;
use App\Http\Controllers\AiPromptTemplateController;
use App\Http\Controllers\AiSettingsController;
use App\Http\Controllers\ClinicaSyncController;
use App\Http\Controllers\ConfiguracaoGlobalController;
use App\Http\Controllers\ManualController;
use App\Http\Controllers\NovidadeController;
use App\Http\Controllers\PacienteArquivoController;
use App\Http\Controllers\PacienteController;
use App\Http\Controllers\PacienteMergeController;
use App\Http\Controllers\PacienteImportController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SolicitacaoController;
use App\Http\Controllers\SolicitacaoImportController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\ProfissionalController;
use App\Http\Controllers\SaudeController;
use App\Http\Controllers\SolicitacaoDocumentoController;
use App\Http\Controllers\UnimedSettingsController;
use App\Support\AuthPayload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Publico de proposito: e lido por um monitor externo, que precisa alcancar a
// API sem credencial nenhuma. Fica fora do grupo `auth:sanctum` e nao devolve
// nada que identifique tenant, usuario ou volume de dados.
//
// O ResolveTenant do grupo `api` continua na frente desta rota, e tudo bem: sem
// usuario autenticado ele apenas limpa o contexto de tenant, sem tocar no banco.
// Sem throttle: o monitor bate a cada 2 minutos e um 429 seria lido como queda.
Route::get('/health', HealthController::class);

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

// EncerrarSessaoExpirada vem logo apos o auth: precisa do usuario resolvido
// para saber o prazo do tenant, e tem que barrar antes de qualquer rota.
Route::middleware(['auth:sanctum', \App\Http\Middleware\EncerrarSessaoExpirada::class])->group(function () {
    // Mesmo formato do bloco `user` do login, e nao o model cru: e por aqui
    // que o frontend redescobre, a cada abertura, que as permissoes do papel
    // mudaram desde o login.
    Route::get('/user', function (Request $request) {
        return response()->json(AuthPayload::paraUsuario($request->user()->load('tenant')));
    });

    Route::get('/dashboard', [DashboardController::class, 'index']);
    // Sem `permission:`: saber se o sistema esta funcionando nao e privilegio de
    // papel — o profissional que lanca sessao precisa disso tanto quanto o admin.
    Route::get('/saude', SaudeController::class);

    // Central de alertas. Ver e configurar são permissões separadas de
    // propósito: quem opera precisa ver o que exige ação, mas mexer em limiar
    // é decisão de administração.
    Route::get('/alertas', [AlertaController::class, 'index'])->middleware('permission:alertas.view');
    Route::post('/alertas/{alerta}/reconhecer', [AlertaController::class, 'reconhecer'])->middleware('permission:alertas.view');
    Route::post('/alertas/{alerta}/silenciar', [AlertaController::class, 'silenciar'])->middleware('permission:alertas.view');
    Route::get('/alertas/regras', [AlertaController::class, 'regras'])->middleware('permission:alertas.manage');
    Route::put('/alertas/regras/{alertaRegra}', [AlertaController::class, 'atualizarRegra'])->middleware('permission:alertas.manage');
    Route::get('/alertas/destinatarios', [AlertaDestinatarioController::class, 'index'])->middleware('permission:alertas.manage');
    Route::post('/alertas/destinatarios', [AlertaDestinatarioController::class, 'store'])->middleware('permission:alertas.manage');
    Route::put('/alertas/destinatarios/{alertaDestinatario}', [AlertaDestinatarioController::class, 'update'])->middleware('permission:alertas.manage');
    Route::delete('/alertas/destinatarios/{alertaDestinatario}', [AlertaDestinatarioController::class, 'destroy'])->middleware('permission:alertas.manage');
    Route::get('/auditoria', [AuditController::class, 'index'])->middleware('permission:dashboard.auditoria');
    Route::get('/auditoria/opcoes', [AuditController::class, 'opcoes'])->middleware('permission:dashboard.auditoria');
    Route::get('/auditoria/exportar', [AuditController::class, 'exportar'])->middleware('permission:dashboard.auditoria');
    Route::get('/automacoes', [AutomacaoController::class, 'index'])->middleware('permission:guias.view');
    Route::get('/automacoes/{automacaoExecucao}', [AutomacaoController::class, 'show'])->middleware('permission:guias.view');
    Route::post('/automacoes/{automacaoExecucao}/reprocessar', [AutomacaoController::class, 'reprocessar'])->middleware('permission:guias.view');

    // Somente leitura: o manual virou conteúdo do produto, servido do
    // repositório. O PUT saiu junto com a permissão `manual.manage`.
    Route::get('/manual/{tipo?}', [ManualController::class, 'show'])->where('tipo', 'manual|mapa-mental');

    Route::get('/novidades', [NovidadeController::class, 'index']);
    Route::post('/novidades/{slug}/lida', [NovidadeController::class, 'marcarLida']);
    Route::get('/configuracoes/emails', [EmailSettingsController::class, 'show'])->middleware('permission:configuracoes.manage');
    Route::put('/configuracoes/emails', [EmailSettingsController::class, 'update'])->middleware('permission:configuracoes.manage');
    Route::post('/configuracoes/emails/teste', [EmailSettingsController::class, 'enviarTeste'])->middleware('permission:configuracoes.manage');
    Route::get('/configuracoes/emails/templates', [EmailTemplateController::class, 'index'])->middleware('permission:configuracoes.manage');
    Route::post('/configuracoes/emails/templates', [EmailTemplateController::class, 'store'])->middleware('permission:configuracoes.manage');
    Route::put('/configuracoes/emails/templates/{emailTemplate}', [EmailTemplateController::class, 'update'])->middleware('permission:configuracoes.manage');
    Route::delete('/configuracoes/emails/templates/{emailTemplate}', [EmailTemplateController::class, 'destroy'])->middleware('permission:configuracoes.manage');
    Route::get('/configuracoes/globais', [ConfiguracaoGlobalController::class, 'show'])->middleware('permission:configuracoes.manage');
    Route::put('/configuracoes/globais', [ConfiguracaoGlobalController::class, 'update'])->middleware('permission:configuracoes.manage');
    Route::get('/configuracoes/ia', [AiSettingsController::class, 'show'])->middleware('permission:configuracoes.manage');
    Route::put('/configuracoes/ia', [AiSettingsController::class, 'update'])->middleware('permission:configuracoes.manage');
    Route::get('/configuracoes/ia/modelos', [AiSettingsController::class, 'models'])->middleware('permission:configuracoes.manage');
    Route::get('/configuracoes/ia/prompts', [AiPromptTemplateController::class, 'index'])->middleware('permission:configuracoes.manage');
    Route::post('/configuracoes/ia/prompts', [AiPromptTemplateController::class, 'store'])->middleware('permission:configuracoes.manage');
    Route::put('/configuracoes/ia/prompts/{aiPromptTemplate}', [AiPromptTemplateController::class, 'update'])->middleware('permission:configuracoes.manage');
    Route::delete('/configuracoes/ia/prompts/{aiPromptTemplate}', [AiPromptTemplateController::class, 'destroy'])->middleware('permission:configuracoes.manage');
    Route::get('/configuracoes/clinica-sync', [ClinicaSyncController::class, 'show'])->middleware('permission:configuracoes.clinica.manage');
    Route::post('/configuracoes/clinica-sync/sincronizar', [ClinicaSyncController::class, 'sincronizar'])->middleware('permission:configuracoes.clinica.manage');
    Route::get('/configuracoes/clinica-sync/pendencias', [ClinicaSyncController::class, 'pendencias'])->middleware('permission:configuracoes.clinica.manage');
    Route::post('/configuracoes/clinica-sync/pendencias/{pendencia}/confirmar', [ClinicaSyncController::class, 'confirmarPendencia'])->middleware('permission:configuracoes.clinica.manage');
    Route::post('/configuracoes/clinica-sync/pendencias/{pendencia}/rejeitar', [ClinicaSyncController::class, 'rejeitarPendencia'])->middleware('permission:configuracoes.clinica.manage');
    Route::get('/configuracoes/clinica-sync/push-pendencias', [ClinicaSyncController::class, 'pushPendencias'])->middleware('permission:configuracoes.clinica.manage');
    Route::post('/configuracoes/clinica-sync/push-pendencias/{pendencia}/confirmar', [ClinicaSyncController::class, 'confirmarPushPendencia'])->middleware('permission:configuracoes.clinica.manage');
    Route::post('/configuracoes/clinica-sync/push-pendencias/{pendencia}/rejeitar', [ClinicaSyncController::class, 'rejeitarPushPendencia'])->middleware('permission:configuracoes.clinica.manage');
    Route::get('/configuracoes/unimed', [UnimedSettingsController::class, 'show'])->middleware('permission:configuracoes.unimed.manage');
    Route::put('/configuracoes/unimed', [UnimedSettingsController::class, 'update'])->middleware('permission:configuracoes.unimed.manage');
    Route::get('/configuracoes/unimed/worker-health', [UnimedSettingsController::class, 'health'])->middleware('permission:configuracoes.unimed.manage');
    Route::post('/configuracoes/unimed/reativar', [UnimedSettingsController::class, 'reativar'])->middleware('permission:configuracoes.unimed.manage');
    Route::get('/configuracoes/unimed/mapeamentos/especialidades', [ConvenioEspecialidadeMapeamentoController::class, 'index'])->middleware('permission:configuracoes.unimed.manage');
    Route::post('/configuracoes/unimed/mapeamentos/especialidades', [ConvenioEspecialidadeMapeamentoController::class, 'store'])->middleware('permission:configuracoes.unimed.manage');
    Route::patch('/configuracoes/unimed/mapeamentos/especialidades/{especialidadeMapeamento}', [ConvenioEspecialidadeMapeamentoController::class, 'update'])->middleware('permission:configuracoes.unimed.manage');
    Route::get('/configuracoes/unimed/mapeamentos/profissionais', [ConvenioProfissionalMapeamentoController::class, 'index'])->middleware('permission:configuracoes.unimed.manage');
    Route::post('/configuracoes/unimed/mapeamentos/profissionais', [ConvenioProfissionalMapeamentoController::class, 'store'])->middleware('permission:configuracoes.unimed.manage');
    Route::patch('/configuracoes/unimed/mapeamentos/profissionais/{profissionalMapeamento}', [ConvenioProfissionalMapeamentoController::class, 'update'])->middleware('permission:configuracoes.unimed.manage');

    Route::get('/pacientes', [PacienteController::class, 'index'])->middleware('permission:dashboard.pacientes');
    // Antes da rota com {paciente}: sem isso "ler-carteirinha", "importar" e
    // "recentes" seriam lidos como id de paciente.
    Route::get('/pacientes/recentes', [PacienteController::class, 'recentes'])->middleware('permission:dashboard.pacientes');
    Route::post('/pacientes/ler-carteirinha', [PacienteController::class, 'lerCarteirinha'])->middleware('permission:dashboard.pacientes');
    Route::get('/pacientes/importar/template', [PacienteImportController::class, 'template'])
        ->middleware('permission:dashboard.pacientes');
    Route::post('/pacientes/importar', [PacienteImportController::class, 'previsualizar'])
        ->middleware('permission:dashboard.pacientes');
    Route::post('/pacientes/importar/{paciente_import_lote}/confirmar', [PacienteImportController::class, 'confirmar'])
        ->middleware('permission:dashboard.pacientes');
    Route::get('/pacientes/duplicados', [PacienteMergeController::class, 'duplicados'])
        ->middleware('permission:dashboard.pacientes');
    Route::post('/pacientes/duplicados/preview', [PacienteMergeController::class, 'preview'])
        ->middleware('permission:dashboard.pacientes');
    Route::post('/pacientes/duplicados/mesclar', [PacienteMergeController::class, 'mesclar'])
        ->middleware('permission:dashboard.pacientes');
    Route::get('/pacientes/{paciente}', [PacienteController::class, 'show'])->middleware('permission:dashboard.pacientes');
    Route::post('/pacientes', [PacienteController::class, 'store'])->middleware('permission:dashboard.pacientes');
    Route::patch('/pacientes/{paciente}', [PacienteController::class, 'update'])->middleware('permission:dashboard.pacientes');
    Route::get('/pacientes/{paciente}/arquivos', [PacienteArquivoController::class, 'index'])->middleware('permission:dashboard.pacientes');
    Route::post('/pacientes/{paciente}/arquivos', [PacienteArquivoController::class, 'store'])->middleware('permission:dashboard.pacientes');
    Route::get('/pacientes/{paciente}/arquivos/{arquivo}', [PacienteArquivoController::class, 'download'])->middleware('permission:dashboard.pacientes');
    Route::get('/pacientes/{paciente}/arquivos/{arquivo}/contexto', [PacienteArquivoController::class, 'contexto'])->middleware('permission:dashboard.pacientes');
    Route::delete('/pacientes/{paciente}/arquivos/{arquivo}', [PacienteArquivoController::class, 'destroy'])->middleware('permission:dashboard.pacientes');
    Route::get('/profissionais', [ProfissionalController::class, 'index']);
    Route::post('/profissionais', [ProfissionalController::class, 'store'])->middleware('permission:profissionais.manage');
    Route::patch('/profissionais/{profissional}', [ProfissionalController::class, 'update'])->middleware('permission:profissionais.manage');
    Route::get('/especialidades', [EspecialidadeController::class, 'index']);
    Route::post('/especialidades', [EspecialidadeController::class, 'store'])->middleware('permission:especialidades.manage');
    Route::patch('/especialidades/{especialidade}', [EspecialidadeController::class, 'update'])->middleware('permission:especialidades.manage');
    Route::get('/cids', [CidController::class, 'index']);
    Route::post('/cids', [CidController::class, 'store'])->middleware('permission:solicitacoes.view');
    Route::patch('/cids/{cid}', [CidController::class, 'update'])->middleware('permission:solicitacoes.view');
    Route::get('/convenios', [ConvenioController::class, 'index']);
    Route::get('/convenios/{convenio}', [ConvenioController::class, 'show']);
    Route::post('/convenios', [ConvenioController::class, 'store'])->middleware('permission:convenios.manage');
    Route::patch('/convenios/{convenio}', [ConvenioController::class, 'update'])->middleware('permission:convenios.manage');
    Route::get('/convenios/{convenio}/regras', [ConvenioController::class, 'regras'])->middleware('permission:convenios.manage');
    Route::post('/convenios/{convenio}/regras', [ConvenioController::class, 'storeRegra'])->middleware('permission:convenios.manage');
    Route::patch('/convenios/{convenio}/regras/{regra}/encerrar', [ConvenioController::class, 'encerrarRegra'])->middleware('permission:convenios.manage');
    Route::get('/convenios/{convenio}/valores', [ConvenioController::class, 'valores'])->middleware('permission:convenios.manage');
    Route::post('/convenios/{convenio}/valores', [ConvenioController::class, 'storeValor'])->middleware('permission:convenios.manage');
    Route::patch('/convenios/{convenio}/valores/{valor}/encerrar', [ConvenioController::class, 'encerrarValor'])->middleware('permission:convenios.manage');
    Route::get('/medicos', [MedicoController::class, 'index'])->middleware('permission:medicos.view|medicos.manage');
    Route::get('/medicos/recentes', [MedicoController::class, 'recentes'])->middleware('permission:medicos.view|medicos.manage');
    Route::post('/medicos', [MedicoController::class, 'store'])->middleware('permission:medicos.manage');
    Route::patch('/medicos/{medico}', [MedicoController::class, 'update'])->middleware('permission:medicos.manage');

    Route::get('/roles', [RoleController::class, 'index'])->middleware('permission:permissoes.manage');
    Route::post('/roles', [RoleController::class, 'store'])->middleware('permission:permissoes.manage');
    Route::patch('/roles/{role}', [RoleController::class, 'update'])->middleware('permission:permissoes.manage');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:permissoes.manage');
    Route::get('/permissions', [PermissionController::class, 'index'])->middleware('permission:permissoes.manage');
    Route::get('/roles/{role}/permissions', [RolePermissionController::class, 'show'])->middleware('permission:permissoes.manage');
    Route::put('/roles/{role}/permissions', [RolePermissionController::class, 'update'])->middleware('permission:permissoes.manage');

    // Gestão de clínicas. `super-admin` em vez de `permission:`: a capacidade
    // fica fora do PermissionCatalog para que o admin de um tenant não possa
    // conceder a si mesmo (ver migration 2026_08_12_180000).
    Route::get('/tenants', [TenantController::class, 'index'])->middleware('super-admin');
    Route::post('/tenants', [TenantController::class, 'store'])->middleware('super-admin');
    Route::put('/tenants/{tenant}', [TenantController::class, 'update'])->middleware('super-admin');
    Route::post('/tenants/{tenant}/acessar', [TenantController::class, 'acessar'])->middleware('super-admin');

    Route::get('/usuarios', [UserController::class, 'index'])->middleware('permission:usuarios.manage');
    Route::get('/usuarios/{usuario}', [UserController::class, 'show'])->middleware('permission:usuarios.manage');
    Route::post('/usuarios', [UserController::class, 'store'])->middleware('permission:usuarios.manage');
    Route::patch('/usuarios/{usuario}', [UserController::class, 'update'])->middleware('permission:usuarios.manage');

    Route::get('/solicitacoes', [SolicitacaoController::class, 'index'])->middleware('permission:solicitacoes.view');
    Route::post('/solicitacoes', [SolicitacaoController::class, 'store'])->middleware('permission:solicitacoes.manage');
    Route::get('/solicitacoes/importar/template', [SolicitacaoImportController::class, 'template'])
        ->middleware('permission:solicitacoes.manage');
    Route::post('/solicitacoes/importar', [SolicitacaoImportController::class, 'previsualizar'])
        ->middleware('permission:solicitacoes.manage');
    Route::post('/solicitacoes/importar/{solicitacao_import_lote}/confirmar', [SolicitacaoImportController::class, 'confirmar'])
        ->middleware('permission:solicitacoes.manage');
    Route::post('/solicitacoes/ler-pedido-medico', [SolicitacaoController::class, 'analisarPedidoMedico'])->middleware('permission:solicitacoes.view');
    Route::post('/solicitacoes/pacientes-rapido', [SolicitacaoController::class, 'storePacienteRapido'])->middleware('permission:solicitacoes.view');
    Route::post('/solicitacoes/especialidades-rapido', [SolicitacaoController::class, 'storeEspecialidadeRapida'])->middleware('permission:solicitacoes.view');
    Route::post('/solicitacoes/medicos-rapido', [SolicitacaoController::class, 'storeMedicoRapido'])->middleware('permission:solicitacoes.view');
    Route::post('/solicitacoes/cids-rapido', [SolicitacaoController::class, 'storeCidRapido'])->middleware('permission:solicitacoes.view');
    Route::get('/solicitacoes/{solicitacao}', [SolicitacaoController::class, 'show'])->middleware('permission:solicitacoes.view');
    Route::patch('/solicitacoes/{solicitacao}', [SolicitacaoController::class, 'update'])->middleware('permission:solicitacoes.manage');
    Route::post('/solicitacoes/{solicitacao}/documentos', [SolicitacaoDocumentoController::class, 'store'])->middleware('permission:solicitacoes.manage');
    Route::post('/solicitacoes/{solicitacao}/documentos/vincular', [SolicitacaoDocumentoController::class, 'vincular'])->middleware('permission:solicitacoes.manage');
    Route::get('/solicitacoes/{solicitacao}/documentos/{documento}', [SolicitacaoDocumentoController::class, 'download'])->middleware('permission:solicitacoes.view');
    Route::delete('/solicitacoes/{solicitacao}/documentos/{documento}', [SolicitacaoDocumentoController::class, 'destroy'])->middleware('permission:solicitacoes.manage');
    // Antes das rotas de {solicitacao}/status por clareza de leitura, não por
    // precedência: os segmentos são literais distintos e não competem.
    Route::get('/solicitacoes/{solicitacao}/contexto-adicao', [SolicitacaoController::class, 'contextoAdicao'])
        ->middleware('permission:solicitacoes.manage');
    Route::post('/solicitacoes/{solicitacao}/itens', [SolicitacaoController::class, 'storeItem'])
        ->middleware('permission:solicitacoes.manage');
    Route::delete('/solicitacoes/{solicitacao}/itens/{item}', [SolicitacaoController::class, 'destroyItem'])
        ->middleware('permission:solicitacoes.manage');
    Route::patch('/solicitacoes/{solicitacao}/status', [SolicitacaoController::class, 'updateStatus'])->middleware('permission:solicitacoes.manage');
    Route::patch('/solicitacoes/{solicitacao}/aprovar', [SolicitacaoController::class, 'aprovar'])->middleware('permission:solicitacoes.manage');
    Route::patch('/solicitacoes/{solicitacao}/negar', [SolicitacaoController::class, 'negar'])->middleware('permission:solicitacoes.manage');
    Route::post('/solicitacao-itens/{solicitacaoItem}/enviar-unimed', [SolicitacaoController::class, 'enviarItemUnimed'])->middleware('permission:solicitacoes.view');
    Route::post('/solicitacao-itens/{solicitacaoItem}/verificar-andamento', [SolicitacaoController::class, 'verificarAndamentoItem'])->middleware('permission:solicitacoes.view');

    Route::get('/antecipacoes/elegiveis', [AntecipacaoController::class, 'elegiveis'])
        ->middleware('permission:antecipacoes.view');
    Route::get('/antecipacoes', [AntecipacaoController::class, 'index'])
        ->middleware('permission:antecipacoes.view');
    Route::post('/antecipacoes', [AntecipacaoController::class, 'store'])
        ->middleware('permission:antecipacoes.manage');
    Route::post('/antecipacoes/ignorar', [AntecipacaoController::class, 'ignorar'])
        ->middleware('permission:antecipacoes.manage');
    Route::patch('/antecipacoes/{antecipacao}', [AntecipacaoController::class, 'update'])
        ->middleware('permission:antecipacoes.manage');
    Route::delete('/antecipacoes/{antecipacao}', [AntecipacaoController::class, 'destroy'])
        ->middleware('permission:antecipacoes.manage');

    Route::get('/guias', [GuiaController::class, 'index']);
    Route::post('/guias', [GuiaController::class, 'store'])->middleware('permission:guias.view|guias.viewOwn');
    Route::get('/guias/importar/template', [GuiaImportController::class, 'template'])
        ->middleware('permission:guias.manage');
    Route::post('/guias/importar', [GuiaImportController::class, 'previsualizar'])
        ->middleware('permission:guias.manage');
    Route::post('/guias/importar/{guia_import_lote}/confirmar', [GuiaImportController::class, 'confirmar'])
        ->middleware('permission:guias.manage');
    Route::get('/guias/{guia}', [GuiaController::class, 'show']);
    Route::patch('/guias/{guia}', [GuiaController::class, 'update'])->middleware('permission:guias.manage');
    Route::patch('/guias/{guia}/finalizar', [GuiaController::class, 'finalizar'])->middleware('permission:guias.view|guias.viewOwn');
    Route::patch('/guias/{guia}/negar', [GuiaController::class, 'negar'])->middleware('permission:guias.view|guias.viewOwn');
    Route::patch('/guias/{guia}/ocultar-alerta-negacao', [GuiaController::class, 'ocultarAlertaNegacao'])->middleware('permission:guias.view|guias.viewOwn');
    Route::patch('/guias/{guia}/ocultar-alerta-restricao', [GuiaController::class, 'ocultarAlertaRestricao'])->middleware('permission:guias.view|guias.viewOwn');
    Route::patch('/guias/{guia}/ocultar-alerta-antecipacao', [GuiaController::class, 'ocultarAlertaAntecipacao'])->middleware('permission:guias.view|guias.viewOwn');
    Route::post('/guias/{guia}/consultar-unimed', [GuiaController::class, 'consultarUnimed'])->middleware('permission:guias.view|guias.viewOwn');
    Route::post('/guias/{guia}/buscar-senha-validade-unimed', [GuiaController::class, 'buscarSenhaValidadeUnimed'])->middleware('permission:guias.view|guias.viewOwn');

    Route::post('/guias/{guia}/lancamentos', [LancamentoController::class, 'store'])->middleware('permission:lancamentos.view|lancamentos.viewOwn');
    Route::post('/guias/{guia}/lancamentos/importar-transcricao', [LancamentoController::class, 'importarTranscricao'])->middleware('permission:lancamentos.view|lancamentos.viewOwn');
    Route::post('/guias/{guia}/lancamentos/ler-registro', [LancamentoController::class, 'lerRegistroSessoes'])->middleware('permission:lancamentos.view|lancamentos.viewOwn');
    Route::post('/lancamentos/importar-analitico', [LancamentoController::class, 'importarAnalitico'])->middleware('permission:lancamentos.view|lancamentos.viewOwn');
    Route::get('/lancamentos/templates/registro-sessoes', [LancamentoPrintTemplateController::class, 'show'])->middleware('permission:lancamentos.view|lancamentos.viewOwn');
    Route::put('/lancamentos/templates/registro-sessoes', [LancamentoPrintTemplateController::class, 'update'])->middleware('permission:lancamentos.manage');
    Route::get('/analiticos', [AnaliticoController::class, 'index'])->middleware('permission:dashboard.analiticos');
    Route::get('/analiticos/{analiticoLote}', [AnaliticoController::class, 'show'])->middleware('permission:dashboard.analiticos');

    Route::get('/lancamentos/importar/template', [LancamentoImportController::class, 'template'])
        ->middleware('permission:lancamentos.manage');
    Route::post('/lancamentos/importar', [LancamentoImportController::class, 'previsualizar'])
        ->middleware('permission:lancamentos.manage');
    Route::post('/lancamentos/importar/{lancamento_import_lote}/confirmar', [LancamentoImportController::class, 'confirmar'])
        ->middleware('permission:lancamentos.manage');
    Route::get('/lancamentos', [LancamentoController::class, 'index']);
    Route::get('/lancamentos/{lancamento}', [LancamentoController::class, 'show']);
    Route::patch('/lancamentos/{lancamento}', [LancamentoController::class, 'update'])->middleware('permission:lancamentos.manage');
    Route::delete('/lancamentos/{lancamento}', [LancamentoController::class, 'destroy'])->middleware('permission:lancamentos.manage');

    Route::post('/guias/{guia}/conciliacao', [ConciliacaoController::class, 'store'])->middleware('permission:conciliacoes.view|conciliacoes.viewOwn');
    Route::get('/conciliacoes/importar/template', [ConciliacaoImportController::class, 'template'])
        ->middleware('permission:conciliacoes.manage');
    Route::post('/conciliacoes/importar', [ConciliacaoImportController::class, 'previsualizar'])
        ->middleware('permission:conciliacoes.manage');
    Route::post('/conciliacoes/importar/{conciliacao_import_lote}/confirmar', [ConciliacaoImportController::class, 'confirmar'])
        ->middleware('permission:conciliacoes.manage');
    Route::get('/conciliacoes', [ConciliacaoController::class, 'index']);
    Route::patch('/conciliacoes/{conciliacao}/marcar-conferido', [ConciliacaoController::class, 'marcarConferido'])->middleware('permission:conciliacoes.view|conciliacoes.viewOwn');
    Route::patch('/conciliacoes/{conciliacao}/marcar-pago', [ConciliacaoController::class, 'marcarPago'])->middleware('permission:conciliacoes.view|conciliacoes.viewOwn');

    Route::post('/logout', [AuthController::class, 'logout']);
});
