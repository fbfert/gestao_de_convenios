<?php

namespace Database\Seeders;

use App\Models\Convenio;
use App\Models\SaudeComponente;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Componentes de saude padrao de cada tenant.
 *
 * A decisao menos obvia aqui e QUAIS nascem ativos. Um componente sem fonte
 * periodica de heartbeat cai para `down` e nunca mais sai de la — e como o
 * GET /api/health devolve 503 quando qualquer componente esta fora, isso
 * derrubaria o endpoint publico para sempre e cegaria o monitor externo da
 * fase 0. Card vermelho permanente e alarme que ninguem olha; endpoint 503
 * permanente e pior, porque quebra a unica vigilancia que existe hoje.
 *
 * Por isso so nasce ativo o que tem heartbeat periodico incondicional de fato:
 *
 * - `scheduler`  ATIVO   — carimba a cada minuto em routes/console.php.
 * - `queue`      inativo — nada carimba a fila hoje. Precisa de um job periodico
 *                          que so exista para provar que a fila desenfileira.
 * - `smtp`       inativo — o unico envio de e-mail do app e o botao de teste
 *                          manual. Ganha fonte periodica no digest da fase 5.
 * - `automacao.unimed` inativo — o worker so trabalha quando ha guia a consultar,
 *                          entao uma madrugada sem trabalho o derrubaria sem que
 *                          nada estivesse errado. Precisa de sonda periodica
 *                          contra o /health do proprio worker.
 *
 * Ativar cada um e mudar `ativo` para true depois que a fonte existir — e dado,
 * nao codigo.
 */
class SaudeComponenteSeeder extends Seeder
{
    public function run(): void
    {
        Tenant::query()->orderBy('id')->each(function (Tenant $tenant) {
            $unimed = Convenio::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('nome', 'Unimed')
                ->first();

            $padroes = [
                [
                    'chave' => SaudeComponente::CHAVE_SCHEDULER,
                    'nome' => 'Agendador',
                    'tipo' => 'scheduler',
                    'convenio_id' => null,
                    // Carimba a cada minuto; 120s absorve uma rodada atrasada por
                    // carga, e o corte de `down` cai em 6 minutos — na mesma
                    // ordem da tolerancia de 10 minutos do ADR-24.
                    'intervalo_esperado_segundos' => 120,
                    'ativo' => true,
                ],
                [
                    'chave' => SaudeComponente::CHAVE_FILA,
                    'nome' => 'Fila de processamento',
                    'tipo' => 'queue',
                    'convenio_id' => null,
                    'intervalo_esperado_segundos' => 300,
                    'ativo' => false,
                ],
                [
                    'chave' => SaudeComponente::CHAVE_SMTP,
                    'nome' => 'Envio de e-mail',
                    'tipo' => 'smtp',
                    'convenio_id' => null,
                    'intervalo_esperado_segundos' => 86400,
                    'ativo' => false,
                ],
            ];

            if ($unimed) {
                $padroes[] = [
                    'chave' => SaudeComponente::CHAVE_WORKER_UNIMED,
                    'nome' => 'Automação Unimed',
                    'tipo' => 'worker',
                    'convenio_id' => $unimed->id,
                    // As consultas sao enfileiradas a cada 30 minutos; 3600s da
                    // margem para um ciclo sem trabalho.
                    'intervalo_esperado_segundos' => 3600,
                    'ativo' => false,
                ];
            }

            foreach ($padroes as $padrao) {
                SaudeComponente::query()->withoutGlobalScopes()->firstOrCreate(
                    ['tenant_id' => $tenant->id, 'chave' => $padrao['chave']],
                    $padrao + ['tenant_id' => $tenant->id],
                );
            }
        });
    }
}
