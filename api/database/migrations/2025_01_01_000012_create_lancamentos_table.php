<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lancamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('antecipacao_id')->constrained('antecipacoes')->restrictOnDelete();
            $table->foreignId('profissional_id')->constrained('profissionais')->restrictOnDelete();
            $table->date('data_sessao');
            $table->enum('status', ['completed', 'missed', 'canceled'])->default('completed');
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'data_sessao']);
            $table->index(['antecipacao_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lancamentos');
    }
};
