<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitacao_itens', function (Blueprint $table) {
            // Mesmo padrao de guias.unimed_next_check_at: quando o item fica
            // 'uncertain' pos-submit sem numero de guia, este timestamp guarda
            // a proxima janela em que EnfileirarConsultasUnimedDueJob pode
            // tentar confirmar de novo (ver ConfirmarGuiaIncertaUnimedService).
            $table->timestamp('unimed_verificacao_next_check_at')->nullable()->after('status_operacional');
        });
    }

    public function down(): void
    {
        Schema::table('solicitacao_itens', function (Blueprint $table) {
            $table->dropColumn('unimed_verificacao_next_check_at');
        });
    }
};
