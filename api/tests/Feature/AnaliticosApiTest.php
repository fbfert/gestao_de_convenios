<?php

namespace Tests\Feature;

use App\Models\AnaliticoUnimedLote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AnaliticosApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_lista_lotes_importados_apos_importacao_do_analitico(): void
    {
        $this->autenticar();

        $arquivo = $this->arquivoModeloAnalitico();

        $this->post('/api/lancamentos/importar-analitico', [
            'arquivo' => $arquivo,
        ])->assertOk();

        $response = $this->getJson('/api/analiticos');

        $response->assertOk()
            ->assertJsonPath('data.0.arquivo_nome_original', 'item3.3.xlsx')
            ->assertJsonPath('data.0.status', 'importado');

        $this->assertGreaterThan(0, (int) $response->json('data.0.total_linhas_analitico'));
        $this->assertGreaterThan(0, (int) $response->json('data.0.total_linhas_glosa'));

        $this->assertDatabaseCount('analitico_unimed_lotes', 1);

        $lote = AnaliticoUnimedLote::query()->firstOrFail();
        $this->assertSame('item3.3.xlsx', $lote->arquivo_nome_original);
    }

    public function test_exibe_detalhe_do_lote_importado(): void
    {
        $this->autenticar();

        $arquivo = $this->arquivoModeloAnalitico();

        $this->post('/api/lancamentos/importar-analitico', [
            'arquivo' => $arquivo,
        ])->assertOk();

        $lote = AnaliticoUnimedLote::query()->firstOrFail();

        $this->getJson("/api/analiticos/{$lote->id}")
            ->assertOk()
            ->assertJsonPath('data.lote.id', $lote->id)
            ->assertJsonPath('data.analitico.linhas.0.origem', 'analitico')
            ->assertJsonPath('data.glosas.linhas.0.origem', 'glosa')
            ->assertJsonPath('data.conciliacao.totais.pago', $lote->total_pago);
    }

    public function test_filtra_lotes_importados_por_texto_status_e_periodo(): void
    {
        $user = $this->autenticar();

        $arquivo = $this->arquivoModeloAnalitico();

        $this->post('/api/lancamentos/importar-analitico', [
            'arquivo' => $arquivo,
        ])->assertOk();

        AnaliticoUnimedLote::query()->create([
            'tenant_id' => $user->tenant_id,
            'arquivo_nome_original' => 'arquivo-antigo.xlsx',
            'arquivo_path' => 'analiticos/arquivo-antigo.xlsx',
            'status' => 'pendente',
            'importado_em' => now()->subMonths(6)->startOfMonth(),
            'total_linhas_analitico' => 0,
            'total_linhas_glosa' => 0,
            'total_linhas_conciliacao' => 0,
            'total_pago' => 0,
            'total_glosado' => 0,
            'saldo_total' => 0,
        ]);

        // A janela acompanha o mês corrente: o lote recém-importado usa now(), então
        // datas fixas faziam o teste passar só no mês em que foi escrito.
        $de = now()->startOfMonth()->toDateString();
        $ate = now()->endOfMonth()->toDateString();

        $response = $this->getJson("/api/analiticos?busca=item3.3&status=importado&importado_de={$de}&importado_ate={$ate}");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.arquivo_nome_original', 'item3.3.xlsx')
            ->assertJsonPath('data.0.status', 'importado');
    }

    private function autenticar(): User
    {
        $user = User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($user);

        return $user;
    }

    private function arquivoModeloAnalitico(): UploadedFile
    {
        $path = dirname(base_path()).DIRECTORY_SEPARATOR.'item3.3.xlsx';

        return UploadedFile::fake()->createWithContent('item3.3.xlsx', file_get_contents($path));
    }
}
