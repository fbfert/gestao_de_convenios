<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LancamentoPrintTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'chave' => $this->chave,
            'nome' => $this->nome,
            'html' => $this->html,
            'ativo' => $this->ativo,
            'placeholders' => [
                'guia_numero',
                'clinica',
                'paciente',
                'numero_cartao',
                'profissional_executante',
                'terapia_aplicada',
                'data_impressao',
                'sessoes.numero',
                'sessoes.data_sessao',
                'sessoes.hora_inicio',
                'sessoes.hora_fim',
                'sessoes.acompanhante',
                'sessoes.resumo_atividades',
            ],
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
