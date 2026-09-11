<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Correção de desenho: antecipação não gera mais uma Solicitação nova — gera
 * itens novos (renovação, `SolicitacaoItem.renovacao_de_item_id`) na MESMA
 * solicitação, prontos pra virar guia e entrar na automação. `solicitacao_gerada_id`
 * nunca chegou a ser usado em produção (a tabela foi criada minutos antes
 * desta correção) e não faz mais sentido no novo desenho.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('antecipacoes', function (Blueprint $table) {
            $table->dropForeign(['solicitacao_gerada_id']);
            $table->dropColumn('solicitacao_gerada_id');
        });
    }

    public function down(): void
    {
        Schema::table('antecipacoes', function (Blueprint $table) {
            $table->foreignId('solicitacao_gerada_id')->nullable()->after('criado_por_id')
                ->constrained('solicitacoes')->nullOnDelete();
        });
    }
};
