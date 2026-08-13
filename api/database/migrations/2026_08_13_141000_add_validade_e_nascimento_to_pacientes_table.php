<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pacientes', function (Blueprint $table) {
            // Ambas saem da leitura da carteirinha, e ambas seguem opcionais:
            // nem toda carteirinha traz as duas, e o cadastro manual não pode
            // depender disso.
            $table->date('validade_carteirinha')->nullable()->after('carteirinha');
            $table->date('data_nascimento')->nullable()->after('cpf');
        });
    }

    public function down(): void
    {
        Schema::table('pacientes', function (Blueprint $table) {
            $table->dropColumn(['validade_carteirinha', 'data_nascimento']);
        });
    }
};
