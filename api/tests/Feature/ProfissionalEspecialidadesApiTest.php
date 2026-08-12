<?php

namespace Tests\Feature;

use App\Models\Especialidade;
use App\Models\Profissional;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfissionalEspecialidadesApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_backfill_liga_todo_profissional_a_sua_especialidade_principal(): void
    {
        // A invariante vale para quem nasceu pelo seeder, nao so pela tela.
        Profissional::query()->with('especialidades')->get()->each(function (Profissional $profissional) {
            $this->assertTrue(
                $profissional->especialidades->contains('id', $profissional->especialidade_id),
                "Profissional {$profissional->nome} nao esta ligado a propria especialidade principal.",
            );
        });
    }

    public function test_cria_profissional_atuando_em_varias_especialidades(): void
    {
        $this->autenticar();

        $principal = Especialidade::query()->where('nome', 'Fisioterapia')->firstOrFail();
        $extra = Especialidade::query()->where('nome', 'Fonoaudiologia')->firstOrFail();

        $resposta = $this->postJson('/api/profissionais', [
            'nome' => 'Dra. Multi Especialidade',
            'especialidade_id' => $principal->id,
            'especialidade_ids' => [$extra->id],
            'percentual_repasse' => 50,
        ])->assertCreated();

        $this->assertEqualsCanonicalizing(
            [$principal->id, $extra->id],
            $resposta->json('data.especialidade_ids'),
        );
    }

    public function test_principal_entra_mesmo_fora_da_lista_enviada(): void
    {
        $this->autenticar();

        $principal = Especialidade::query()->where('nome', 'Fisioterapia')->firstOrFail();
        $extra = Especialidade::query()->where('nome', 'Fonoaudiologia')->firstOrFail();

        // Lista sem a principal: um profissional que nao atende na propria
        // especialidade de registro seria um estado incoerente.
        $resposta = $this->postJson('/api/profissionais', [
            'nome' => 'Dra. Sem Principal Na Lista',
            'especialidade_id' => $principal->id,
            'especialidade_ids' => [$extra->id],
        ])->assertCreated();

        $this->assertContains($principal->id, $resposta->json('data.especialidade_ids'));
    }

    public function test_filtro_por_especialidade_acha_quem_atua_sem_ser_a_principal(): void
    {
        $this->autenticar();

        $principal = Especialidade::query()->where('nome', 'Fisioterapia')->firstOrFail();
        $extra = Especialidade::query()->where('nome', 'Fonoaudiologia')->firstOrFail();

        $this->postJson('/api/profissionais', [
            'nome' => 'Dra. Atua Nas Duas',
            'especialidade_id' => $principal->id,
            'especialidade_ids' => [$extra->id],
        ])->assertCreated();

        $nomes = collect(
            $this->getJson('/api/profissionais?especialidade_id='.$extra->id)->assertOk()->json('data')
        )->pluck('nome');

        // Antes o filtro olhava a coluna: quem atendia na especialidade sem
        // que ela fosse a principal simplesmente nao aparecia.
        $this->assertContains('Dra. Atua Nas Duas', $nomes->all());
    }

    public function test_edicao_troca_as_especialidades_mantendo_a_principal(): void
    {
        $this->autenticar();

        $principal = Especialidade::query()->where('nome', 'Fisioterapia')->firstOrFail();
        $extra = Especialidade::query()->where('nome', 'Fonoaudiologia')->firstOrFail();
        $outra = Especialidade::query()
            ->whereNotIn('id', [$principal->id, $extra->id])
            ->firstOrFail();

        $id = $this->postJson('/api/profissionais', [
            'nome' => 'Dra. Vai Trocar',
            'especialidade_id' => $principal->id,
            'especialidade_ids' => [$extra->id],
        ])->assertCreated()->json('data.id');

        $resposta = $this->patchJson("/api/profissionais/{$id}", [
            'nome' => 'Dra. Vai Trocar',
            'especialidade_id' => $principal->id,
            'especialidade_ids' => [$outra->id],
        ])->assertOk();

        $this->assertEqualsCanonicalizing(
            [$principal->id, $outra->id],
            $resposta->json('data.especialidade_ids'),
        );
    }

    private function autenticar(): void
    {
        Sanctum::actingAs(
            User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail()
        );
    }
}
