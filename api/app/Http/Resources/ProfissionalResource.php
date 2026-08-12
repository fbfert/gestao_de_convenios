<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfissionalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'especialidade_id' => $this->especialidade_id,
            'conselho_registro' => $this->conselho_registro,
            'percentual_repasse' => $this->percentual_repasse,
            'ativo' => $this->ativo,
            'especialidade' => $this->whenLoaded('especialidade', fn () => [
                'id' => $this->especialidade->id,
                'nome' => $this->especialidade->nome,
            ]),
            // Todas em que atende, principal incluída. A tela usa esta lista
            // para saber quais profissionais oferecer em cada especialidade.
            'especialidades' => $this->whenLoaded(
                'especialidades',
                fn () => $this->especialidades
                    ->map(fn ($especialidade) => [
                        'id' => $especialidade->id,
                        'nome' => $especialidade->nome,
                    ])
                    ->values(),
            ),
            'especialidade_ids' => $this->whenLoaded(
                'especialidades',
                fn () => $this->especialidades->pluck('id')->values(),
            ),
        ];
    }
}
