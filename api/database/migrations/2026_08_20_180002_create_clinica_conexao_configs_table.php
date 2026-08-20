<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinica_conexao_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('base_url');
            // Token pessoal (Sanctum) da conta de integração no clinica — nunca a senha
            // de um usuário humano, para poder ser revogado sem afetar login nenhum.
            $table->text('token');
            $table->boolean('ativo')->default(true);
            $table->timestamp('ultima_execucao_em')->nullable();
            $table->string('ultima_execucao_status', 20)->nullable();
            $table->timestamps();

            $table->unique('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinica_conexao_configs');
    }
};
