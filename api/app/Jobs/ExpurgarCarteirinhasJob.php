<?php

namespace App\Jobs;

use App\Models\ConfiguracaoGlobal;
use App\Models\PacienteDocumento;
use App\Models\Tenant;
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
 *
 * Roda por tenant, como o expurgo de auditoria, só pra poder respeitar o
 * liga/desliga configurável em /automacoes/configuracoes por clínica.
 */
class ExpurgarCarteirinhasJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        Tenant::query()->orderBy('id')->each(function (Tenant $tenant) {
            $ativo = ConfiguracaoGlobal::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->value('automacao_expurgo_carteirinhas_ativo');

            if ($ativo === false) {
                return;
            }

            PacienteDocumento::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('expira_em', '<', now())
                ->chunkById(200, function ($documentos) {
                    foreach ($documentos as $documento) {
                        if ($documento->path && Storage::disk('local')->exists($documento->path)) {
                            Storage::disk('local')->delete($documento->path);
                        }

                        $documento->delete();
                    }
                });
        });
    }
}
