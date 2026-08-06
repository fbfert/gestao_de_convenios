<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EspecialidadeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'ativo' => $this->ativo,
            // Só vem preenchido quando a listagem é pedida com ?convenio_id=, porque o
            // código do procedimento é definido por convênio, não pela especialidade.
            'codigo_procedimento' => $this->codigoProcedimento((int) $request->integer('convenio_id')),
        ];
    }

    private function codigoProcedimento(int $convenioId): ?string
    {
        if (! $convenioId || ! $this->resource->relationLoaded('convenioMapeamentos')) {
            return null;
        }

        return $this->convenioMapeamentos
            ->first(fn ($mapeamento) => $mapeamento->convenio_id === $convenioId && $mapeamento->ativo)
            ?->codigo_procedimento;
    }
}
