<?php

namespace Database\Seeders;

use App\Models\Alerta;
use App\Models\AlertaRegra;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Regras padrao de um tenant.
 *
 * Os limiares nascem aqui e passam a ser DADO: a clinica ajusta pela tela sem
 * deploy, que e a regra de ouro do projeto (ADR-03).
 *
 * `critica` marca quem vai disparar notificacao imediata na fase de
 * notificacoes. Guia negada e componente fora sao criticas; senha vencendo nao,
 * porque tem dias de antecedencia e cabe no resumo diario.
 */
class AlertaRegraSeeder extends Seeder
{
    public function run(): void
    {
        $padroes = [
            [
                'chave' => AlertaRegra::CHAVE_SENHA_VENCENDO,
                'nivel_base' => Alerta::NIVEL_AMARELO,
                'limiar_amarelo' => null,
                // Dias para o vencimento abaixo dos quais vira vermelho.
                'limiar_vermelho' => 2,
                'critica' => false,
            ],
            [
                'chave' => AlertaRegra::CHAVE_GUIA_NEGADA,
                'nivel_base' => Alerta::NIVEL_VERMELHO,
                'limiar_amarelo' => null,
                'limiar_vermelho' => null,
                'critica' => true,
            ],
            [
                'chave' => AlertaRegra::CHAVE_AUTOMACAO_FALHAS,
                'nivel_base' => Alerta::NIVEL_VERMELHO,
                'limiar_amarelo' => null,
                // Falhas consecutivas da mesma operacao.
                'limiar_vermelho' => 3,
                'critica' => true,
            ],
            [
                'chave' => AlertaRegra::CHAVE_COMPONENTE_FORA,
                'nivel_base' => Alerta::NIVEL_VERMELHO,
                'limiar_amarelo' => null,
                'limiar_vermelho' => null,
                'critica' => true,
            ],
        ];

        Tenant::query()->orderBy('id')->each(function (Tenant $tenant) use ($padroes) {
            foreach ($padroes as $padrao) {
                AlertaRegra::query()->withoutGlobalScopes()->firstOrCreate(
                    ['tenant_id' => $tenant->id, 'chave' => $padrao['chave']],
                    $padrao + ['tenant_id' => $tenant->id, 'ativo' => true],
                );
            }
        });
    }
}
