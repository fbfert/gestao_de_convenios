<?php

namespace Tests\Feature;

use App\Jobs\VerificarGuiasDiarioJob;
use App\Models\ConectorExecucao;
use App\Models\ConfiguracaoGlobal;
use App\Models\Convenio;
use App\Models\Especialidade;
use App\Models\Guia;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\Tenant;
use App\Scopes\TenantScope;
use App\Services\Connectors\ConnectorResolver;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Isolamento onde o automático não alcança: fora de requisição HTTP.
 *
 * O `TenantScope` só filtra quando há `TenantContext`, e o `TenantContext` é
 * alimentado pelo middleware `ResolveTenant` a partir do usuário autenticado.
 * Num job da fila, num comando de console ou numa rotina agendada não há
 * usuário, não há contexto, e o scope é **no-op**: a consulta que parece igual
 * a todas as outras passa a ler todas as clínicas.
 *
 * É o ponto mais silencioso do isolamento. Não há resposta HTTP errada para
 * alguém notar: o efeito é uma clínica decidir pela outra, ou um dado da outra
 * entrar numa conta, e ninguém liga reclamando porque nada quebra na tela.
 *
 * Os jobs de hoje passam o tenant explicitamente e comentam por quê. Estes
 * testes existem para que isso deixe de depender de quem escreve o próximo.
 */
class IsolamentoNaFilaTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    /**
     * A premissa, afirmada em vez de comentada.
     *
     * Se um dia o `TenantScope` passar a se defender sozinho fora de
     * requisição, este teste falha — e aí a obrigação de recortar à mão em todo
     * job pode ser reexaminada, em vez de continuar valendo por inércia.
     */
    public function test_sem_contexto_de_clinica_o_escopo_automatico_nao_filtra_nada(): void
    {
        $this->criarGuiaEmOutraClinica();

        TenantContext::clear();

        $comEscopo = Guia::query()->count();
        $semEscopo = Guia::query()->withoutGlobalScope(TenantScope::class)->count();

        $this->assertSame(
            $semEscopo,
            $comEscopo,
            'Fora de requisição o TenantScope passou a filtrar. Se isso for intencional, '
            .'a exigência de recorte explícito nos jobs precisa ser revista — e este teste, reescrito.'
        );

        $this->assertGreaterThan(
            1,
            $comEscopo,
            'O teste precisa de guias em mais de uma clínica para significar algo.'
        );
    }

    /**
     * O job varre todas as clínicas de propósito — é uma rotina diária —, mas a
     * decisão de cada uma SHALL sair da configuração dela.
     *
     * Este é o modo de falha concreto: a leitura da configuração por tenant
     * (`where('tenant_id', $tenantId)`) é a única coisa que impede a
     * configuração de uma clínica decidir pela outra. Sem ela, a consulta
     * devolve a primeira linha que encontrar, e a clínica que desligou a
     * verificação diária continua sendo verificada — ou, pior, a que deixou
     * ligada para de ser.
     */
    public function test_job_diario_respeita_a_configuracao_de_cada_clinica(): void
    {
        [$primeira, $segunda] = $this->duasClinicasComGuiaParaVerificar();

        // A primeira desliga a verificação diária; a segunda mantém ligada.
        ConfiguracaoGlobal::doTenant($primeira->id)->update([
            'automacao_verificacao_guias_diaria_ativo' => false,
        ]);
        ConfiguracaoGlobal::doTenant($segunda->id)->update([
            'automacao_verificacao_guias_diaria_ativo' => true,
        ]);

        app(VerificarGuiasDiarioJob::class)->handle(app(ConnectorResolver::class));

        $execucoesDaPrimeira = ConectorExecucao::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $primeira->id)
            ->count();

        $execucoesDaSegunda = ConectorExecucao::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $segunda->id)
            ->count();

        $this->assertSame(
            0,
            $execucoesDaPrimeira,
            'A clínica que DESLIGOU a verificação diária foi verificada: a configuração da outra '
            .'decidiu por ela.'
        );

        $this->assertGreaterThan(
            0,
            $execucoesDaSegunda,
            'A clínica que deixou a verificação LIGADA não foi verificada: a configuração da outra '
            .'decidiu por ela.'
        );
    }

    /** E o que o job grava fica na clínica da guia, nunca na vizinha. */
    public function test_job_diario_grava_a_execucao_na_clinica_da_guia(): void
    {
        [$primeira, $segunda] = $this->duasClinicasComGuiaParaVerificar();

        foreach ([$primeira, $segunda] as $clinica) {
            ConfiguracaoGlobal::doTenant($clinica->id)->update([
                'automacao_verificacao_guias_diaria_ativo' => true,
            ]);
        }

        app(VerificarGuiasDiarioJob::class)->handle(app(ConnectorResolver::class));

        $execucoes = ConectorExecucao::query()->withoutGlobalScopes()->with([])->get();

        $this->assertGreaterThan(0, $execucoes->count(), 'O job não gravou execução alguma.');

        foreach ($execucoes as $execucao) {
            $guia = Guia::query()
                ->withoutGlobalScopes()
                ->where('convenio_id', $execucao->convenio_id)
                ->where('tenant_id', $execucao->tenant_id)
                ->first();

            $this->assertNotNull(
                $guia,
                "Execução gravada na clínica {$execucao->tenant_id} para o convênio "
                ."{$execucao->convenio_id}, que não tem guia nessa clínica: o job cruzou clínicas."
            );
        }
    }

    // ── Apoio ───────────────────────────────────────────────────────────────

    /**
     * Duas clínicas, cada uma com convênio, paciente e guia `under_review`
     * próprios.
     *
     * Os convênios são distintos porque o job agrupa por `convenio_id` e lê o
     * tenant do primeiro do grupo — com o convênio compartilhado, as duas
     * clínicas cairiam no mesmo grupo e o teste mediria outra coisa.
     *
     * @return array{0: Tenant, 1: Tenant}
     */
    private function duasClinicasComGuiaParaVerificar(): array
    {
        Guia::query()->withoutGlobalScopes()->delete();
        ConectorExecucao::query()->withoutGlobalScopes()->delete();

        $primeira = Tenant::query()->where('slug', 'clinica-exemplo')->firstOrFail();
        $segunda = Tenant::query()->create([
            'nome' => 'Clínica Vizinha da Fila',
            'slug' => 'clinica-vizinha-fila',
            'cnpj' => '44.444.444/0001-44',
            'ativo' => true,
        ]);

        $this->criarGuiaUnderReview($primeira, 'SC Saúde');
        $this->criarGuiaUnderReview($segunda, 'Celos');

        return [$primeira, $segunda];
    }

    /**
     * Uma guia `under_review` na clínica indicada, com convênio próprio dela.
     *
     * O convênio é copiado do semeado para a clínica de destino quando ela não
     * é a semeada: assim o teste não precisa saber o formato do model, e o
     * `connector_driver` continua sendo um que o job aceita (não `unimed_rda`).
     */
    private function criarGuiaUnderReview(Tenant $clinica, string $nomeConvenio): Guia
    {
        $convenioSemeado = Convenio::query()
            ->withoutGlobalScopes()
            ->where('nome', $nomeConvenio)
            ->firstOrFail();

        $convenio = $this->naClinica($convenioSemeado, $clinica);
        $especialidade = $this->naClinica(
            Especialidade::query()->withoutGlobalScopes()->where('nome', 'Fisioterapia')->firstOrFail(),
            $clinica
        );
        $profissional = $this->naClinica(
            Profissional::query()->withoutGlobalScopes()->orderBy('id')->firstOrFail(),
            $clinica
        );
        $paciente = $this->naClinica(
            Paciente::query()->withoutGlobalScopes()->orderBy('id')->firstOrFail(),
            $clinica
        );

        return Guia::query()->create([
            'tenant_id' => $clinica->id,
            'solicitacao_id' => null,
            'convenio_id' => $convenio->id,
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'especialidade_id' => $especialidade->id,
            'numero_guia' => 'FILA-'.uniqid(),
            'tipo_terapia' => 'especializada',
            'status' => 'under_review',
            'data_solicitacao' => today(),
        ]);
    }

    /** O registro na clínica indicada — o próprio, se já for dela; uma cópia, se não. */
    private function naClinica(Model $original, Tenant $clinica): Model
    {
        if ((int) $original->tenant_id === (int) $clinica->id) {
            return $original;
        }

        $copia = $original->replicate();
        $copia->tenant_id = $clinica->id;

        foreach (['email', 'slug', 'cnpj', 'cpf'] as $campo) {
            if (! is_null($copia->getAttribute($campo) ?? null)) {
                $copia->setAttribute($campo, 'fila-'.$copia->getAttribute($campo));
            }
        }

        $copia->save();

        return $copia;
    }

    private function criarGuiaEmOutraClinica(): void
    {
        $this->duasClinicasComGuiaParaVerificar();
    }
}
