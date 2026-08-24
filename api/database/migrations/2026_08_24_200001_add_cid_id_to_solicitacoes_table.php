<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitacoes', function (Blueprint $table) {
            $table->foreignId('cid_id')->nullable()->after('cid')->constrained('cids')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('solicitacoes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cid_id');
        });
    }
};
