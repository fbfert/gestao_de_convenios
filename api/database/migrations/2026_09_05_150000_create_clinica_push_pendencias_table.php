<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Espelho de clinica_pacientes_pendentes, mas pro sentido PUSH: aqui o
 * registro local já existe (paciente ou profissional do gescon) e o
 * candidato é remoto — achado por nome parecido no clinica antes de criar
 * um cadastro novo lá (evita duplicar do lado de lá também).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinica_push_pendencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('tipo'); // 'paciente' | 'profissional'
            $table->unsignedBigInteger('local_id'); // Paciente.id ou Profissional.id, conforme `tipo`
            $table->json('candidatos_json'); // [{clinica_id, nome, similaridade}, ...]
            $table->string('status')->default('pendente'); // pendente|confirmado|rejeitado
            $table->unsignedBigInteger('clinica_id_escolhido')->nullable();
            $table->foreignId('resolvido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolvido_em')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'tipo', 'local_id']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinica_push_pendencias');
    }
};
