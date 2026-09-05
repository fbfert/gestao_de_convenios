<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AutomacaoController;
use App\Http\Controllers\AntecipacaoController;
use App\Http\Controllers\AntecipacaoImportController;
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
use App\Http\Controllers\PacienteArquivoController;
use App\Http\Controllers\PacienteController;
use App\Http\Controllers\PacienteMergeController;
use App\Http\Controllers\PacienteImportController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SolicitacaoController;
use App\Http\Controllers\SolicitacaoImportController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\ProfissionalController;
use App\Http\Controllers\SolicitacaoDocumentoController;
use App\Http\Controllers\UnimedSettingsController;
use App\Support\AuthPayload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

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
    Route::get('/auditoria', [AuditController::class, 'index'])->middleware('permission:dashboard.auditoria');
    Route::get('/auditoria/opcoes', [AuditController::class, 'opcoes'])->middleware('permission:dashboard.auditoria');
    Route::get('/auditoria/exportar', [AuditController::class, 'exportar'])->middleware('permission:dashboard.auditoria');
    Route::get('/automacoes', [AutomacaoController::class, 'index']);
    Route::get('/automacoes/{automacaoExecucao}', [AutomacaoController::class, 'show']);
    Route::post('/automacoes/{automacaoExecucao}/reprocessar', [AutomacaoController::class, 'reprocessar']);

    Route::get('/manual/{tipo?}', [ManualController::class, 'show'])->where('tipo', 'manual|mapa-mental');
    Route::put('/manual/{tipo?}', [ManualController::class, 'update'])->middleware('permission:manual.manage')->where('tipo', 'manual|mapa-mental');
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

    Route::get('/pacientes', [PacienteController::class, 'index']);
    // Antes da rota com {paciente}: sem isso "ler-carteirinha", "importar" e
    // "recentes" seriam lidos como id de paciente.
    Route::get('/pacientes/recentes', [PacienteController::class, 'recentes']);
    Route::post('/pacientes/ler-carteirinha', [PacienteController::class, 'lerCarteirinha']);
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
    Route::get('/pacientes/{paciente}', [PacienteController::class, 'show']);
    Route::post('/pacientes', [PacienteController::class, 'store']);
    Route::patch('/pacientes/{paciente}', [PacienteController::class, 'update']);
    Route::get('/pacientes/{paciente}/arquivos', [PacienteArquivoController::class, 'index']);
    Route::post('/pacientes/{paciente}/arquivos', [PacienteArquivoController::class, 'store']);
    Route::get('/pacientes/{paciente}/arquivos/{arquivo}', [PacienteArquivoController::class, 'download']);
    Route::delete('/pacientes/{paciente}/arquivos/{arquivo}', [PacienteArquivoController::class, 'destroy']);
    Route::get('/profissionais', [ProfissionalController::class, 'index']);
    Route::post('/profissionais', [ProfissionalController::class, 'store'])->middleware('permission:profissionais.manage');
    Route::patch('/profissionais/{profissional}', [ProfissionalController::class, 'update'])->middleware('permission:profissionais.manage');
    Route::get('/especialidades', [EspecialidadeController::class, 'index']);
    Route::post('/especialidades', [EspecialidadeController::class, 'store'])->middleware('permission:especialidades.manage');
    Route::patch('/especialidades/{especialidade}', [EspecialidadeController::class, 'update'])->middleware('permission:especialidades.manage');
    Route::get('/cids', [CidController::class, 'index']);
    Route::post('/cids', [CidController::class, 'store']);
    Route::patch('/cids/{cid}', [CidController::class, 'update']);
    Route::get('/convenios', [ConvenioController::class, 'index']);
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
    Route::post('/usuarios', [UserController::class, 'store'])->middleware('permission:usuarios.manage');
    Route::patch('/usuarios/{usuario}', [UserController::class, 'update'])->middleware('permission:usuarios.manage');

    Route::get('/solicitacoes', [SolicitacaoController::class, 'index']);
    Route::post('/solicitacoes', [SolicitacaoController::class, 'store']);
    Route::get('/solicitacoes/importar/template', [SolicitacaoImportController::class, 'template'])
        ->middleware('permission:solicitacoes.manage');
    Route::post('/solicitacoes/importar', [SolicitacaoImportController::class, 'previsualizar'])
        ->middleware('permission:solicitacoes.manage');
    Route::post('/solicitacoes/importar/{solicitacao_import_lote}/confirmar', [SolicitacaoImportController::class, 'confirmar'])
        ->middleware('permission:solicitacoes.manage');
    Route::post('/solicitacoes/ler-pedido-medico', [SolicitacaoController::class, 'analisarPedidoMedico']);
    Route::post('/solicitacoes/pacientes-rapido', [SolicitacaoController::class, 'storePacienteRapido']);
    Route::post('/solicitacoes/especialidades-rapido', [SolicitacaoController::class, 'storeEspecialidadeRapida']);
    Route::post('/solicitacoes/medicos-rapido', [SolicitacaoController::class, 'storeMedicoRapido']);
    Route::post('/solicitacoes/cids-rapido', [SolicitacaoController::class, 'storeCidRapido']);
    Route::get('/solicitacoes/{solicitacao}', [SolicitacaoController::class, 'show']);
    Route::patch('/solicitacoes/{solicitacao}', [SolicitacaoController::class, 'update'])->middleware('permission:solicitacoes.manage');
    Route::post('/solicitacoes/{solicitacao}/documentos', [SolicitacaoDocumentoController::class, 'store']);
    Route::post('/solicitacoes/{solicitacao}/documentos/vincular', [SolicitacaoDocumentoController::class, 'vincular']);
    Route::get('/solicitacoes/{solicitacao}/documentos/{documento}', [SolicitacaoDocumentoController::class, 'download']);
    Route::delete('/solicitacoes/{solicitacao}/documentos/{documento}', [SolicitacaoDocumentoController::class, 'destroy']);
    Route::patch('/solicitacoes/{solicitacao}/status', [SolicitacaoController::class, 'updateStatus']);
    Route::patch('/solicitacoes/{solicitacao}/aprovar', [SolicitacaoController::class, 'aprovar']);
    Route::patch('/solicitacoes/{solicitacao}/negar', [SolicitacaoController::class, 'negar']);
    Route::post('/solicitacao-itens/{solicitacaoItem}/enviar-unimed', [SolicitacaoController::class, 'enviarItemUnimed']);
    Route::post('/solicitacao-itens/{solicitacaoItem}/verificar-andamento', [SolicitacaoController::class, 'verificarAndamentoItem']);

    Route::get('/guias', [GuiaController::class, 'index']);
    Route::post('/guias', [GuiaController::class, 'store']);
    Route::get('/guias/importar/template', [GuiaImportController::class, 'template'])
        ->middleware('permission:guias.manage');
    Route::post('/guias/importar', [GuiaImportController::class, 'previsualizar'])
        ->middleware('permission:guias.manage');
    Route::post('/guias/importar/{guia_import_lote}/confirmar', [GuiaImportController::class, 'confirmar'])
        ->middleware('permission:guias.manage');
    Route::get('/guias/{guia}', [GuiaController::class, 'show']);
    Route::patch('/guias/{guia}', [GuiaController::class, 'update'])->middleware('permission:guias.manage');
    Route::patch('/guias/{guia}/finalizar', [GuiaController::class, 'finalizar']);
    Route::patch('/guias/{guia}/negar', [GuiaController::class, 'negar']);
    Route::patch('/guias/{guia}/ocultar-alerta-negacao', [GuiaController::class, 'ocultarAlertaNegacao']);
    Route::post('/guias/{guia}/consultar-unimed', [GuiaController::class, 'consultarUnimed']);
    Route::post('/guias/{guia}/buscar-senha-validade-unimed', [GuiaController::class, 'buscarSenhaValidadeUnimed']);

    Route::get('/antecipacoes', [AntecipacaoController::class, 'index']);
    Route::get('/antecipacoes/importar/template', [AntecipacaoImportController::class, 'template'])
        ->middleware('permission:antecipacoes.manage');
    Route::post('/antecipacoes/importar', [AntecipacaoImportController::class, 'previsualizar'])
        ->middleware('permission:antecipacoes.manage');
    Route::post('/antecipacoes/importar/{antecipacao_import_lote}/confirmar', [AntecipacaoImportController::class, 'confirmar'])
        ->middleware('permission:antecipacoes.manage');
    Route::get('/antecipacoes/{antecipacao}', [AntecipacaoController::class, 'show']);
    Route::patch('/antecipacoes/{antecipacao}', [AntecipacaoController::class, 'update'])->middleware('permission:antecipacoes.manage');
    Route::post('/antecipacoes/{antecipacao}/lancamentos', [LancamentoController::class, 'store']);
    Route::post('/antecipacoes/{antecipacao}/lancamentos/importar-transcricao', [LancamentoController::class, 'importarTranscricao']);
    Route::post('/antecipacoes/{antecipacao}/lancamentos/ler-registro', [LancamentoController::class, 'lerRegistroSessoes']);
    Route::post('/lancamentos/importar-analitico', [LancamentoController::class, 'importarAnalitico']);
    Route::get('/lancamentos/templates/registro-sessoes', [LancamentoPrintTemplateController::class, 'show']);
    Route::put('/lancamentos/templates/registro-sessoes', [LancamentoPrintTemplateController::class, 'update']);
    Route::get('/analiticos', [AnaliticoController::class, 'index']);
    Route::get('/analiticos/{analiticoLote}', [AnaliticoController::class, 'show']);

    Route::get('/lancamentos/importar/template', [LancamentoImportController::class, 'template'])
        ->middleware('permission:lancamentos.manage');
    Route::post('/lancamentos/importar', [LancamentoImportController::class, 'previsualizar'])
        ->middleware('permission:lancamentos.manage');
    Route::post('/lancamentos/importar/{lancamento_import_lote}/confirmar', [LancamentoImportController::class, 'confirmar'])
        ->middleware('permission:lancamentos.manage');
    Route::get('/lancamentos', [LancamentoController::class, 'index']);
    Route::get('/lancamentos/{lancamento}', [LancamentoController::class, 'show']);
    Route::patch('/lancamentos/{lancamento}', [LancamentoController::class, 'update'])->middleware('permission:lancamentos.manage');
    Route::delete('/lancamentos/{lancamento}', [LancamentoController::class, 'destroy']);

    Route::post('/guias/{guia}/conciliacao', [ConciliacaoController::class, 'store']);
    Route::get('/conciliacoes/importar/template', [ConciliacaoImportController::class, 'template'])
        ->middleware('permission:conciliacoes.manage');
    Route::post('/conciliacoes/importar', [ConciliacaoImportController::class, 'previsualizar'])
        ->middleware('permission:conciliacoes.manage');
    Route::post('/conciliacoes/importar/{conciliacao_import_lote}/confirmar', [ConciliacaoImportController::class, 'confirmar'])
        ->middleware('permission:conciliacoes.manage');
    Route::get('/conciliacoes', [ConciliacaoController::class, 'index']);
    Route::patch('/conciliacoes/{conciliacao}/marcar-conferido', [ConciliacaoController::class, 'marcarConferido']);
    Route::patch('/conciliacoes/{conciliacao}/marcar-pago', [ConciliacaoController::class, 'marcarPago']);

    Route::post('/logout', [AuthController::class, 'logout']);
});
