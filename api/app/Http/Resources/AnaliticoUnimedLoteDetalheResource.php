<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnaliticoUnimedLoteDetalheResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $analitico = $this->linhas->where('origem', 'analitico')->values();
        $glosas = $this->linhas->where('origem', 'glosa')->values();
        $conciliacao = $this->linhas->sortBy('linha')->values();

        return [
            'lote' => [
                'id' => $this->id,
                'arquivo_nome_original' => $this->arquivo_nome_original,
                'arquivo_path' => $this->arquivo_path,
                'status' => $this->status,
                'importado_em' => $this->importado_em?->toISOString(),
                'total_linhas_analitico' => $this->total_linhas_analitico,
                'total_linhas_glosa' => $this->total_linhas_glosa,
                'total_linhas_conciliacao' => $this->total_linhas_conciliacao,
                'total_pago' => $this->total_pago,
                'total_glosado' => $this->total_glosado,
                'saldo_total' => $this->saldo_total,
            ],
            'analitico' => [
                'linhas' => $analitico->map(fn ($linha) => $this->mapearLinha($linha))->all(),
                'totais' => [
                    'linhas' => $analitico->count(),
                    'valor' => $analitico->sum('valor_normalizado'),
                ],
            ],
            'glosas' => [
                'linhas' => $glosas->map(fn ($linha) => $this->mapearLinha($linha))->all(),
                'totais' => [
                    'linhas' => $glosas->count(),
                    'valor' => $glosas->sum('valor_normalizado'),
                ],
            ],
            'conciliacao' => [
                'linhas' => $conciliacao->map(fn ($linha) => $this->mapearLinha($linha))->all(),
                'totais' => [
                    'pago' => $this->total_pago,
                    'glosado' => $this->total_glosado,
                    'saldo' => $this->saldo_total,
                ],
            ],
        ];
    }

    /**
     * @param mixed $linha
     * @return array<string, mixed>
     */
    private function mapearLinha($linha): array
    {
        return [
            'id' => $linha->id,
            'linha' => $linha->linha,
            'origem' => $linha->origem,
            'natureza' => $linha->natureza,
            'processavel' => $linha->processavel,
            'numero_guia_operadora' => $linha->numero_guia_operadora,
            'numero_guia_prestador' => $linha->numero_guia_prestador,
            'codigo' => $linha->codigo,
            'usuario' => $linha->usuario,
            'data_autorizacao' => $linha->data_autorizacao,
            'data_realizacao' => $linha->data_realizacao,
            'procedimento' => $linha->procedimento,
            'descricao_procedimento' => $linha->descricao_procedimento,
            'qtd' => $linha->qtd,
            'qtd_normalizada' => $linha->qtd_normalizada,
            'tipo' => $linha->tipo,
            'motivo' => $linha->motivo,
            'valor' => $linha->valor,
            'valor_normalizado' => $linha->valor_normalizado,
            'local_realizacao' => $linha->local_realizacao,
            'chave_conciliacao' => $linha->chave_conciliacao,
        ];
    }
}
