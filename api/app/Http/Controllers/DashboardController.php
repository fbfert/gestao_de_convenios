<?php

namespace App\Http\Controllers;

use App\Models\AnaliticoUnimedLote;
use App\Models\Antecipacao;
use App\Models\AuditLog;
use App\Models\ConciliacaoFinanceira;
use App\Models\Convenio;
use App\Models\Especialidade;
use App\Models\Guia;
use App\Models\Lancamento;
use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\Solicitacao;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $blocks = collect([
            [
                'key' => 'convenios',
                'permission' => 'dashboard.convenios',
                'label' => 'Convênios',
                'href' => '/convenios',
                'value' => Convenio::query()->count(),
                'detail' => Convenio::query()->where('ativo', true)->count().' ativos',
            ],
            [
                'key' => 'solicitacoes',
                'permission' => 'dashboard.solicitacoes',
                'label' => 'Solicitações',
                'href' => '/solicitacoes',
                'value' => Solicitacao::query()->where('status', 'under_review')->count(),
                'detail' => Solicitacao::query()->where('status', 'ready_for_automation')->count().' prontas para automação',
            ],
            [
                'key' => 'guias',
                'permission' => 'dashboard.guias',
                'label' => 'Guias',
                'href' => '/guias',
                // O fluxo manual (não-Unimed) usa under_review/finalized; o fluxo
                // automático Unimed usa under_review/needs_verification/approved.
                // Conta os dois: senão a clínica 100% Unimed via aqui sempre "0/0".
                'value' => Guia::query()->whereIn('status', ['under_review', 'needs_verification'])->count(),
                'detail' => Guia::query()->whereIn('status', ['finalized', 'approved'])->count().' aprovadas',
            ],
            [
                'key' => 'antecipacoes',
                'permission' => 'dashboard.antecipacoes',
                'label' => 'Antecipações',
                'href' => '/antecipacoes',
                'value' => Antecipacao::query()->where('status', 'open')->count(),
                'detail' => Antecipacao::query()->where('status', 'closed')->count().' fechadas',
            ],
            [
                'key' => 'lancamentos',
                'permission' => 'dashboard.lancamentos',
                'label' => 'Sessões',
                'href' => '/lancamentos',
                'value' => Lancamento::query()->whereDate('created_at', today())->count(),
                'detail' => Lancamento::query()->where('status', 'completed')->count().' concluídos',
            ],
            [
                'key' => 'conciliacoes',
                'permission' => 'dashboard.conciliacoes',
                'label' => 'Conciliações',
                'href' => '/conciliacao',
                'value' => ConciliacaoFinanceira::query()->where('status', 'pending')->count(),
                'detail' => ConciliacaoFinanceira::query()->where('status', 'reviewed')->count().' conferidas',
            ],
            [
                'key' => 'pacientes',
                'permission' => 'dashboard.pacientes',
                'label' => 'Pacientes',
                'href' => '/pacientes',
                'value' => Paciente::query()->where('ativo', true)->count(),
                'detail' => Paciente::query()->count().' cadastrados',
            ],
            [
                'key' => 'profissionais',
                'permission' => 'dashboard.profissionais',
                'label' => 'Profissionais',
                'href' => '/profissionais',
                'value' => Profissional::query()->where('ativo', true)->count(),
                'detail' => Profissional::query()->count().' cadastrados',
            ],
            [
                'key' => 'medicos',
                'permission' => 'dashboard.medicos',
                'label' => 'Médicos',
                'href' => '/medicos',
                'value' => Medico::query()->where('ativo', true)->count(),
                'detail' => Medico::query()->count().' cadastrados',
            ],
            [
                'key' => 'especialidades',
                'permission' => 'dashboard.especialidades',
                'label' => 'Especialidades',
                'href' => '/especialidades',
                'value' => Especialidade::query()->where('ativo', true)->count(),
                'detail' => Especialidade::query()->count().' cadastradas',
            ],
            [
                'key' => 'usuarios',
                'permission' => 'dashboard.usuarios',
                'label' => 'Usuários',
                'href' => '/usuarios',
                'value' => User::query()->where('tenant_id', $user->tenant_id)->where('ativo', true)->count(),
                'detail' => User::query()->where('tenant_id', $user->tenant_id)->count().' cadastrados',
            ],
            [
                'key' => 'analiticos',
                'permission' => 'dashboard.analiticos',
                'label' => 'Analíticos',
                'href' => '/analiticos',
                'value' => AnaliticoUnimedLote::query()->count(),
                // `importado` e o default da coluna: lote lido mas ainda nao conciliado.
                'detail' => AnaliticoUnimedLote::query()->where('status', 'importado')->count().' importados',
            ],
            [
                'key' => 'auditoria',
                'permission' => 'dashboard.auditoria',
                'label' => 'Auditoria',
                'href' => '/auditoria',
                'value' => AuditLog::query()->where('created_at', '>=', now()->subDay())->count(),
                'detail' => 'últimas 24 horas',
            ],
        ])->filter(function (array $block) use ($user) {
            return $user?->can($block['permission']) ?? false;
        })->values();

        return response()->json([
            'data' => [
                'blocks' => $blocks,
                'recent_audits' => $this->recentAudits($user),
            ],
        ]);
    }

    private function recentAudits($user): array
    {
        if (! $user?->can('dashboard.auditoria')) {
            return [];
        }

        return AuditLog::query()
            ->with('user')
            ->latest('created_at')
            ->limit(12)
            ->get()
            ->map(fn (AuditLog $audit) => [
                'id' => $audit->id,
                'acao' => $audit->acao,
                'entidade' => $audit->entidade,
                'entidade_id' => $audit->entidade_id,
                'usuario' => $audit->user?->name,
                'created_at' => $audit->created_at?->toISOString(),
            ])
            ->all();
    }
}
