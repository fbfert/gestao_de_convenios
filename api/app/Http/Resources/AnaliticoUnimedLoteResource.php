<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnaliticoUnimedLoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
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
        ];
    }
}
