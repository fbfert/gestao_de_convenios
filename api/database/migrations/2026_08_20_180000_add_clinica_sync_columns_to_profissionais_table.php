<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profissionais', function (Blueprint $table) {
            // Vínculo com o profissional correspondente no clinica.gestaonossa.com.br
            // (ver App\Services\ClinicaSync). Sem tabela de mapeamento separada: é 1:1.
            $table->unsignedBigInteger('clinica_id')->nullable()->after('percentual_repasse');
            // Igual a updated_at no INSTANTE em que a sync gravou por último — é o que
            // corta o loop de sincronização (ver ClinicaSyncService::@loop-prevention).
            $table->timestamp('sincronizado_em')->nullable()->after('clinica_id');

            $table->unique(['tenant_id', 'clinica_id']);
        });
    }

    public function down(): void
    {
        Schema::table('profissionais', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'clinica_id']);
            $table->dropColumn(['clinica_id', 'sincronizado_em']);
        });
    }
};
