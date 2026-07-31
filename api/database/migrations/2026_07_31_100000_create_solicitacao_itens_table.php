<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitacao_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('solicitacao_id')->constrained('solicitacoes')->cascadeOnDelete();
            $table->foreignId('especialidade_id')->constrained('especialidades')->restrictOnDelete();
            $table->foreignId('profissional_id')->constrained('profissionais')->restrictOnDelete();
            $table->unsignedInteger('quantidade')->default(10);
            $table->string('status_operacional')->default('pending');
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'solicitacao_id']);
            $table->index(['tenant_id', 'status_operacional']);
            $table->index(['tenant_id', 'profissional_id']);
        });

        DB::table('solicitacoes')
            ->select([
                'id',
                'tenant_id',
                'especialidade_id',
                'profissional_id',
                'created_at',
                'updated_at',
            ])
            ->orderBy('id')
            ->chunkById(200, function ($solicitacoes) {
                foreach ($solicitacoes as $solicitacao) {
                    $exists = DB::table('solicitacao_itens')
                        ->where('solicitacao_id', $solicitacao->id)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    DB::table('solicitacao_itens')->insert([
                        'tenant_id' => $solicitacao->tenant_id,
                        'solicitacao_id' => $solicitacao->id,
                        'especialidade_id' => $solicitacao->especialidade_id,
                        'profissional_id' => $solicitacao->profissional_id,
                        'quantidade' => 10,
                        'status_operacional' => 'pending',
                        'observacoes' => null,
                        'created_at' => $solicitacao->created_at ?? now(),
                        'updated_at' => $solicitacao->updated_at ?? now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitacao_itens');
    }
};
