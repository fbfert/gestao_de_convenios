<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manuais', function (Blueprint $table) {
            $table->string('tipo')->default('manual')->after('tenant_id');
        });

        Schema::table('manuais', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropUnique(['tenant_id']);
        });

        Schema::table('manuais', function (Blueprint $table) {
            $table->unique(['tenant_id', 'tipo']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('manuais', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropUnique(['tenant_id', 'tipo']);
        });

        Schema::table('manuais', function (Blueprint $table) {
            $table->unique('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->dropColumn('tipo');
        });
    }
};
