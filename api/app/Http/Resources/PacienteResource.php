<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PacienteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'cpf' => $this->cpf,
            'data_nascimento' => $this->data_nascimento?->toDateString(),
            'carteirinha' => $this->carteirinha,
            'validade_carteirinha' => $this->validade_carteirinha?->toDateString(),
            'carteirinha_vencida' => $this->carteirinhaVencida(),
            'convenio_id' => $this->convenio_id,
            // Coluna antiga, mantida para nao quebrar quem ainda le dela; a
            // fonte agora e a lista `telefones`.
            'telefone' => $this->telefone,
            'telefones' => $this->whenLoaded('telefones', fn () => $this->telefones->map(fn ($telefone) => [
                'id' => $telefone->id,
                'numero' => $telefone->numero,
                'rotulo' => $telefone->rotulo,
                'contato_nome' => $telefone->contato_nome,
                'principal' => $telefone->principal,
            ])),
            'clinica_agil_id' => $this->clinica_agil_id,
            'ativo' => $this->ativo,
            'convenio' => $this->whenLoaded('convenio', fn () => [
                'id' => $this->convenio->id,
                'nome' => $this->convenio->nome,
                'connector_driver' => $this->convenio->connector_driver,
                // A tela agrupa a carteirinha por estes blocos ao exibir.
                'carteirinha_blocos' => $this->convenio->blocosCarteirinha(),
            ]),
        ];
    }
}
