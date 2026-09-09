<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estado da janela de silencio.
 *
 * `notificado_em` mora no proprio alerta, e nao numa tabela de log paralela:
 * uma tabela a parte exigiria limpeza propria e viraria uma segunda fonte de
 * verdade sobre o que ja foi avisado.
 *
 * `janela_silencio_horas` fica na regra porque e limiar, e limiar e dado
 * (ADR-03) — cada clinica decide de quanto em quanto tempo aceita ser
 * interrompida pela mesma coisa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alertas', function (Blueprint $table) {
            $table->timestamp('notificado_em')->nullable()->after('silenciado_ate');
        });

        Schema::table('alerta_regras', function (Blueprint $table) {
            $table->unsignedInteger('janela_silencio_horas')->default(24)->after('critica');
        });
    }

    public function down(): void
    {
        Schema::table('alertas', function (Blueprint $table) {
            $table->dropColumn('notificado_em');
        });

        Schema::table('alerta_regras', function (Blueprint $table) {
            $table->dropColumn('janela_silencio_horas');
        });
    }
};
