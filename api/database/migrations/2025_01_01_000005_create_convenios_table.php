<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('convenios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('nome');
            $table->enum('connector_type', ['manual', 'api', 'scraping'])->default('manual');
            $table->json('connector_config')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'ativo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('convenios');
    }
};
