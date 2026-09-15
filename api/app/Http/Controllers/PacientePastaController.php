<?php

namespace App\Http\Controllers;

use App\Http\Resources\PacienteResource;
use App\Models\Antecipacao;
use App\Models\Guia;
use App\Models\Lancamento;
use App\Models\Paciente;
use App\Models\PacienteArquivo;
use App\Models\Solicitacao;
use Illuminate\Http\JsonResponse;

/**
 * A pasta do paciente inteira numa requisição.
 *
 * Um endpoint só, e não cinco chamadas às listagens existentes, por dois
 * motivos: as seções abrem recolhidas mostrando a contagem, então a tela
 * precisa dos totais antes de qualquer expansão; e solicitações, sessões e
 * antecipações não têm filtro por paciente nas suas listagens — acrescentá-lo
 * em três serviços para uma tela só espalharia o assunto.
 *
 * O escopo por tenant vem do `BelongsToTenant` de cada model, e o `{paciente}`
 * chega resolvido pelo binding, que já confere o tenant.
 */
class PacientePastaController extends Controller
{
    public function __invoke(Paciente $paciente): JsonResponse
    {
        return response()->json([
            'data' => [
                'paciente' => new PacienteResource($paciente->load('convenio')),
                'solicitacoes' => $this->solicitacoes($paciente),
                'guias' => $this->guias($paciente),
                'sessoes' => $this->sessoes($paciente),
                'antecipacoes' => $this->antecipacoes($paciente),
                'arquivos' => $this->arquivos($paciente),
            ],
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function solicitacoes(Paciente $paciente): array
    {
        return Solicitacao::query()
            ->where('paciente_id', $paciente->id)
            ->with(['convenio', 'medico', 'itens.especialidade', 'itens.profissional'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (Solicitacao $s) => [
                'id' => $s->id,
                'status' => $s->status,
                'solicitado_em' => $s->solicitado_em?->toDateString(),
                'convenio' => $s->convenio?->nome,
                'medico' => $s->medico?->nome,
                'itens' => $s->itens->map(fn ($item) => [
                    'id' => $item->id,
                    'especialidade' => $item->especialidade?->nome,
                    'profissional' => $item->profissional?->nome,
                    'quantidade' => $item->quantidade,
                    'status_operacional' => $item->status_operacional,
                ])->all(),
            ])
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function guias(Paciente $paciente): array
    {
        return Guia::query()
            ->where('paciente_id', $paciente->id)
            ->with(['convenio', 'especialidade', 'profissional'])
            ->withCount('lancamentos')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Guia $g) => [
                'id' => $g->id,
                'numero_guia' => $g->numero_guia,
                'status' => $g->status,
                'convenio' => $g->convenio?->nome,
                'especialidade' => $g->especialidade?->nome,
                'profissional' => $g->profissional?->nome,
                'senha' => $g->senha,
                'validade_senha' => $g->validade_senha?->toDateString(),
                'sessoes_autorizadas' => $g->sessoes_autorizadas,
                'lancamentos_count' => $g->lancamentos_count,
                'sessoes_disponiveis' => $g->sessoesDisponiveis(),
            ])
            ->all();
    }

    /**
     * Sessões chegam pela guia — `lancamentos` não tem `paciente_id`.
     *
     * @return array<int, array<string, mixed>>
     */
    private function sessoes(Paciente $paciente): array
    {
        return Lancamento::query()
            ->whereHas('guia', fn ($query) => $query->where('paciente_id', $paciente->id))
            ->with(['guia', 'profissional'])
            ->orderByDesc('data_sessao')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Lancamento $l) => [
                'id' => $l->id,
                'data_sessao' => $l->data_sessao?->toDateString(),
                'hora_inicio' => $l->hora_inicio,
                'hora_fim' => $l->hora_fim,
                'profissional' => $l->profissional?->nome,
                'guia_id' => $l->guia_id,
                'numero_guia' => $l->guia?->numero_guia,
                'status' => $l->status,
            ])
            ->all();
    }

    /**
     * Antecipações são por SOLICITAÇÃO de origem, não por paciente — daí o
     * `whereHas` em vez de um `where` direto.
     *
     * @return array<int, array<string, mixed>>
     */
    private function antecipacoes(Paciente $paciente): array
    {
        return Antecipacao::query()
            ->whereHas('solicitacaoOrigem', fn ($query) => $query->where('paciente_id', $paciente->id))
            ->with(['solicitacaoOrigem.convenio', 'criadoPor'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (Antecipacao $a) => [
                'id' => $a->id,
                'status' => $a->status,
                'solicitacao_origem_id' => $a->solicitacao_origem_id,
                'convenio' => $a->solicitacaoOrigem?->convenio?->nome,
                'itens_gerados' => count($a->itens_selecionados ?? []),
                'criado_por' => $a->criadoPor?->name,
                'created_at' => $a->created_at?->toISOString(),
            ])
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function arquivos(Paciente $paciente): array
    {
        return PacienteArquivo::query()
            ->where('paciente_id', $paciente->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (PacienteArquivo $a) => [
                'id' => $a->id,
                'tipo' => $a->tipo,
                'nome_original' => $a->nome_original,
                'mime' => $a->mime,
                'metadata' => $a->metadata,
                'created_at' => $a->created_at?->toISOString(),
            ])
            ->all();
    }
}
