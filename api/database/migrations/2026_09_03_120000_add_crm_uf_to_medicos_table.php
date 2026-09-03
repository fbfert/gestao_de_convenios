<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medicos', function (Blueprint $table) {
            $table->string('crm')->nullable()->change();
            $table->string('crm_uf', 2)->nullable()->after('crm');
        });
    }

    public function down(): void
    {
        Schema::table('medicos', function (Blueprint $table) {
            $table->dropColumn('crm_uf');
            $table->string('crm')->nullable(false)->change();
        });
    }
};
