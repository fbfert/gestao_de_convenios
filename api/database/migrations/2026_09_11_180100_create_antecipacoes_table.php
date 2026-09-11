<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Histórico + fila de antecipações geradas manualmente (ver
 * App\Services\AntecipacaoService). A fila "Elegíveis" da tela não vem desta
 * tabela — é calculada ao vivo por Guia::elegiveisParaAntecipacao(), igual ao
 * alerta. Esta tabela só guarda o que a pessoa efetivamente iniciou: um
 * registro por solicitação-de-origem, criado como `pendente`, que vira
 * `gerada` quando a nova solicitação é salva, ou `ignorada` se descartado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('antecipacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('solicitacao_origem_id')->constrained('solicitacoes')->restrictOnDelete();
            $table->string('status')->default('pendente');
            $table->date('data_alvo')->nullable();
            // Snapshot de {especialidade_id, profissional_id} escolhidos na hora de gerar.
            $table->json('itens_selecionados')->nullable();
            $table->text('observacoes')->nullable();
            $table->foreignId('criado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('solicitacao_gerada_id')->nullable()->constrained('solicitacoes')->nullOnDelete();
            $table->timestamp('gerado_em')->nullable();
            $table->timestamp('ignorado_em')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('antecipacoes');
    }
};
