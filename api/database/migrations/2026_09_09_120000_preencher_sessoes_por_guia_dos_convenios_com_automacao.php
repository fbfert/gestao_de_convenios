<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Preenche `sessoes_por_guia` das regras VIGENTES dos convênios com automação.
 *
 * O `ConvenioRegraSeeder` só alcança base nova. Em produção a regra vigente da
 * Unimed já existe com o campo nulo, e a partir do R6 nulo significa "não há
 * quantidade padrão" — a atendente levaria 422 no primeiro pedido depois do
 * deploy. Deploy tem que ser autocontido: isto não pode virar linha de runbook.
 *
 * SÓ `unimed_rda`. Convênio sem automação continua nulo de propósito: é o caso
 * "sem regra vigente" que o R6 trata, e inventar um teto para eles seria
 * exatamente o que o change tirou do código.
 *
 * O 10 aqui é DADO de uma migração pontual, não regra no código: a partir da
 * próxima alteração quem manda é a tela de Convênios > Regras.
 */
return new class extends Migration
{
    private const SESSOES_POR_GUIA_UNIMED = 10;

    public function up(): void
    {
        $hoje = now()->toDateString();

        DB::table('convenio_regras')
            ->whereNull('sessoes_por_guia')
            ->whereDate('vigente_desde', '<=', $hoje)
            ->where(fn ($query) => $query
                ->whereNull('vigente_ate')
                ->orWhereDate('vigente_ate', '>=', $hoje))
            ->whereIn('convenio_id', function ($query) {
                $query->select('id')
                    ->from('convenios')
                    ->where('connector_driver', 'unimed_rda');
            })
            ->update(['sessoes_por_guia' => self::SESSOES_POR_GUIA_UNIMED]);
    }

    /**
     * Volta a nulo apenas o que esta migração poderia ter escrito.
     *
     * Não dá para distinguir o valor que ela gravou de um que alguém cadastrou
     * depois pela tela; o `where` pelo valor exato limita o estrago ao mínimo,
     * e desfazer um preenchimento é reversível na própria tela.
     */
    public function down(): void
    {
        DB::table('convenio_regras')
            ->where('sessoes_por_guia', self::SESSOES_POR_GUIA_UNIMED)
            ->whereIn('convenio_id', function ($query) {
                $query->select('id')
                    ->from('convenios')
                    ->where('connector_driver', 'unimed_rda');
            })
            ->update(['sessoes_por_guia' => null]);
    }
};
