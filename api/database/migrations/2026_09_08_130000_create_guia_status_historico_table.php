<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historico de transicoes de status da guia.
 *
 * Tabela propria da guia, e nao polimorfica (`entidade`/`entidade_id`): a
 * polimorfica mata chave estrangeira e indice, que sao justamente o que faz esta
 * tabela servir para leitura. Solicitacao, Antecipacao e Conciliacao terao a
 * delas quando chegar a vez.
 *
 * Sem `updated_at`: linha de historico nao e editada, e `ocorrido_em` e o unico
 * tempo que importa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guia_status_historico', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('guia_id')->constrained('guias')->cascadeOnDelete();
            $table->string('de')->nullable(); // nulo na criacao da guia
            $table->string('para');
            $table->timestamp('ocorrido_em');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('origem'); // manual|automacao|importacao|migracao
            $table->text('motivo')->nullable();
            $table->timestamp('created_at')->nullable();

            // A linha do tempo de uma guia.
            $table->index(['tenant_id', 'guia_id', 'ocorrido_em']);
            // "quantas negadas hoje / na semana" — e o que o card do dashboard
            // consulta, e sem este indice viraria varredura a cada 30s.
            $table->index(['tenant_id', 'para', 'ocorrido_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guia_status_historico');
    }
};
