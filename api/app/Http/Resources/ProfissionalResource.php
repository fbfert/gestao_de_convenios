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
            'percentual_repasse' => $this->percentual_repasse,
            'especialidade' => $this->whenLoaded('especialidade', fn () => [
                'id' => $this->especialidade->id,
                'nome' => $this->especialidade->nome,
            ]),
        ];
    }
}
