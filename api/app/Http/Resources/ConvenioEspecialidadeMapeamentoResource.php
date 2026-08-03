<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConvenioEspecialidadeMapeamentoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'convenio_id' => $this->convenio_id,
            'especialidade_id' => $this->especialidade_id,
            'codigo_procedimento' => $this->codigo_procedimento,
            'descricao_operadora' => $this->descricao_operadora,
            'quantidade_padrao' => $this->quantidade_padrao,
            'usa_descricao_generica' => $this->usa_descricao_generica,
            'valor_generico' => $this->valor_generico,
            'ativo' => $this->ativo,
            'convenio' => $this->whenLoaded('convenio', fn () => [
                'id' => $this->convenio->id,
                'nome' => $this->convenio->nome,
            ]),
            'especialidade' => $this->whenLoaded('especialidade', fn () => [
                'id' => $this->especialidade->id,
                'nome' => $this->especialidade->nome,
            ]),
        ];
    }
}
