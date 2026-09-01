<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conciliacao_import_lotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('arquivo_nome_original');
            $table->string('arquivo_path')->nullable();
            $table->string('status')->default('previsualizado');
            $table->timestamp('confirmado_em')->nullable();
            $table->unsignedInteger('total_linhas')->default(0);
            $table->unsignedInteger('total_validas')->default(0);
            $table->unsignedInteger('total_invalidas')->default(0);
            $table->unsignedInteger('total_importados')->default(0);
            $table->unsignedInteger('total_atualizados')->default(0);
            $table->unsignedInteger('total_ignorados')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conciliacao_import_lotes');
    }
};
