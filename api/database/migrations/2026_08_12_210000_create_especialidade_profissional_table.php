<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Um profissional passa a atuar em várias especialidades.
 *
 * `profissionais.especialidade_id` continua existindo como a especialidade
 * **principal** — o registro de conselho da pessoa, e o padrão quando algum
 * fluxo precisa de uma só. A tabela nova diz em quais especialidades ela
 * atende. Invariante: a principal está sempre entre elas; quem garante é
 * Profissional::sincronizarEspecialidades().
 *
 * Por que nao dropar a coluna: oito arquivos de teste, o ProfissionalSeeder e
 * o DatabaseSeeder consultam por ela (`where('especialidade_id', ...)`), e o
 * UserResource a expoe. Remove-la agora trocaria a rede de seguranca desta
 * mudanca pela propria mudanca. Fica como limpeza futura.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('especialidade_profissional', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profissional_id')->constrained('profissionais')->cascadeOnDelete();
            $table->foreignId('especialidade_id')->constrained('especialidades')->cascadeOnDelete();
            $table->timestamps();

            // Nome explicito: o gerado pelo Laravel
            // (`especialidade_profissional_profissional_id_especialidade_id_unique`)
            // passa dos 64 caracteres que o MySQL aceita em identificador. O
            // sqlite dos testes engole, entao a falha so apareceria no deploy.
            $table->unique(['profissional_id', 'especialidade_id'], 'esp_prof_unico');
        });

        // Backfill: todo mundo comeca atuando na propria especialidade atual,
        // que e exatamente o comportamento de antes desta migration.
        $agora = now();

        DB::table('profissionais')
            ->whereNotNull('especialidade_id')
            ->orderBy('id')
            ->chunkById(500, function ($profissionais) use ($agora) {
                DB::table('especialidade_profissional')->insertOrIgnore(
                    $profissionais->map(fn ($profissional) => [
                        'profissional_id' => $profissional->id,
                        'especialidade_id' => $profissional->especialidade_id,
                        'created_at' => $agora,
                        'updated_at' => $agora,
                    ])->all()
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('especialidade_profissional');
    }
};
