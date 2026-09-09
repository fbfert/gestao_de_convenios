<?php

namespace Tests\Feature;

use App\Models\Convenio;
use App\Models\ConvenioRegra;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A migração de dados que preenche `sessoes_por_guia` no deploy.
 *
 * O seeder só alcança base nova; em produção a regra vigente da Unimed já
 * existe com o campo nulo, e a partir do R6 nulo significa "não há quantidade
 * padrão" — a atendente levaria 422 no primeiro pedido depois do deploy.
 *
 * O teste roda a migração à mão sobre dados montados aqui, porque o
 * `RefreshDatabase` já a aplicou numa base onde ela não tinha o que fazer.
 */
class PreencherSessoesPorGuiaMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function migracao(): object
    {
        return require database_path(
            'migrations/2026_09_09_120000_preencher_sessoes_por_guia_dos_convenios_com_automacao.php'
        );
    }

    private function regra(Convenio $convenio, array $overrides = []): ConvenioRegra
    {
        return ConvenioRegra::query()->create(array_merge([
            'tenant_id' => $convenio->tenant_id,
            'convenio_id' => $convenio->id,
            'tipo_terapia' => 'especializada',
            'frequencia_lancamento' => 'diaria',
            'qtd_autorizada_por_ciclo' => 1,
            'sessoes_por_guia' => null,
            'validade_senha_dias' => 30,
            'vigente_desde' => today()->subMonth()->toDateString(),
            'vigente_ate' => null,
        ], $overrides));
    }

    private function convenio(string $nome, ?string $driver): Convenio
    {
        return Convenio::query()->create([
            'tenant_id' => Tenant::query()->where('slug', 'clinica-exemplo')->firstOrFail()->id,
            'nome' => $nome,
            'connector_type' => $driver ? 'scraping' : 'manual',
            'connector_driver' => $driver,
            'ativo' => true,
        ]);
    }

    public function test_preenche_a_regra_vigente_do_convenio_com_automacao(): void
    {
        $convenio = $this->convenio('Operadora Robô', 'unimed_rda');
        $regra = $this->regra($convenio);

        $this->migracao()->up();

        $this->assertSame(10, $regra->fresh()->sessoes_por_guia);
    }

    public function test_nao_toca_convenio_sem_automacao(): void
    {
        // Nulo ali é de propósito: é o caso "sem regra vigente" que o R6 trata,
        // e inventar um teto seria justamente o que o change tirou do código.
        $convenio = $this->convenio('Operadora Manual', null);
        $regra = $this->regra($convenio);

        $this->migracao()->up();

        $this->assertNull($regra->fresh()->sessoes_por_guia);
    }

    public function test_nao_toca_regra_ja_encerrada(): void
    {
        $convenio = $this->convenio('Operadora Robô 2', 'unimed_rda');
        $encerrada = $this->regra($convenio, [
            'vigente_desde' => today()->subYear()->toDateString(),
            'vigente_ate' => today()->subMonth()->toDateString(),
        ]);

        $this->migracao()->up();

        $this->assertNull($encerrada->fresh()->sessoes_por_guia);
    }

    public function test_nao_sobrescreve_valor_ja_cadastrado(): void
    {
        $convenio = $this->convenio('Operadora Robô 3', 'unimed_rda');
        $regra = $this->regra($convenio, ['sessoes_por_guia' => 6]);

        $this->migracao()->up();

        // Quem cadastrou 6 na tela tem a palavra final.
        $this->assertSame(6, $regra->fresh()->sessoes_por_guia);
    }

    public function test_rodar_duas_vezes_nao_muda_nada_na_segunda(): void
    {
        $convenio = $this->convenio('Operadora Robô 4', 'unimed_rda');
        $regra = $this->regra($convenio);

        $this->migracao()->up();
        $this->migracao()->up();

        $this->assertSame(10, $regra->fresh()->sessoes_por_guia);
        $this->assertSame(
            1,
            DB::table('convenio_regras')->where('convenio_id', $convenio->id)->count(),
        );
    }
}
