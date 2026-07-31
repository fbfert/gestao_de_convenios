<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guias', function (Blueprint $table) {
            $table->string('unimed_status')->nullable()->after('status');
            $table->timestamp('unimed_last_checked_at')->nullable()->after('unimed_status');
            $table->timestamp('unimed_next_check_at')->nullable()->after('unimed_last_checked_at');

            $table->index(['tenant_id', 'unimed_next_check_at']);
        });
    }

    public function down(): void
    {
        Schema::table('guias', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'unimed_next_check_at']);
            $table->dropColumn(['unimed_status', 'unimed_last_checked_at', 'unimed_next_check_at']);
        });
    }
};
