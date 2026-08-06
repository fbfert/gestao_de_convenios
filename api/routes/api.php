<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AutomacaoController;
use App\Http\Controllers\AntecipacaoController;
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
use App\Http\Controllers\EspecialidadeController;
use App\Http\Controllers\ConciliacaoController;
use App\Http\Controllers\GuiaController;
use App\Http\Controllers\MedicoController;
use App\Http\Controllers\LancamentoController;
use App\Http\Controllers\LancamentoPrintTemplateController;
use App\Http\Controllers\AnaliticoController;
use App\Http\Controllers\AiSettingsController;
use App\Http\Controllers\ManualController;
use App\Http\Controllers\PacienteController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SolicitacaoController;
use App\Http\Controllers\ProfissionalController;
use App\Http\Controllers\SolicitacaoDocumentoController;
use App\Http\Controllers\UnimedSettingsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/auditoria', [AuditController::class, 'index'])->middleware('permission:dashboard.auditoria');
    Route::get('/automacoes', [AutomacaoController::class, 'index']);
    Route::get('/automacoes/{automacaoExecucao}', [AutomacaoController::class, 'show']);
    Route::post('/automacoes/{automacaoExecucao}/reprocessar', [AutomacaoController::class, 'reprocessar']);

    Route::get('/manual/{tipo?}', [ManualController::class, 'show'])->where('tipo', 'manual|mapa-mental');
    Route::put('/manual/{tipo?}', [ManualController::class, 'update'])->middleware('permission:manual.manage')->where('tipo', 'manual|mapa-mental');
    Route::get('/configuracoes/emails', [EmailSettingsController::class, 'show'])->middleware('permission:configuracoes.manage');
    Route::put('/configuracoes/emails', [EmailSettingsController::class, 'update'])->middleware('permission:configuracoes.manage');
    Route::get('/configuracoes/emails/templates', [EmailTemplateController::class, 'index'])->middleware('permission:configuracoes.manage');
    Route::post('/configuracoes/emails/templates', [EmailTemplateController::class, 'store'])->middleware('permission:configuracoes.manage');
    Route::put('/configuracoes/emails/templates/{emailTemplate}', [EmailTemplateController::class, 'update'])->middleware('permission:configuracoes.manage');
    Route::delete('/configuracoes/emails/templates/{emailTemplate}', [EmailTemplateController::class, 'destroy'])->middleware('permission:configuracoes.manage');
    Route::get('/configuracoes/ia', [AiSettingsController::class, 'show'])->middleware('permission:configuracoes.manage');
    Route::put('/configuracoes/ia', [AiSettingsController::class, 'update'])->middleware('permission:configuracoes.manage');
    Route::get('/configuracoes/ia/modelos', [AiSettingsController::class, 'models'])->middleware('permission:configuracoes.manage');
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
    Route::get('/pacientes/{paciente}', [PacienteController::class, 'show']);
    Route::post('/pacientes', [PacienteController::class, 'store']);
    Route::patch('/pacientes/{paciente}', [PacienteController::class, 'update']);
    Route::get('/profissionais', [ProfissionalController::class, 'index']);
    Route::post('/profissionais', [ProfissionalController::class, 'store'])->middleware('permission:profissionais.manage');
    Route::patch('/profissionais/{profissional}', [ProfissionalController::class, 'update'])->middleware('permission:profissionais.manage');
    Route::get('/especialidades', [EspecialidadeController::class, 'index']);
    Route::post('/especialidades', [EspecialidadeController::class, 'store'])->middleware('permission:especialidades.manage');
    Route::patch('/especialidades/{especialidade}', [EspecialidadeController::class, 'update'])->middleware('permission:especialidades.manage');
    Route::get('/convenios', [ConvenioController::class, 'index']);
    Route::post('/convenios', [ConvenioController::class, 'store'])->middleware('permission:convenios.manage');
    Route::patch('/convenios/{convenio}', [ConvenioController::class, 'update'])->middleware('permission:convenios.manage');
    Route::get('/convenios/{convenio}/regras', [ConvenioController::class, 'regras'])->middleware('permission:convenios.manage');
    Route::post('/convenios/{convenio}/regras', [ConvenioController::class, 'storeRegra'])->middleware('permission:convenios.manage');
    Route::patch('/convenios/{convenio}/regras/{regra}/encerrar', [ConvenioController::class, 'encerrarRegra'])->middleware('permission:convenios.manage');
    Route::get('/convenios/{convenio}/valores', [ConvenioController::class, 'valores'])->middleware('permission:convenios.manage');
    Route::post('/convenios/{convenio}/valores', [ConvenioController::class, 'storeValor'])->middleware('permission:convenios.manage');
    Route::patch('/convenios/{convenio}/valores/{valor}/encerrar', [ConvenioController::class, 'encerrarValor'])->middleware('permission:convenios.manage');
    Route::get('/medicos', [MedicoController::class, 'index'])->middleware('permission:medicos.manage');
    Route::post('/medicos', [MedicoController::class, 'store'])->middleware('permission:medicos.manage');
    Route::patch('/medicos/{medico}', [MedicoController::class, 'update'])->middleware('permission:medicos.manage');

    Route::get('/roles', [RoleController::class, 'index'])->middleware('permission:permissoes.manage');
    Route::get('/permissions', [PermissionController::class, 'index'])->middleware('permission:permissoes.manage');
    Route::get('/roles/{role}/permissions', [RolePermissionController::class, 'show'])->middleware('permission:permissoes.manage');
    Route::put('/roles/{role}/permissions', [RolePermissionController::class, 'update'])->middleware('permission:permissoes.manage');

    Route::get('/usuarios', [UserController::class, 'index'])->middleware('permission:usuarios.manage');
    Route::post('/usuarios', [UserController::class, 'store'])->middleware('permission:usuarios.manage');
    Route::patch('/usuarios/{usuario}', [UserController::class, 'update'])->middleware('permission:usuarios.manage');

    Route::get('/solicitacoes', [SolicitacaoController::class, 'index']);
    Route::post('/solicitacoes', [SolicitacaoController::class, 'store']);
    Route::post('/solicitacoes/ler-pedido-medico', [SolicitacaoController::class, 'analisarPedidoMedico']);
    Route::post('/solicitacoes/pacientes-rapido', [SolicitacaoController::class, 'storePacienteRapido']);
    Route::post('/solicitacoes/especialidades-rapido', [SolicitacaoController::class, 'storeEspecialidadeRapida']);
    Route::post('/solicitacoes/medicos-rapido', [SolicitacaoController::class, 'storeMedicoRapido']);
    Route::get('/solicitacoes/{solicitacao}', [SolicitacaoController::class, 'show']);
    Route::get('/solicitacoes/{solicitacao}/pedido-medico', [SolicitacaoController::class, 'pedidoMedico']);
    Route::post('/solicitacoes/{solicitacao}/documentos', [SolicitacaoDocumentoController::class, 'store']);
    Route::get('/solicitacoes/{solicitacao}/documentos/{documento}', [SolicitacaoDocumentoController::class, 'download']);
    Route::delete('/solicitacoes/{solicitacao}/documentos/{documento}', [SolicitacaoDocumentoController::class, 'destroy']);
    Route::patch('/solicitacoes/{solicitacao}/status', [SolicitacaoController::class, 'updateStatus']);
    Route::patch('/solicitacoes/{solicitacao}/aprovar', [SolicitacaoController::class, 'aprovar']);
    Route::patch('/solicitacoes/{solicitacao}/negar', [SolicitacaoController::class, 'negar']);
    Route::post('/solicitacao-itens/{solicitacaoItem}/enviar-unimed', [SolicitacaoController::class, 'enviarItemUnimed']);

    Route::get('/guias', [GuiaController::class, 'index']);
    Route::post('/guias', [GuiaController::class, 'store']);
    Route::get('/guias/{guia}', [GuiaController::class, 'show']);
    Route::patch('/guias/{guia}/finalizar', [GuiaController::class, 'finalizar']);
    Route::patch('/guias/{guia}/negar', [GuiaController::class, 'negar']);
    Route::post('/guias/{guia}/consultar-unimed', [GuiaController::class, 'consultarUnimed']);
    Route::post('/guias/{guia}/buscar-senha-validade-unimed', [GuiaController::class, 'buscarSenhaValidadeUnimed']);

    Route::get('/antecipacoes', [AntecipacaoController::class, 'index']);
    Route::get('/antecipacoes/{antecipacao}', [AntecipacaoController::class, 'show']);
    Route::post('/antecipacoes/{antecipacao}/lancamentos', [LancamentoController::class, 'store']);
    Route::post('/antecipacoes/{antecipacao}/lancamentos/importar-transcricao', [LancamentoController::class, 'importarTranscricao']);
    Route::post('/lancamentos/importar-analitico', [LancamentoController::class, 'importarAnalitico']);
    Route::get('/lancamentos/templates/registro-sessoes', [LancamentoPrintTemplateController::class, 'show']);
    Route::put('/lancamentos/templates/registro-sessoes', [LancamentoPrintTemplateController::class, 'update']);
    Route::get('/analiticos', [AnaliticoController::class, 'index']);
    Route::get('/analiticos/{analiticoLote}', [AnaliticoController::class, 'show']);

    Route::get('/lancamentos', [LancamentoController::class, 'index']);
    Route::get('/lancamentos/{lancamento}', [LancamentoController::class, 'show']);
    Route::patch('/lancamentos/{lancamento}', [LancamentoController::class, 'update']);
    Route::delete('/lancamentos/{lancamento}', [LancamentoController::class, 'destroy']);

    Route::post('/guias/{guia}/conciliacao', [ConciliacaoController::class, 'store']);
    Route::get('/conciliacoes', [ConciliacaoController::class, 'index']);
    Route::patch('/conciliacoes/{conciliacao}/marcar-conferido', [ConciliacaoController::class, 'marcarConferido']);
    Route::patch('/conciliacoes/{conciliacao}/marcar-pago', [ConciliacaoController::class, 'marcarPago']);

    Route::post('/logout', [AuthController::class, 'logout']);
});
