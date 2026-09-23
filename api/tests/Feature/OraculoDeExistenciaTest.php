<?php

namespace Tests\Feature;

use App\Models\Convenio;
use App\Models\Especialidade;
use App\Models\Guia;
use App\Models\Profissional;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A validação não conta o que existe em outra clínica.
 *
 * `exists:tabela,id` roda no query builder, não no Eloquent: o `TenantScope`
 * não se aplica e a regra vê todas as clínicas. O dado alheio nunca ficou
 * acessível — o código adiante refaz a consulta com escopo e devolve 404 —, mas
 * a DIFERENÇA entre as respostas vazava existência:
 *
 * - id de outra clínica: passava na validação, morria depois → 404
 * - id inexistente: recusado na validação → 422
 *
 * Repetido número a número, isso enumera a base da clínica vizinha sem nunca
 * mostrar um registro: quantos profissionais ela tem, quantos convênios,
 * quantas especialidades.
 *
 * Cada teste aqui compara as duas respostas e exige que sejam iguais. Não
 * afirma QUAL é o status, de propósito: o que precisa valer é a
 * indistinguibilidade, e prender o número tornaria o teste sobre a forma da
 * resposta em vez de sobre o vazamento.
 */
class OraculoDeExistenciaTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    /** Id que não existe em clínica alguma. */
    private const ID_INEXISTENTE = 999999;

    public function test_registrar_sessao_nao_distingue_profissional_alheio_de_inexistente(): void
    {
        $this->autenticar();
        $guia = $this->guiaQueAceitaLancamento();
        $alheio = $this->naVizinha(Profissional::query()->orderBy('id')->firstOrFail());

        $comAlheio = $this->postJson("/api/guias/{$guia->id}/lancamentos", [
            'profissional_id' => $alheio->getKey(),
            'data_sessao' => '2026-11-03',
            'hora_inicio' => '08:00',
        ]);

        $comInexistente = $this->postJson("/api/guias/{$guia->id}/lancamentos", [
            'profissional_id' => self::ID_INEXISTENTE,
            'data_sessao' => '2026-11-03',
            'hora_inicio' => '08:00',
        ]);

        $this->afirmarRecusaIdentica($comAlheio, $comInexistente, 'profissional_id', 'POST /guias/{guia}/lancamentos');
    }

    public function test_conferir_agenda_nao_distingue_profissional_alheio_de_inexistente(): void
    {
        $this->autenticar();
        $guia = $this->guiaQueAceitaLancamento();
        $alheio = $this->naVizinha(Profissional::query()->orderBy('id')->firstOrFail());

        $sessoes = ['sessoes' => [['data_sessao' => '2026-11-04', 'hora_inicio' => '09:00']]];

        $this->afirmarRecusaIdentica(
            $this->postJson(
                "/api/guias/{$guia->id}/lancamentos/conferir-agenda",
                ['profissional_id' => $alheio->getKey()] + $sessoes
            ),
            $this->postJson(
                "/api/guias/{$guia->id}/lancamentos/conferir-agenda",
                ['profissional_id' => self::ID_INEXISTENTE] + $sessoes
            ),
            'profissional_id',
            'POST /guias/{guia}/lancamentos/conferir-agenda'
        );
    }

    public function test_filtro_de_conciliacao_nao_distingue_ids_alheios_de_inexistentes(): void
    {
        $this->autenticar();

        $casos = [
            'convenio_id' => $this->naVizinha(Convenio::query()->orderBy('id')->firstOrFail()),
            'especialidade_id' => $this->naVizinha(Especialidade::query()->orderBy('id')->firstOrFail()),
            'profissional_id' => $this->naVizinha(Profissional::query()->orderBy('id')->firstOrFail()),
        ];

        foreach ($casos as $campo => $alheio) {
            $this->assertNotNull($alheio, "A montagem do ataque falhou para {$campo}.");

            $this->afirmarRecusaIdentica(
                $this->getJson("/api/conciliacoes?{$campo}=".$alheio->getKey()),
                $this->getJson('/api/conciliacoes?'.$campo.'='.self::ID_INEXISTENTE),
                $campo,
                "GET /conciliacoes?{$campo}"
            );
        }
    }

    public function test_cadastro_rapido_de_paciente_nao_distingue_convenio_alheio_de_inexistente(): void
    {
        $this->autenticar();
        $alheio = $this->naVizinha(Convenio::query()->orderBy('id')->firstOrFail());

        $this->afirmarRecusaIdentica(
            $this->postJson('/api/solicitacoes/pacientes-rapido', [
                'nome' => 'Paciente de Teste',
                'convenio_id' => $alheio->getKey(),
                'carteirinha' => '0123456789012345',
            ]),
            $this->postJson('/api/solicitacoes/pacientes-rapido', [
                'nome' => 'Paciente de Teste',
                'convenio_id' => self::ID_INEXISTENTE,
                'carteirinha' => '0123456789012345',
            ]),
            'convenio_id',
            'POST /solicitacoes/pacientes-rapido'
        );
    }

    /** O caminho legítimo continua funcionando — senão os testes acima seriam vazios. */
    public function test_id_da_propria_clinica_continua_aceito(): void
    {
        $user = $this->autenticar();
        $guia = $this->guiaQueAceitaLancamento();
        $proprio = Profissional::query()->where('tenant_id', $user->tenant_id)->orderBy('id')->firstOrFail();

        $this->postJson("/api/guias/{$guia->id}/lancamentos", [
            'profissional_id' => $proprio->id,
            'data_sessao' => '2026-11-05',
            'hora_inicio' => '10:00',
        ])->assertCreated();
    }

    // ── Apoio ───────────────────────────────────────────────────────────────

    /**
     * As duas respostas têm de ser indistinguíveis — e as duas têm de ser
     * recusa da VALIDAÇÃO sobre aquele campo.
     *
     * A terceira asserção não é zelo, e ela já pagou por si: enquanto só havia
     * as duas primeiras, `conferir-agenda` passava verde respondendo **500 nas
     * duas chamadas** — iguais, logo aprovadas. O 500 era um import faltando no
     * controller, ou seja, o endpoint estava quebrado e o teste dizia que o
     * isolamento estava certo.
     *
     * Exigir `errors[campo]` nas duas prende o que interessa: o id foi recusado
     * ali, na porta, pela validação — e não por acidente lá dentro.
     */
    private function afirmarRecusaIdentica(
        TestResponse $alheio,
        TestResponse $inexistente,
        string $campo,
        string $onde,
    ): void {
        $this->assertSame(
            $inexistente->getStatusCode(),
            $alheio->getStatusCode(),
            "{$onde}: id de outra clínica responde HTTP {$alheio->getStatusCode()} e id inexistente "
            ."responde HTTP {$inexistente->getStatusCode()}. A diferença confirma que o id existe "
            .'em outra clínica.'
        );

        $this->assertSame(
            $this->parteQueOClienteLe($inexistente),
            $this->parteQueOClienteLe($alheio),
            "{$onde}: as duas respostas têm o mesmo status mas dizem coisas diferentes, o que ainda "
            .'distingue id alheio de id inexistente.'
        );

        foreach (['id de outra clínica' => $alheio, 'id inexistente' => $inexistente] as $qual => $resposta) {
            $this->assertArrayHasKey(
                $campo,
                $this->parteQueOClienteLe($resposta)['errors'] ?? [],
                "{$onde}: {$qual} não foi recusado pela validação do campo {$campo} "
                ."(HTTP {$resposta->getStatusCode()}). Respostas iguais por outro motivo não provam "
                .'que o recorte por clínica está no lugar.'
            );
        }
    }

    /**
     * Mensagem e erros de validação, sem o resto.
     *
     * O corpo cru não serve para comparar: com `APP_DEBUG` ligado — que é como a
     * suíte roda — a resposta de exceção carrega o rastro de pilha, e nele vai o
     * número da linha de quem chamou. As duas chamadas saem de linhas
     * diferentes deste arquivo, então os corpos seriam sempre distintos por um
     * motivo que nada tem a ver com vazamento.
     *
     * O que importa é o que chega a quem está do outro lado: a mensagem e os
     * erros de campo. É neles que um "profissional não encontrado" versus um
     * "profissional inválido" voltaria a contar qual id existe.
     *
     * @return array{message: mixed, errors: mixed}
     */
    private function parteQueOClienteLe(TestResponse $resposta): array
    {
        $corpo = $resposta->json() ?? [];

        return [
            'message' => is_array($corpo) ? ($corpo['message'] ?? null) : null,
            'errors' => is_array($corpo) ? ($corpo['errors'] ?? null) : null,
        ];
    }

    private function autenticar(): User
    {
        $user = User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($user);

        return $user;
    }

    private function vizinha(): Tenant
    {
        return Tenant::query()->firstOrCreate(
            ['slug' => 'clinica-vizinha-oraculo'],
            ['nome' => 'Clínica Vizinha do Oráculo', 'cnpj' => '55.555.555/0001-55', 'ativo' => true]
        );
    }

    /** O mesmo registro, na clínica vizinha. */
    private function naVizinha(Model $original): Model
    {
        $copia = $original->replicate();
        $copia->tenant_id = $this->vizinha()->id;

        foreach (['email', 'slug', 'cnpj', 'cpf'] as $campo) {
            if (! is_null($copia->getAttribute($campo) ?? null)) {
                $copia->setAttribute($campo, 'vizinha-'.$copia->getAttribute($campo));
            }
        }

        $copia->save();

        return $copia;
    }

    /**
     * Uma guia aprovada e com sessão sobrando.
     *
     * Sem isso a requisição para em "esta guia ainda não está aprovada" e o
     * teste passa verde sem chegar à validação do `profissional_id` — a
     * armadilha que a varredura de vazamento já pagou uma vez.
     */
    private function guiaQueAceitaLancamento(): Guia
    {
        $guia = Guia::query()->orderBy('id')->firstOrFail();
        $guia->forceFill(['sessoes_autorizadas' => 20])->save();

        // Aprovação pela API: o model recusa mudança de status fora do
        // GuiaService (ver a change guia-status-historico).
        $this->patchJson("/api/guias/{$guia->id}/aprovar")->assertSuccessful();

        $fresca = $guia->fresh();
        $this->assertTrue($fresca->aceitaLancamento(), 'A guia do teste não aceita lançamento.');

        return $fresca;
    }
}
