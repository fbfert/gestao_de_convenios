<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_openai_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->text('api_key')->nullable();
            $table->string('base_url')->default('https://api.openai.com/v1');
            $table->string('organization_id')->nullable();
            $table->string('project_id')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_openai_settings');
    }
};
