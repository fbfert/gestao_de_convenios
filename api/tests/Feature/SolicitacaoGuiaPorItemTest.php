<?php

namespace Tests\Feature;

use App\Models\Convenio;
use App\Models\Especialidade;
use App\Models\Guia;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\Solicitacao;
use App\Models\User;
use App\Support\GuiaStatus;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SolicitacaoGuiaPorItemTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private int $tenantId;

    private function autenticar(): User
    {
        $usuario = User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail();

        Sanctum::actingAs($usuario);
        TenantContext::set((int) $usuario->tenant_id);
        app(PermissionRegistrar::class)->setPermissionsTeamId((int) $usuario->tenant_id);
        $this->tenantId = (int) $usuario->tenant_id;

        return $usuario;
    }

    /** Uma solicitação com dois itens; o primeiro ganha guia, o segundo não. */
    private function solicitacaoComDoisItens(?string $numeroGuia): Solicitacao
    {
        $convenio = Convenio::query()->where('nome', 'Unimed')->firstOrFail();
        $fisio = Especialidade::query()->where('nome', 'Fisioterapia')->firstOrFail();
        $outra = Especialidade::query()->where('nome', '!=', 'Fisioterapia')->firstOrFail();
        $profissional = Profissional::query()->where('especialidade_id', $fisio->id)->firstOrFail();
        $paciente = Paciente::query()->firstOrFail();

        $solicitacao = Solicitacao::query()->create([
            'tenant_id' => $this->tenantId,
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'especialidade_id' => $fisio->id,
            'convenio_id' => $convenio->id,
            'status' => 'under_review',
            'solicitado_em' => today()->toDateString(),
        ]);

        $comGuia = $solicitacao->itens()->create([
            'tenant_id' => $this->tenantId,
            'especialidade_id' => $fisio->id,
            'profissional_id' => $profissional->id,
            'quantidade' => 10,
            'status_operacional' => 'pending',
        ]);

        $solicitacao->itens()->create([
            'tenant_id' => $this->tenantId,
            'especialidade_id' => $outra->id,
            'profissional_id' => $profissional->id,
            'quantidade' => 5,
            'status_operacional' => 'pending',
        ]);

        Guia::query()->create([
            'tenant_id' => $this->tenantId,
            'solicitacao_id' => $solicitacao->id,
            'solicitacao_item_id' => $comGuia->id,
            'convenio_id' => $convenio->id,
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'especialidade_id' => $fisio->id,
            'numero_guia' => $numeroGuia,
            'tipo_terapia' => 'especializada',
            'status' => GuiaStatus::UNDER_REVIEW,
            'data_solicitacao' => today()->toDateString(),
        ]);

        return $solicitacao;
    }

    private function itensDe(Solicitacao $solicitacao): array
    {
        $lista = $this->getJson('/api/solicitacoes')->assertOk()->json('data');

        return collect($lista)->firstWhere('id', $solicitacao->id)['itens'];
    }

    public function test_item_com_guia_traz_id_numero_e_status(): void
    {
        $this->autenticar();
        $solicitacao = $this->solicitacaoComDoisItens('50143966538');

        $itens = $this->itensDe($solicitacao);

        $this->assertSame('50143966538', $itens[0]['guia']['numero_guia']);
        $this->assertSame('50143966538', $itens[0]['guia']['numero_operadora']);
        $this->assertSame(GuiaStatus::UNDER_REVIEW, $itens[0]['guia']['status']);
        $this->assertNotNull($itens[0]['guia']['id']);
    }

    public function test_item_sem_guia_vem_com_guia_nula(): void
    {
        $this->autenticar();
        $solicitacao = $this->solicitacaoComDoisItens('50143966538');

        $itens = $this->itensDe($solicitacao);

        $this->assertNull($itens[1]['guia']);
    }

    public function test_valor_de_preenchimento_nao_vira_numero_da_operadora(): void
    {
        $this->autenticar();
        $solicitacao = $this->solicitacaoComDoisItens(
            GuiaStatus::PREFIXO_NUMERO_PLACEHOLDER.'123'
        );

        $guia = $this->itensDe($solicitacao)[0]['guia'];

        // A guia existe e o valor bruto continua disponível, mas o campo que a
        // tela consome como número da operadora vem vazio — é o que impede
        // "GUIA-SOLICITACAO-123" de virar o número que a atendente lê no
        // telefone com o convênio.
        $this->assertNotNull($guia['id']);
        $this->assertSame(GuiaStatus::PREFIXO_NUMERO_PLACEHOLDER.'123', $guia['numero_guia']);
        $this->assertNull($guia['numero_operadora']);
    }

    public function test_guia_sem_numero_vem_com_numero_da_operadora_nulo(): void
    {
        $this->autenticar();
        $solicitacao = $this->solicitacaoComDoisItens(null);

        $guia = $this->itensDe($solicitacao)[0]['guia'];

        $this->assertNotNull($guia['id']);
        $this->assertNull($guia['numero_operadora']);
    }

    /**
     * O requisito é "a coluna Info e a guia por item não adicionam consulta".
     *
     * A forma exata de verificar isso não é contar consultas — a listagem já tem
     * um N+1 anterior a este change, e uma contagem absoluta misturaria os dois.
     * O que este teste trava é a causa: se o resource ler `item->guia` (ou
     * qualquer outra relação) fora do eager load, o modo estrito do Eloquent
     * lança em vez de emitir a consulta silenciosamente.
     */
    public function test_a_listagem_nao_faz_carregamento_preguicoso(): void
    {
        $this->autenticar();

        $this->solicitacaoComDoisItens('50143966538');
        $this->solicitacaoComDoisItens(GuiaStatus::PREFIXO_NUMERO_PLACEHOLDER.'9');
        $this->solicitacaoComDoisItens(null);

        Model::preventLazyLoading();

        try {
            $this->getJson('/api/solicitacoes')->assertOk();
        } finally {
            Model::preventLazyLoading(false);
        }
    }

    public function test_o_numero_de_consultas_nao_cresce_com_a_guia_por_item(): void
    {
        $this->autenticar();
        $this->solicitacaoComDoisItens('50143966538');

        $medir = function (): int {
            $total = 0;
            DB::listen(function () use (&$total) {
                $total++;
            });
            $this->getJson('/api/solicitacoes')->assertOk();
            // Remove o ouvinte para a próxima medição começar limpa.
            DB::getEventDispatcher()->forget(\Illuminate\Database\Events\QueryExecuted::class);

            return $total;
        };

        // Aquecimento descartado: a PRIMEIRA requisição do processo é mais cara
        // (o cache de permissões do Spatie ainda está frio, entre outros), e
        // comparar uma requisição fria com uma quente mede o cache, não o
        // eager load. Sem isto a contagem cai de 30 para 27 entre as medições e
        // o teste acusaria um N+1 que não existe.
        $medir();

        $comUmItemComGuia = $medir();

        // Um item A MAIS na MESMA solicitação: se o resource consultasse a guia
        // por item, isto somaria uma consulta. Com o eager load, não soma.
        $solicitacao = Solicitacao::query()->latest('id')->firstOrFail();
        $solicitacao->itens()->create([
            'tenant_id' => $this->tenantId,
            'especialidade_id' => $solicitacao->especialidade_id,
            'profissional_id' => $solicitacao->profissional_id,
            'quantidade' => 3,
            'status_operacional' => 'pending',
        ]);

        $this->assertSame($comUmItemComGuia, $medir());
    }
}
