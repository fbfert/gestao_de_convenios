<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unimed_rda_credentials', function (Blueprint $table) {
            $table->timestamp('automation_paused_at')->nullable()->after('ativo');
            $table->string('automation_paused_reason')->nullable()->after('automation_paused_at');
        });
    }

    public function down(): void
    {
        Schema::table('unimed_rda_credentials', function (Blueprint $table) {
            $table->dropColumn(['automation_paused_at', 'automation_paused_reason']);
        });
    }
};
