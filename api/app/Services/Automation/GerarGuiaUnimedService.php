<?php

namespace App\Services\Automation;

use App\Exceptions\AutomationConcurrencyException;
use App\Jobs\ExecutarAutomacaoUnimedJob;
use App\Models\AutomacaoExecucao;
use App\Models\Guia;
use App\Models\SolicitacaoItem;
use App\Models\UnimedRdaCredential;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GerarGuiaUnimedService
{
    private const ACTIVE_STATUSES = ['queued', 'running', 'uncertain'];

    public function __construct(private readonly AutomacaoService $automacoes)
    {
    }

    public function avaliar(SolicitacaoItem $item): array
    {
        $item->loadMissing([
            'solicitacao.convenio',
            'solicitacao.paciente',
            'solicitacao.documentos',
            'especialidade',
            'profissional',
            'guia',
            'automacaoExecucoes',
        ]);

        $motivos = [];
        $solicitacao = $item->solicitacao;
        $credential = UnimedRdaCredential::query()
            ->where('tenant_id', $item->tenant_id)
            ->where('ativo', true)
            ->first();

        if ($solicitacao?->status !== 'approved') {
            $motivos[] = 'A Solicitação precisa estar aprovada.';
        }

        if ($solicitacao?->convenio?->connector_driver !== 'unimed_rda') {
            $motivos[] = 'O Convênio não está configurado como Unimed RDA.';
        }

        if (! $credential || blank($credential->password)) {
            $motivos[] = 'A credencial Unimed ativa não está configurada.';
        }

        if (! $this->pedidoMedico($item)) {
            $motivos[] = 'Pedido Médico obrigatório não encontrado.';
        }

        if (! $item->profissional || ! $item->especialidade) {
            $motivos[] = 'Profissional e especialidade do item são obrigatórios.';
        }

        if ($item->guia) {
            $motivos[] = 'O item já possui Guia local vinculada.';
        }

        if ($item->automacaoExecucoes->whereIn('status', self::ACTIVE_STATUSES)->isNotEmpty()) {
            $motivos[] = 'Já existe execução Unimed ativa ou incerta para este item.';
        }

        return [
            'eligible' => $motivos === [],
            'motivos' => $motivos,
        ];
    }

    public function enviar(SolicitacaoItem $item): AutomacaoExecucao
    {
        $avaliacao = $this->avaliar($item);

        if (! $avaliacao['eligible']) {
            throw ValidationException::withMessages(['item' => $avaliacao['motivos']]);
        }

        try {
            $execucao = $this->automacoes->enfileirar(
                $item->tenant_id,
                'gerar_guia',
                $item,
                payload: $this->payloadPersistido($item),
            );
        } catch (AutomationConcurrencyException $exception) {
            throw ValidationException::withMessages([
                'item' => ["Já existe execução Unimed ativa para este tenant ({$exception->execucaoId})."],
            ]);
        }

        ExecutarAutomacaoUnimedJob::dispatch($execucao->id);

        return $execucao;
    }

    public function payloadParaWorker(AutomacaoExecucao $execucao): array
    {
        $execucao->loadMissing('solicitacaoItem.solicitacao');
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
        if (($resultado['status'] ?? null) === 'uncertain') {
            $execucao = $this->automacoes->concluir($execucao, $resultado);
            $execucao->solicitacaoItem?->update(['status_operacional' => 'uncertain']);

            return $execucao;
        }

        return DB::transaction(function () use ($execucao, $resultado) {
            $execucao = $this->automacoes->concluir($execucao, $resultado);

            if ($execucao->status === 'succeeded' && filled($resultado['numero_guia'] ?? null)) {
                $this->criarOuAtualizarGuia($execucao, (string) $resultado['numero_guia']);
            }

            return $execucao->refresh();
        });
    }

    private function criarOuAtualizarGuia(AutomacaoExecucao $execucao, string $numeroGuia): Guia
    {
        $item = $execucao->solicitacaoItem()->with(['solicitacao', 'profissional', 'especialidade'])->firstOrFail();
        $solicitacao = $item->solicitacao;

        $guia = Guia::query()->firstOrNew([
            'tenant_id' => $execucao->tenant_id,
            'solicitacao_item_id' => $item->id,
        ]);

        $guia->fill([
            'tenant_id' => $execucao->tenant_id,
            'solicitacao_id' => $solicitacao->id,
            'solicitacao_item_id' => $item->id,
            'automacao_execucao_id' => $execucao->id,
            'convenio_id' => $solicitacao->convenio_id,
            'paciente_id' => $solicitacao->paciente_id,
            'profissional_id' => $item->profissional_id,
            'especialidade_id' => $item->especialidade_id,
            'numero_guia' => $numeroGuia,
            'tipo_terapia' => 'especializada',
            'status' => 'under_review',
            'data_solicitacao' => today(),
            'observacoes' => $solicitacao->observacoes,
        ])->save();

        $item->update(['status_operacional' => 'guia_generated']);

        return $guia;
    }

    private function payloadPersistido(SolicitacaoItem $item): array
    {
        $item->loadMissing(['solicitacao.paciente', 'solicitacao.convenio', 'especialidade', 'profissional']);
        $pedidoMedico = $this->pedidoMedico($item);

        return [
            'solicitacao_id' => $item->solicitacao_id,
            'solicitacao_item_id' => $item->id,
            'paciente' => [
                'id' => $item->solicitacao->paciente_id,
                'nome' => $item->solicitacao->paciente?->nome,
                'carteirinha' => $item->solicitacao->paciente?->carteirinha,
            ],
            'convenio_id' => $item->solicitacao->convenio_id,
            'especialidade' => $item->especialidade?->nome,
            'profissional' => $item->profissional?->nome,
            'quantidade' => $item->quantidade,
            'pedido_medico' => $pedidoMedico ? [
                'id' => $pedidoMedico->id,
                'nome_original' => $pedidoMedico->nome_original,
            ] : null,
        ];
    }

    private function pedidoMedico(SolicitacaoItem $item)
    {
        $item->loadMissing('solicitacao.documentos');

        return $item->solicitacao?->documentos
            ->firstWhere('tipo', 'pedido_medico');
    }
}
