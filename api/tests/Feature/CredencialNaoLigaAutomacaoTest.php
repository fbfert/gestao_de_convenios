<?php

namespace Tests\Feature;

use App\Jobs\VerificarGuiasDiarioJob;
use App\Models\ConectorExecucao;
use App\Models\Convenio;
use App\Models\ConvenioCredencial;
use App\Models\Especialidade;
use App\Models\Guia;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\Solicitacao;
use App\Models\SolicitacaoItem;
use App\Models\User;
use App\Services\Automation\GerarGuiaUnimedService;
use App\Services\Connectors\ConnectorResolver;
use App\Support\ConvenioDriverCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Quarto critério de aceite: cadastrar credencial não liga automação.
 *
 * `convenios.connector_driver` é o interruptor, com doze consumidores. Entre
 * eles o `VerificarGuiasDiarioJob`, que DEIXA de conferir manualmente as guias
 * do convênio quando o driver está ligado, por assumir que a automação cuida
 * delas. Se cadastrar credencial ligasse a automação, o SC Saúde entraria num
 * fluxo que não existe e as guias dele sumiriam da verificação diária — sem
 * ninguém perceber, porque some é o que o job faz de propósito.
 *
 * É a distinção que esta change existe para tornar explícita, e por isso ela
 * tem teste próprio em vez de só um comentário.
 */
class CredencialNaoLigaAutomacaoTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_convenio_com_credencial_e_sem_driver_segue_na_verificacao_diaria(): void
    {
        $convenio = $this->convenioComCredencialESemDriver();
        $this->guiaDo($convenio);

        $this->assertContains(
            $convenio->id,
            $this->conveniosVerificadosPeloJob(),
            'a guia do convênio manual precisa continuar sendo conferida',
        );
    }

    /** O contraste: com o interruptor ligado, a guia sai da verificação diária. */
    public function test_convenio_com_driver_ligado_sai_da_verificacao_diaria(): void
    {
        $convenio = $this->convenioComCredencialESemDriver();
        $this->guiaDo($convenio);

        $convenio->forceFill(['connector_driver' => ConvenioDriverCatalog::UNIMED_RDA])->save();

        $this->assertNotContains($convenio->id, $this->conveniosVerificadosPeloJob());
    }

    /**
     * O gate de envio continua sendo o conector, não a credencial: com
     * credencial e sem driver, o item é recusado por "não está configurado como
     * Unimed RDA".
     */
    public function test_item_de_convenio_sem_driver_nao_entra_na_automacao(): void
    {
        $convenio = $this->convenioComCredencialESemDriver();
        $item = $this->itemDe($convenio);

        $avaliacao = app(GerarGuiaUnimedService::class)->avaliar($item);

        $this->assertFalse($avaliacao['eligible']);
        $this->assertContains('O Convênio não está configurado como Unimed RDA.', $avaliacao['motivos']);
    }

    /** E o inverso: a credencial cadastrada não aparece como motivo de bloqueio. */
    public function test_credencial_cadastrada_deixa_de_ser_motivo_de_bloqueio(): void
    {
        $convenio = $this->convenioComCredencialESemDriver();
        $item = $this->itemDe($convenio);

        $this->assertNotContains(
            'A credencial Unimed ativa não está configurada.',
            app(GerarGuiaUnimedService::class)->avaliar($item)['motivos'],
        );
    }

    /**
     * Roda o job de verdade e devolve os convênios que ele conferiu.
     *
     * Rodar o job, e não repetir a condição dele aqui, é o que faz este teste
     * valer: uma condição copiada continuaria passando se o job mudasse.
     *
     * @return array<int, int>
     */
    private function conveniosVerificadosPeloJob(): array
    {
        ConectorExecucao::query()->delete();

        app(VerificarGuiasDiarioJob::class)->handle(app(ConnectorResolver::class));

        return ConectorExecucao::query()->pluck('convenio_id')->all();
    }

    private function convenioComCredencialESemDriver(): Convenio
    {
        $tenantId = (int) User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail()->tenant_id;

        $convenio = Convenio::query()->create([
            'tenant_id' => $tenantId,
            'nome' => 'Convênio Com Credencial Sem Driver',
            'connector_type' => 'manual',
            'connector_driver' => null,
            'ativo' => true,
        ]);

        ConvenioCredencial::query()->create([
            'tenant_id' => $tenantId,
            'convenio_id' => $convenio->id,
            'driver' => ConvenioDriverCatalog::UNIMED_RDA,
            'credenciais' => ['login' => 'operador', 'password' => 'senha'],
            'ativo' => true,
        ]);

        return $convenio;
    }

    private function guiaDo(Convenio $convenio): Guia
    {
        [$paciente, $especialidade, $profissional] = $this->referencias((int) $convenio->tenant_id);

        return Guia::query()->create([
            'tenant_id' => $convenio->tenant_id,
            'convenio_id' => $convenio->id,
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'especialidade_id' => $especialidade->id,
            'numero_guia' => 'LIGA-'.uniqid(),
            'tipo_terapia' => 'especializada',
            'status' => 'under_review',
            'data_solicitacao' => today(),
        ]);
    }

    private function itemDe(Convenio $convenio): SolicitacaoItem
    {
        [$paciente, $especialidade, $profissional] = $this->referencias((int) $convenio->tenant_id);

        $solicitacao = Solicitacao::query()->create([
            'tenant_id' => $convenio->tenant_id,
            'convenio_id' => $convenio->id,
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'especialidade_id' => $especialidade->id,
            'status' => 'ready_for_automation',
            'solicitado_em' => today(),
        ]);

        return SolicitacaoItem::query()->create([
            'tenant_id' => $convenio->tenant_id,
            'solicitacao_id' => $solicitacao->id,
            'especialidade_id' => $especialidade->id,
            'profissional_id' => $profissional->id,
            'quantidade' => 10,
        ]);
    }

    /** @return array{0: Paciente, 1: Especialidade, 2: Profissional} */
    private function referencias(int $tenantId): array
    {
        $paciente = Paciente::query()->where('tenant_id', $tenantId)->firstOrFail();
        $especialidade = Especialidade::query()->where('tenant_id', $tenantId)->firstOrFail();
        $profissional = Profissional::query()
            ->where('tenant_id', $tenantId)
            ->where('especialidade_id', $especialidade->id)
            ->firstOrFail();

        return [$paciente, $especialidade, $profissional];
    }
}
