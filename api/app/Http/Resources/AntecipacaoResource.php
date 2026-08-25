<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AntecipacaoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'guia_id' => $this->guia_id,
            'paciente_id' => $this->paciente_id,
            'convenio_id' => $this->convenio_id,
            // Nome, e nao so id: o seletor de antecipacao da importacao
            // mostrava "Paciente 42", numero que ninguem reconhece.
            'paciente' => $this->whenLoaded('paciente', fn () => [
                'id' => $this->paciente->id,
                'nome' => $this->paciente->nome,
                'carteirinha' => $this->paciente->carteirinha,
            ]),
            'convenio' => $this->whenLoaded('convenio', fn () => [
                'id' => $this->convenio->id,
                'nome' => $this->convenio->nome,
            ]),
            // A especialidade vem da guia: e ela que amarra a antecipacao a uma
            // terapia, e e por ela que a tela filtra o profissional executante.
            'especialidade' => $this->whenLoaded('guia', fn () => $this->guia?->relationLoaded('especialidade') && $this->guia->especialidade
                ? ['id' => $this->guia->especialidade->id, 'nome' => $this->guia->especialidade->nome]
                : null),
            // Numero da guia e tipo de terapia: usados para montar o modelo de
            // impressao preenchido em Sessoes (ver LancamentoPrintTemplate).
            'guia' => $this->whenLoaded('guia', fn () => $this->guia ? [
                'numero_guia' => $this->guia->numero_guia,
                'tipo_terapia' => $this->guia->tipo_terapia,
            ] : null),
            'ciclo_inicio' => $this->ciclo_inicio?->toDateString(),
            'ciclo_fim' => $this->ciclo_fim?->toDateString(),
            'qtd_autorizada' => $this->qtd_autorizada,
            'qtd_utilizada' => $this->qtd_utilizada,
            'status' => $this->status,
            'lancamentos' => $this->whenLoaded('lancamentos', fn () => $this->lancamentos->map(fn ($lancamento) => [
                'id' => $lancamento->id,
                'data_sessao' => $lancamento->data_sessao?->toDateString(),
                'hora_inicio' => $lancamento->hora_inicio,
                'hora_fim' => $lancamento->hora_fim,
                'acompanhante' => $lancamento->acompanhante,
                'resumo_atividades' => $lancamento->resumo_atividades,
                'status' => $lancamento->status,
                'profissional' => $lancamento->relationLoaded('profissional') && $lancamento->profissional
                    ? ['id' => $lancamento->profissional->id, 'nome' => $lancamento->profissional->nome]
                    : null,
            ])->values()),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
