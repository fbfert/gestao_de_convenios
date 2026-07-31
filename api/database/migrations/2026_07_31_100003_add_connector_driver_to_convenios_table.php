<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('convenios', function (Blueprint $table) {
            $table->string('connector_driver')->nullable()->after('connector_type');
            $table->index(['tenant_id', 'connector_driver']);
        });
    }

    public function down(): void
    {
        Schema::table('convenios', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'connector_driver']);
            $table->dropColumn('connector_driver');
        });
    }
};
