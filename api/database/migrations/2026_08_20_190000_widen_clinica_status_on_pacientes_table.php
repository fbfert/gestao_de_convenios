<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pacientes', function (Blueprint $table) {
            // A mensagem de pendência (motivo legível) não cabia em 20 chars — descoberto
            // no primeiro teste real da sync em 20/08/2026.
            $table->text('clinica_status')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('pacientes', function (Blueprint $table) {
            $table->string('clinica_status', 20)->nullable()->change();
        });
    }
};
