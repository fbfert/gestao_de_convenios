<?php

namespace App\Http\Resources;

use App\Services\Sessoes\FolhasDeRegistroService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PacienteArquivoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tipo' => $this->tipo,
            'nome_original' => $this->nome_original,
            'mime' => $this->mime,
            'url' => url("/api/pacientes/{$this->paciente_id}/arquivos/{$this->id}"),
            'created_at' => $this->created_at?->toISOString(),
            // Só na listagem da pasta, e só para folha de registro: a guia de
            // origem e se ela já travou a remoção (finalizada na operadora).
            'guia' => $this->when(
                $this->relationLoaded('guiaDaFolha') && $this->getRelation('guiaDaFolha') !== null,
                fn () => [
                    'id' => $this->getRelation('guiaDaFolha')->id,
                    'numero_guia' => $this->getRelation('guiaDaFolha')->numero_guia,
                    'folha_travada' => app(FolhasDeRegistroService::class)->travada($this->getRelation('guiaDaFolha')),
                ],
            ),
            'vinculos' => $this->whenLoaded('vinculos', fn () => $this->vinculos->map(fn ($vinculo) => [
                'id' => $vinculo->id,
                'solicitacao_id' => $vinculo->solicitacao_id,
                'solicitacao_item_id' => $vinculo->solicitacao_item_id,
                'travado' => $vinculo->estaTravado(),
            ])->values()),
        ];
    }
}
