<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lancamentos', function (Blueprint $table) {
            $table->time('hora_inicio')->nullable()->after('data_sessao');
            $table->time('hora_fim')->nullable()->after('hora_inicio');
            $table->string('acompanhante')->nullable()->after('hora_fim');
            $table->text('resumo_atividades')->nullable()->after('acompanhante');
            $table->longText('transcricao_bruta')->nullable()->after('resumo_atividades');
        });
    }

    public function down(): void
    {
        Schema::table('lancamentos', function (Blueprint $table) {
            $table->dropColumn([
                'hora_inicio',
                'hora_fim',
                'acompanhante',
                'resumo_atividades',
                'transcricao_bruta',
            ]);
        });
    }
};
