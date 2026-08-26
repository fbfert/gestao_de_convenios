<?php

namespace App\Services\Automation;

use App\Jobs\ExecutarAutomacaoUnimedJob;
use App\Models\AutomacaoExecucao;
use App\Models\Guia;
use App\Models\SolicitacaoItem;
use App\Models\UnimedRdaCredential;
use App\Services\SolicitacaoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Resolve o estado 'uncertain' pós-submit de gerar_guia (UNCERTAIN_AFTER_SUBMIT
 * — Finalizar rodou no portal mas o worker não conseguiu ler a confirmação de
 * volta, sem numero_guia conhecido). Busca a guia por paciente em "Exames em
 * aberto" e, com o resultado, ou cria a Guia local (achou) ou libera o item
 * pra reenvio manual (não achou) — nunca reenvia sozinho.
 */
class ConfirmarGuiaIncertaUnimedService
{
    public const OPERATION = 'confirmar_guia_incerta';

    public function __construct(
        private readonly AutomacaoService $automacoes,
        private readonly SolicitacaoService $solicitacoes,
    ) {
    }

    public function avaliar(SolicitacaoItem $item): array
    {
        $item->loadMissing(['guia', 'automacaoExecucoes' => fn ($query) => $query->orderByDesc('id')]);

        $motivos = [];

        if ($item->guia) {
            $motivos[] = 'O item já possui Guia local vinculada.';
        }

        if (! $this->execucaoIncertaAtual($item)) {
            $motivos[] = 'O item não possui execução de geração de Guia incerta pendente de confirmação.';
        }

        $credential = UnimedRdaCredential::query()
            ->where('tenant_id', $item->tenant_id)
            ->where('ativo', true)
            ->first();

        if (! $credential || blank($credential->password)) {
            $motivos[] = 'A credencial Unimed ativa não está configurada.';
        }

        if ($item->automacaoExecucoes->where('operacao', self::OPERATION)->whereIn('status', ['queued', 'running'])->isNotEmpty()) {
            $motivos[] = 'Já existe confirmação de guia incerta em andamento para este item.';
        }

        return ['eligible' => $motivos === [], 'motivos' => $motivos];
    }

    public function enviar(SolicitacaoItem $item): AutomacaoExecucao
    {
        $avaliacao = $this->avaliar($item);

        if (! $avaliacao['eligible']) {
            throw ValidationException::withMessages(['item' => $avaliacao['motivos']]);
        }

        $execucaoIncerta = $this->execucaoIncertaAtual($item);

        $execucao = $this->automacoes->enfileirar(
            $item->tenant_id,
            self::OPERATION,
            $item,
            null,
            $this->payloadPersistido($item, $execucaoIncerta),
            $execucaoIncerta,
        );

        ExecutarAutomacaoUnimedJob::dispatch($execucao->id);

        return $execucao;
    }

    public function payloadParaWorker(AutomacaoExecucao $execucao): array
    {
        $credential = UnimedRdaCredential::query()
            ->where('tenant_id', $execucao->tenant_id)
            ->where('ativo', true)
            ->firstOrFail();

        return ($execucao->payload ?? []) + [
            'credential' => [
                'login' => $credential->login,
                'password' => $credential->password,
                'base_url' => $credential->base_url,
            ],
        ];
    }

    public function aplicarResultado(AutomacaoExecucao $execucao, array $resultado): AutomacaoExecucao
    {
        return DB::transaction(function () use ($execucao, $resultado) {
            $execucao = $this->automacoes->concluir($execucao, $resultado);

            if ($execucao->status !== 'succeeded') {
                // Falha técnica na própria confirmação (login, timeout, portal
                // fora do ar) — não resolve nada, o item continua 'uncertain'
                // e a próxima janela agendada tenta de novo.
                return $execucao;
            }

            $item = $execucao->solicitacaoItem()->firstOrFail();
            $execucaoIncerta = $execucao->parent;

            if ($resultado['encontrada'] ?? false) {
                $guia = $this->criarOuAtualizarGuia($execucao, $execucaoIncerta, $item, $resultado);
                $this->solicitacoes->sincronizarStatusComGuias($guia->solicitacao);
            } else {
                $this->resolverComoNaoEncontrada($execucao, $execucaoIncerta, $item);
            }

            return $execucao->refresh();
        });
    }

    private function criarOuAtualizarGuia(
        AutomacaoExecucao $execucaoConfirmacao,
        ?AutomacaoExecucao $execucaoIncerta,
        SolicitacaoItem $item,
        array $resultado,
    ): Guia {
        $item->loadMissing(['solicitacao']);
        $solicitacao = $item->solicitacao;

        $guia = Guia::query()->firstOrNew([
            'tenant_id' => $execucaoConfirmacao->tenant_id,
            'solicitacao_item_id' => $item->id,
        ]);

        $guia->fill([
            'tenant_id' => $execucaoConfirmacao->tenant_id,
            'solicitacao_id' => $solicitacao->id,
            'solicitacao_item_id' => $item->id,
            'automacao_execucao_id' => $execucaoIncerta?->id ?? $execucaoConfirmacao->id,
            'convenio_id' => $solicitacao->convenio_id,
            'paciente_id' => $solicitacao->paciente_id,
            'profissional_id' => $item->profissional_id,
            'especialidade_id' => $item->especialidade_id,
            'numero_guia' => $resultado['numero_guia'] ?? null,
            'tipo_terapia' => 'especializada',
            'status' => $resultado['guia_status'] ?? 'under_review',
            'unimed_status' => $resultado['unimed_status'] ?? $resultado['situacao_portal'] ?? null,
            'sessoes_solicitadas' => $resultado['sessoes_solicitadas'] ?? null,
            'sessoes_autorizadas' => $resultado['sessoes_autorizadas'] ?? null,
            'senha' => $resultado['senha'] ?? null,
            'validade_senha' => $resultado['validade_senha'] ?? null,
            'data_solicitacao' => today(),
            'observacoes' => $solicitacao->observacoes,
        ])->save();

        $item->update(['status_operacional' => 'guia_generated', 'unimed_verificacao_next_check_at' => null]);

        if ($execucaoIncerta) {
            $execucaoIncerta->forceFill(['status' => 'succeeded', 'finished_at' => now()])->save();
            $this->automacoes->registrarEvento($execucaoIncerta, 'confirmacao_idempotente', 'succeeded', [
                'mensagem' => 'Guia encontrada em Exames em aberto por busca de paciente.',
                'confirmado_por_execucao_id' => $execucaoConfirmacao->id,
                'numero_guia' => $resultado['numero_guia'] ?? null,
            ]);
        }

        return $guia;
    }

    private function resolverComoNaoEncontrada(
        AutomacaoExecucao $execucaoConfirmacao,
        ?AutomacaoExecucao $execucaoIncerta,
        SolicitacaoItem $item,
    ): void {
        if ($execucaoIncerta) {
            // A execução original de gerar_guia fica 'uncertain' pra sempre
            // se não mexermos nela — e é isso que trava reenvio em
            // GerarGuiaUnimedService::avaliar() (ACTIVE_STATUSES inclui
            // 'uncertain'). Resolver pra 'failed' aqui é o que efetivamente
            // libera o botão manual de novo.
            $execucaoIncerta->forceFill([
                'status' => 'failed',
                'erro_codigo' => 'CONFIRMADO_NAO_CRIADA',
                'erro_mensagem' => 'Confirmado por busca de paciente em Exames em aberto: a guia não foi criada no portal.',
                'finished_at' => now(),
            ])->save();

            $this->automacoes->registrarEvento($execucaoIncerta, 'confirmacao_idempotente', 'failed', [
                'mensagem' => 'Guia não encontrada em Exames em aberto — reenvio manual liberado.',
                'confirmado_por_execucao_id' => $execucaoConfirmacao->id,
            ]);
        }

        $item->update(['status_operacional' => 'pending', 'unimed_verificacao_next_check_at' => null]);
    }

    private function execucaoIncertaAtual(SolicitacaoItem $item): ?AutomacaoExecucao
    {
        return $item->automacaoExecucoes
            ->where('operacao', 'gerar_guia')
            ->where('status', 'uncertain')
            ->first();
    }

    private function payloadPersistido(SolicitacaoItem $item, ?AutomacaoExecucao $execucaoIncerta): array
    {
        $item->loadMissing('solicitacao.paciente');

        return [
            'solicitacao_item_id' => $item->id,
            'paciente' => [
                'id' => $item->solicitacao->paciente_id,
                'nome' => $item->solicitacao->paciente?->nome,
                'carteirinha' => $item->solicitacao->paciente?->carteirinha,
            ],
            'execucao_incerta_id' => $execucaoIncerta?->id,
            'submetida_em' => $execucaoIncerta?->created_at?->toISOString(),
        ];
    }
}
