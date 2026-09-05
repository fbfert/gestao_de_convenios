<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fila de revisão humana para pacientes vindos da clinica que não bateram
 * por clinica_id nem CPF exatos, mas têm nome parecido (~90%) com alguém já
 * cadastrado no gescon sem vínculo. Existe pra nunca criar um Paciente
 * duplicado sozinho quando pode ser a mesma pessoa — ver
 * PacienteSyncService::pullUm().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinica_pacientes_pendentes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->unsignedBigInteger('clinica_id');
            $table->json('dados_remoto');
            $table->timestamp('remoto_atualizado_em');
            $table->string('status')->default('pendente');
            $table->foreignId('candidato_paciente_id')->nullable()->constrained('pacientes')->nullOnDelete();
            $table->unsignedTinyInteger('similaridade')->nullable();
            $table->json('candidatos_json')->nullable();
            $table->foreignId('resolvido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolvido_em')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'clinica_id']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinica_pacientes_pendentes');
    }
};
