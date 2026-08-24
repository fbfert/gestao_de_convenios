<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('codigo');
            $table->string('descricao');
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'ativo']);
            $table->unique(['tenant_id', 'codigo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cids');
    }
};
