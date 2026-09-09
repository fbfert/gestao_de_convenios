<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quantas sessões a operadora autoriza POR GUIA.
 *
 * Campo novo, ao lado de `qtd_autorizada_por_ciclo` e sem tocá-lo. Os dois
 * parecem a mesma coisa e não são: `qtd_autorizada_por_ciclo` é uma TAXA,
 * emparelhada com `frequencia_lancamento` — o `ConvenioRegraSeeder` grava
 * "1 por dia" e "duas autorizações por dia", e o comentário em
 * `AntecipacaoService` diz que ele "descreve o ritmo de liberacao, nao o total
 * da guia". Reaproveitá-lo como quantidade padrão poria 1 onde a Unimed libera
 * 10 — um número errado configurável, que é pior que um número errado no código
 * porque parece decidido de propósito.
 *
 * Nullable, e nulo significa "não sabemos", não zero: sem valor, a quantidade
 * vem vazia e quem preenche é a pessoa. Nunca inventar número é o ponto — era
 * exatamente isso que o `?? 10` fazia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('convenio_regras', function (Blueprint $table) {
            $table->unsignedInteger('sessoes_por_guia')
                ->nullable()
                ->after('qtd_autorizada_por_ciclo');
        });
    }

    public function down(): void
    {
        Schema::table('convenio_regras', function (Blueprint $table) {
            $table->dropColumn('sessoes_por_guia');
        });
    }
};
