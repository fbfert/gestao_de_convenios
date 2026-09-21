<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A guia já estava finalizada no portal da Unimed antes de a automação existir.
 *
 * Duas datas, e não um booleano, porque há TRÊS estados e um booleano só
 * distingue dois:
 *
 * - ambas nulas .................. nunca conferida
 * - só `conferida_...` ........... conferida, e não estava finalizada lá
 * - as duas preenchidas .......... conferida e confirmada como finalizada
 *
 * É o segundo caso que o lote precisa enxergar para não reconferir tudo do
 * zero a cada varredura, e que o operador precisa para saber que a guia já foi
 * olhada.
 *
 * Nenhuma das duas toca o `status` da guia — ver
 * openspec/changes/conferir-guias-finalizadas-unimed/design.md, decisão 1:
 * `finalized` faz a guia aceitar lançamento de sessão, e essas guias antigas
 * não têm sessão nenhuma registrada aqui.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guias', function (Blueprint $table) {
            $table->timestamp('finalizada_na_operadora_em')->nullable()->after('data_finalizacao');
            $table->timestamp('conferida_na_operadora_em')->nullable()->after('finalizada_na_operadora_em');

            // O lote busca por "ainda não conferida" dentro do tenant; sem
            // índice isso vira varredura da tabela inteira de guias.
            $table->index(['tenant_id', 'conferida_na_operadora_em'], 'guias_tenant_conferida_idx');
        });
    }

    public function down(): void
    {
        Schema::table('guias', function (Blueprint $table) {
            $table->dropIndex('guias_tenant_conferida_idx');
            $table->dropColumn(['finalizada_na_operadora_em', 'conferida_na_operadora_em']);
        });
    }
};
