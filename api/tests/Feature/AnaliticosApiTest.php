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

    private function autenticar(): void
    {
        $user = User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($user);
    }

    private function arquivoModeloAnalitico(): UploadedFile
    {
        $path = dirname(base_path()).DIRECTORY_SEPARATOR.'item3.3.xlsx';

        return UploadedFile::fake()->createWithContent('item3.3.xlsx', file_get_contents($path));
    }
}
