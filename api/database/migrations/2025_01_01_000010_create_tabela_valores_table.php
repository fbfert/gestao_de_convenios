<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tabela_valores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('convenio_id')->constrained('convenios')->restrictOnDelete();
            $table->foreignId('especialidade_id')->nullable()->constrained('especialidades')->restrictOnDelete();
            $table->foreignId('profissional_id')->nullable()->constrained('profissionais')->restrictOnDelete();
            $table->decimal('valor', 10, 2);
            $table->date('vigente_desde');
            $table->date('vigente_ate')->nullable();
            $table->timestamps();

            // Suporte direto à cascata do ADR-07 (convenio -> +especialidade -> +profissional)
            $table->index(['convenio_id', 'especialidade_id', 'profissional_id', 'vigente_desde'], 'tv_conv_esp_prof_vig_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tabela_valores');
    }
};
