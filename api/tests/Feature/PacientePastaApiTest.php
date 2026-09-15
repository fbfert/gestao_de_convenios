<?php

namespace Tests\Feature;

use App\Models\Convenio;
use App\Models\Especialidade;
use App\Models\Guia;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\Tenant;
use App\Models\User;
use App\Services\GuiaService;
use App\Support\GuiaStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A pasta reúne, numa requisição, tudo que existe sobre o paciente.
 *
 * Um endpoint só, e não cinco chamadas às listagens: as seções abrem
 * recolhidas mostrando a contagem, então a tela precisa dos totais antes de
 * qualquer expansão.
 */
class PacientePastaApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_pasta_traz_as_cinco_listas_do_paciente(): void
    {
        $this->autenticar();

        $paciente = Paciente::query()->firstOrFail();

        $corpo = $this->getJson("/api/pacientes/{$paciente->id}/pasta")
            ->assertOk()
            ->json('data');

        $this->assertSame($paciente->id, $corpo['paciente']['id']);
        $this->assertSame(
            ['paciente', 'solicitacoes', 'guias', 'sessoes', 'antecipacoes', 'arquivos'],
            array_keys($corpo),
        );
    }

    /** Sessões chegam pela guia — `lancamentos` não tem `paciente_id`. */
    public function test_pasta_traz_guias_e_sessoes_do_paciente(): void
    {
        $this->autenticar();

        $payload = $this->payloadGuia();
        $guiaId = $this->postJson('/api/guias', $payload)->assertCreated()->json('data.id');

        $guia = Guia::query()->findOrFail($guiaId);
        $guia->forceFill(['sessoes_autorizadas' => 2])->save();
        app(GuiaService::class)->registrarTransicao($guia, GuiaStatus::APPROVED);

        $this->postJson("/api/guias/{$guiaId}/lancamentos", [
            'profissional_id' => $payload['profissional_id'],
            'data_sessao' => today()->toDateString(),
        ])->assertCreated();

        $corpo = $this->getJson("/api/pacientes/{$payload['paciente_id']}/pasta")
            ->assertOk()
            ->json('data');

        $this->assertSame($guiaId, $corpo['guias'][0]['id']);
        $this->assertSame(1, $corpo['guias'][0]['lancamentos_count']);
        $this->assertCount(1, $corpo['sessoes']);
        $this->assertSame($guiaId, $corpo['sessoes'][0]['guia_id']);
    }

    /**
     * A folha de registro de sessões era exigida pela regional 0220, conferida
     * e descartada — nada a gravava. Agora entra na pasta como arquivo do
     * paciente, amarrada à guia que originou a remessa.
     */
    public function test_pasta_lista_o_registro_de_sessoes(): void
    {
        Storage::fake('local');

        $this->autenticar();

        $paciente = Paciente::query()->firstOrFail();

        $this->post("/api/pacientes/{$paciente->id}/arquivos", [
            'tipo' => 'registro_sessoes',
            'arquivo' => UploadedFile::fake()->create('folha.pdf', 32, 'application/pdf'),
        ])->assertCreated();

        $arquivos = $this->getJson("/api/pacientes/{$paciente->id}/pasta")
            ->assertOk()
            ->json('data.arquivos');

        $this->assertSame('registro_sessoes', $arquivos[0]['tipo']);
        $this->assertSame('folha.pdf', $arquivos[0]['nome_original']);
    }

    public function test_paciente_de_outra_clinica_nao_tem_pasta(): void
    {
        $this->autenticar();

        $tenant = Tenant::query()->create([
            'nome' => 'Clínica Vizinha Pasta',
            'slug' => 'clinica-vizinha-pasta',
            'cnpj' => '22.222.222/0001-22',
            'ativo' => true,
        ]);

        $convenio = Convenio::query()->create([
            'tenant_id' => $tenant->id,
            'nome' => 'Convênio Vizinho Pasta',
            'connector_type' => 'manual',
            'ativo' => true,
        ]);

        $alheio = Paciente::query()->create([
            'tenant_id' => $tenant->id,
            'convenio_id' => $convenio->id,
            'nome' => 'Paciente Vizinho Pasta',
            'carteirinha' => 'VIZ-PASTA-1',
            'ativo' => true,
        ]);

        $this->getJson("/api/pacientes/{$alheio->id}/pasta")->assertNotFound();
    }

    public function test_profissional_nao_alcanca_a_pasta(): void
    {
        $user = User::query()->where('email', 'profissional@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($user);

        $paciente = Paciente::query()->firstOrFail();

        $this->getJson("/api/pacientes/{$paciente->id}/pasta")->assertForbidden();
    }

    private function autenticar(): User
    {
        $user = User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($user);

        return $user;
    }

    /** @return array<string, mixed> */
    private function payloadGuia(): array
    {
        $convenio = Convenio::query()->where('nome', 'Unimed')->firstOrFail();
        $especialidade = Especialidade::query()->where('nome', 'Fisioterapia')->firstOrFail();
        $profissional = Profissional::query()
            ->where('especialidade_id', $especialidade->id)->firstOrFail();
        $paciente = Paciente::query()->where('convenio_id', $convenio->id)->firstOrFail();

        return [
            'solicitacao_id' => null,
            'convenio_id' => $convenio->id,
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'especialidade_id' => $especialidade->id,
            'numero_guia' => 'PASTA-'.uniqid(),
            'tipo_terapia' => 'especializada',
            'data_solicitacao' => today()->toDateString(),
        ];
    }
}
