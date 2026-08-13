<?php

namespace App\Jobs;

use App\Models\PacienteDocumento;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * Apaga as imagens de carteirinha vencidas.
 *
 * Diferente do expurgo da auditoria, aqui não se exporta nada antes: o objetivo
 * é justamente não guardar imagem de documento pessoal além do necessário.
 *
 * Pega também o que nunca teve dono — leitura em que o operador desistiu do
 * cadastro no meio.
 */
class ExpurgarCarteirinhasJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        PacienteDocumento::query()
            ->withoutGlobalScopes()
            ->where('expira_em', '<', now())
            ->chunkById(200, function ($documentos) {
                foreach ($documentos as $documento) {
                    if ($documento->path && Storage::disk('local')->exists($documento->path)) {
                        Storage::disk('local')->delete($documento->path);
                    }

                    $documento->delete();
                }
            });
    }
}
