<?php

namespace Tests\Feature;

use App\Models\AnaliticoUnimedLote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\Support\ConstroiAnaliticoUnimedXlsx;
use Tests\TestCase;

class AnaliticosApiTest extends TestCase
{
    use ConstroiAnaliticoUnimedXlsx;
    use RefreshDatabase;

    private const ARQUIVO = 'analitico-unimed-exemplo.xlsx';

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
            ->assertJsonPath('data.0.arquivo_nome_original', self::ARQUIVO)
            ->assertJsonPath('data.0.status', 'importado');

        $this->assertGreaterThan(0, (int) $response->json('data.0.total_linhas_analitico'));
        $this->assertGreaterThan(0, (int) $response->json('data.0.total_linhas_glosa'));

        $this->assertDatabaseCount('analitico_unimed_lotes', 1);

        $lote = AnaliticoUnimedLote::query()->firstOrFail();
        $this->assertSame(self::ARQUIVO, $lote->arquivo_nome_original);
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

        $response = $this->getJson("/api/analiticos?busca=analitico-unimed-exemplo&status=importado&importado_de={$de}&importado_ate={$ate}");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.arquivo_nome_original', self::ARQUIVO)
            ->assertJsonPath('data.0.status', 'importado');
    }

    private function autenticar(): User
    {
        $user = User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($user);

        return $user;
    }

    /**
     * Duas linhas pagas e uma glosada, montadas em memória.
     *
     * A versão anterior lia um `item3.3.xlsx` da raiz do projeto — um arquivo
     * nunca versionado, que saiu do git em c728b86 e depois sumiu do disco,
     * levando os três testes junto.
     */
    private function arquivoModeloAnalitico(): UploadedFile
    {
        $analitico = array_merge($this->cabecalhoAnaliticoUnimed(), [
            [4, [
                'A' => '50137394772',
                'B' => '50137394772',
                'C' => '1',
                'D' => 'BENEFICIARIO DE TESTE',
                'E' => '20/03/2026',
                'F' => '06/04/2026',
                'G' => '50000470',
                'H' => 'Procedimentos e eventos em saúde',
                'I' => 'SESSÃO DE PSICOTERAPIA INDIVIDUAL POR PSICÓLOGO',
                'J' => '1',
                'K' => '0,00',
                'L' => '0,00',
                'M' => '45,00',
                'N' => '45,00',
                'O' => '',
            ]],
            [5, [
                'A' => '50137394773',
                'B' => '50137394773',
                'C' => '2',
                'D' => 'OUTRO BENEFICIARIO DE TESTE',
                'E' => '21/03/2026',
                'F' => '07/04/2026',
                'G' => '50000470',
                'H' => 'Procedimentos e eventos em saúde',
                'I' => 'SESSÃO DE FISIOTERAPIA',
                'J' => '2',
                'K' => '0,00',
                'L' => '0,00',
                'M' => '90,00',
                'N' => '90,00',
                'O' => '',
            ]],
            [6, ['A' => 'TOTAL DO PRESTADOR', 'N' => '135,00']],
            [7, ['A' => 'TOTAL DO LOTE', 'N' => '135,00']],
        ]);

        $glosa = array_merge($this->cabecalhoGlosaUnimed(), [
            [2, [
                'A' => '50137394772',
                'B' => '50137394772',
                'C' => '1',
                'D' => 'BENEFICIARIO DE TESTE',
                'E' => '20/03/2026',
                'F' => '06/04/2026',
                'G' => '50000470',
                'H' => 'Procedimentos e eventos em saúde',
                'I' => 'SESSÃO DE PSICOTERAPIA INDIVIDUAL POR PSICÓLOGO',
                'J' => '1',
                'K' => '1.0',
                'L' => 'Cobranca de procedimento em duplicidade',
                'M' => '45,00',
                'N' => '',
            ]],
            [3, ['A' => 'TOTAL:', 'M' => '45,00']],
        ]);

        return $this->montarArquivoAnaliticoUnimed($analitico, $glosa, self::ARQUIVO);
    }
}
