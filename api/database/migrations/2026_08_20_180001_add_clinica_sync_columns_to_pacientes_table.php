<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pacientes', function (Blueprint $table) {
            // Mesmo padrão de profissionais — ver a migration irmã.
            $table->unsignedBigInteger('clinica_id')->nullable()->after('clinica_agil_id');
            $table->timestamp('sincronizado_em')->nullable()->after('clinica_id');
            // 'pendente_clinica': o paciente não tem os campos que o clinica exige
            // (nascimento/sexo/necessidade/responsável) para ser criado lá — ver
            // PacienteSyncService. Fica visível na tela sem precisar abrir o log da sync.
            $table->string('clinica_status', 20)->nullable()->after('sincronizado_em');

            $table->unique(['tenant_id', 'clinica_id']);
        });
    }

    public function down(): void
    {
        Schema::table('pacientes', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'clinica_id']);
            $table->dropColumn(['clinica_id', 'sincronizado_em', 'clinica_status']);
        });
    }
};
