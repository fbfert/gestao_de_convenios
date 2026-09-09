<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Liga o item de renovação ao item de ORIGEM da cadeia.
 *
 * A Unimed libera 10 sessões por guia; paciente que precisa de 20 no mês pede
 * duas vezes a mesma especialidade com o mesmo profissional, sob o mesmo pedido
 * médico. Sem o vínculo, somar o que já foi pedido no ciclo teria de agrupar
 * por especialidade + profissional — e isso contaria junto duas terapias
 * legitimamente separadas.
 *
 * Aponta para a origem, e não para o item anterior: com todos apontando para o
 * primeiro, somar a cadeia é uma query; em árvore, seria recursão.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitacao_itens', function (Blueprint $table) {
            $table->foreignId('renovacao_de_item_id')
                ->nullable()
                ->after('profissional_id')
                ->constrained('solicitacao_itens')
                // Nunca em cascata: apagar a origem não pode levar junto as
                // renovações, que são sessões pedidas de verdade.
                ->nullOnDelete();

            $table->index(['tenant_id', 'renovacao_de_item_id'], 'sol_itens_tenant_renovacao_index');
        });
    }

    public function down(): void
    {
        Schema::table('solicitacao_itens', function (Blueprint $table) {
            $table->dropIndex('sol_itens_tenant_renovacao_index');
            $table->dropConstrainedForeignId('renovacao_de_item_id');
        });
    }
};
