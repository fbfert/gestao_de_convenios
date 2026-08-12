<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Formato da carteirinha por convênio, como lista de tamanhos de bloco.
 * Ex.: Unimed = [4, 4, 6, 2, 1] (17 dígitos).
 *
 * Antes o formato estava amarrado a `connector_driver === 'unimed_rda'`, que é
 * o interruptor de toda a automação Unimed: doze pontos do código dependem
 * dele, incluindo o roteamento de guias e o job que consulta o portal. Quem
 * quisesse apenas a máscara de digitação teria que ligar a automação junto.
 *
 * São coisas diferentes: "como se escreve a carteirinha" é característica do
 * convênio, "qual automação usar" é decisão de integração. Separar também
 * atende o ADR-03 — regra de convênio é dado, não código: um formato novo se
 * cadastra pela tela, sem deploy.
 *
 * `null` = texto livre, o comportamento de todos os convênios hoje.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('convenios', function (Blueprint $table) {
            $table->json('carteirinha_blocos')->nullable()->after('connector_driver');
        });
    }

    public function down(): void
    {
        Schema::table('convenios', function (Blueprint $table) {
            $table->dropColumn('carteirinha_blocos');
        });
    }
};
