<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('profissional_id')
                ->nullable()
                ->after('tenant_id')
                ->constrained('profissionais')
                ->nullOnDelete();

            $table->dropColumn('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('profissional_id');
            $table->enum('role', ['admin', 'operador'])->default('operador')->after('password');
        });
    }
};
