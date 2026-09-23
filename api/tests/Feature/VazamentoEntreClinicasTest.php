<?php

namespace Tests\Feature;

use App\Models\AnaliticoUnimedLote;
use App\Models\AutomacaoExecucao;
use App\Models\Guia;
use App\Models\Lancamento;
use App\Models\Profissional;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RotaDoLaravel;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Varredura de vazamento entre clínicas.
 *
 * O isolamento do Gescon é o ADR-01: banco único, coluna `tenant_id` e
 * `TenantScope` como global scope, mais `BelongsToTenant::resolveRouteBinding`
 * para o instante em que a URL vira model. `IsolamentoDeBindingApiTest` já
 * prova isso em dois parâmetros escolhidos a dedo.
 *
 * Este teste é o contrário: não escolhe nada. Ele PERGUNTA AO ROTEADOR quais
 * rotas existem, descobre por reflexão qual model cada parâmetro resolve, e
 * ataca todas. Uma rota nova nasce coberta, e é por isso que o teste existe —
 * o modo de falha real deste projeto não é o isolamento ser mal escrito, é
 * alguém acrescentar uma rota que passa por fora dele.
 *
 * Os registros da clínica vizinha são feitos por `replicate()` de uma linha já
 * semeada, e não montados campo a campo: assim o teste não precisa saber o
 * formato de 37 models, e um campo novo em qualquer um deles não o quebra.
 *
 * O ataque é sempre feito com o ADMIN da clínica — não com usuário sem
 * permissão. Um 403 por falta de permissão provaria outra coisa, e passaria
 * verde mesmo com o isolamento derrubado.
 */
class VazamentoEntreClinicasTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    /**
     * Parâmetros de rota que NÃO resolvem model e por isso não entram na
     * varredura. Declarados aqui para que um parâmetro novo apareça no
     * relatório em vez de sumir em silêncio.
     */
    private const PARAMETROS_SEM_MODEL = [
        'tipo',   // api/manual/{tipo?} — string, escolhe o documento do manual
        'slug',   // api/novidades/{slug} — recurso de arquivo, não de banco
        'role',   // papel do Spatie, global por desenho (não tem tenant_id)
    ];

    /**
     * Rotas cujo 404 tem motivo de negócio, e não de isolamento.
     *
     * A varredura de escrita usa o 404 como prova de barramento, então precisa
     * saber onde um 404 significa outra coisa. `reativar` responde 404 quando o
     * convênio não tem credencial — e a cópia que a varredura cria não tem —,
     * o que faria o controle acusar rota inalcançável sem haver problema algum.
     *
     * A lista é explícita para que uma rota nova não entre aqui sozinha: ela
     * reprova primeiro, e alguém decide.
     */
    private const ROTAS_COM_404_DE_NEGOCIO = [
        'api/configuracoes/convenios-credenciais/{convenio}/reativar',
    ];

    /**
     * Toda rota GET de um único parâmetro, atacada com registro da vizinha.
     *
     * Um único teste para todas, de propósito: o valor está na varredura ser
     * exaustiva, e a mensagem de falha nomeia rota por rota o que vazou.
     */
    public function test_nenhuma_rota_get_por_id_entrega_registro_de_outra_clinica(): void
    {
        $this->autenticarComoAdmin();
        $vizinha = $this->clinicaVizinha();

        $vazaram = [];
        $rotasSemControle = [];
        $naoCobertas = [];
        $atacadas = 0;

        foreach ($this->rotasGetDeUmParametro() as [$rota, $parametro, $modelo]) {
            $uri = $rota->uri();

            if ($modelo === null) {
                $naoCobertas[] = "{$uri} (parâmetro {$parametro} não resolve model)";

                continue;
            }

            $proprio = $this->linhaSemeada($modelo);

            if ($proprio === null) {
                $naoCobertas[] = "{$uri} (sem linha semeada de ".class_basename($modelo).' para replicar)';

                continue;
            }

            $alheio = $this->replicarParaOutraClinica($proprio, $vizinha);

            if ($alheio === null) {
                $naoCobertas[] = "{$uri} (não foi possível replicar ".class_basename($modelo).')';

                continue;
            }

            /*
             * O controle vem primeiro: se a rota não responde nem para o
             * registro da própria clínica, o 404 do ataque não prova nada.
             * É a armadilha em que este teste cairia sozinho.
             */
            $respostaPropria = $this->getJson($this->urlDe($rota, $parametro, $proprio->getKey()));

            if ($respostaPropria->getStatusCode() === 404) {
                $rotasSemControle[] = "{$uri} → 404 para o registro da PRÓPRIA clínica";

                continue;
            }

            $resposta = $this->getJson($this->urlDe($rota, $parametro, $alheio->getKey()));
            $atacadas++;

            if ($resposta->getStatusCode() !== 404) {
                $vazaram[] = "{$uri} → HTTP {$resposta->getStatusCode()} para registro da clínica vizinha";
            }
        }

        $this->assertGreaterThan(
            10,
            $atacadas,
            'A varredura atacou poucas rotas para significar algo. '
            ."Não cobertas:\n- ".implode("\n- ", $naoCobertas)
        );

        $this->assertSame(
            [],
            $rotasSemControle,
            "Rotas que não respondem nem para a própria clínica (o ataque nelas seria vazio):\n- "
            .implode("\n- ", $rotasSemControle)
        );

        $this->assertSame(
            [],
            $vazaram,
            "VAZAMENTO ENTRE CLÍNICAS em {$atacadas} rotas atacadas:\n- ".implode("\n- ", $vazaram)
        );
    }

    /**
     * As listagens não somam linha da vizinha.
     *
     * O binding protege o acesso por id; a listagem depende do `TenantScope`.
     * São duas defesas diferentes, e esta é a que um `withoutGlobalScope`
     * esquecido derruba.
     */
    public function test_nenhuma_listagem_soma_registro_de_outra_clinica(): void
    {
        $this->autenticarComoAdmin();
        $vizinha = $this->clinicaVizinha();

        $vazaram = [];
        $conferidas = 0;

        foreach ($this->rotasGetSemParametro() as $rota) {
            $uri = $rota->uri();
            $modelo = $this->modeloDaListagem($rota);

            if ($modelo === null) {
                continue;
            }

            $proprio = $this->linhaSemeada($modelo);

            if ($proprio === null) {
                continue;
            }

            $antes = $this->getJson('/'.$uri);

            if ($antes->getStatusCode() !== 200) {
                continue;
            }

            $quantidadeAntes = $this->quantidadeDeItens($antes->json());

            if ($quantidadeAntes === null) {
                continue;
            }

            $alheio = $this->replicarParaOutraClinica($proprio, $vizinha);

            if ($alheio === null) {
                continue;
            }

            $depois = $this->getJson('/'.$uri);
            $quantidadeDepois = $this->quantidadeDeItens($depois->json());
            $conferidas++;

            if ($quantidadeDepois !== $quantidadeAntes) {
                $vazaram[] = "{$uri} → {$quantidadeAntes} itens antes, {$quantidadeDepois} depois de "
                    .'inserir uma linha na clínica vizinha';
            }

            // Desfaz, para uma listagem não contaminar a conferência da próxima.
            $alheio->forceDelete();
        }

        $this->assertGreaterThan(5, $conferidas, 'A varredura conferiu poucas listagens para significar algo.');

        $this->assertSame(
            [],
            $vazaram,
            "LISTAGEM VAZANDO entre clínicas ({$conferidas} conferidas):\n- ".implode("\n- ", $vazaram)
        );
    }

    /**
     * Nenhuma rota de ESCRITA alcança registro da vizinha.
     *
     * Vale mais que a de leitura: ler dado alheio é vazamento, escrever nele é
     * corromper o prontuário de outra clínica.
     *
     * O corpo enviado é vazio de propósito. O `SubstituteBindings` roda ANTES
     * da validação, então um registro de outra clínica tem de virar 404 sem o
     * payload sequer ser olhado — e um 422 no ataque seria má notícia, porque
     * significaria que a requisição chegou à validação carregando um model que
     * não é da clínica de quem pediu.
     *
     * O controle é feito numa CÓPIA do registro dentro da própria clínica, e
     * nunca no original: assim um DELETE de controle não apaga dado semeado de
     * que os testes seguintes dependem.
     */
    public function test_nenhuma_rota_de_escrita_alcanca_registro_de_outra_clinica(): void
    {
        $this->autenticarComoAdmin();
        $vizinha = $this->clinicaVizinha();
        $propria = User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail()->tenant;

        $vazaram = [];
        $rotasSemControle = [];
        $atacadas = 0;

        foreach ($this->rotasDeEscritaDeUmParametro() as [$rota, $metodo, $parametro, $modelo]) {
            $uri = $rota->uri();

            if ($modelo === null) {
                continue;
            }

            $original = $this->linhaSemeada($modelo);

            if ($original === null) {
                continue;
            }

            $copiaPropria = $this->replicarPara($original, $propria);
            $alheio = $this->replicarPara($original, $vizinha);

            if ($copiaPropria === null || $alheio === null) {
                continue;
            }

            $respostaPropria = $this->json($metodo, $this->urlDe($rota, $parametro, $copiaPropria->getKey()));

            if ($respostaPropria->getStatusCode() === 404) {
                if (! in_array($uri, self::ROTAS_COM_404_DE_NEGOCIO, true)) {
                    $rotasSemControle[] = "{$metodo} {$uri} → 404 para cópia na PRÓPRIA clínica";
                }

                continue;
            }

            $resposta = $this->json($metodo, $this->urlDe($rota, $parametro, $alheio->getKey()));
            $atacadas++;

            if ($resposta->getStatusCode() !== 404) {
                $vazaram[] = "{$metodo} {$uri} → HTTP {$resposta->getStatusCode()} "
                    .'para registro da clínica vizinha';
            }

            // A linha da vizinha tem de continuar existindo e intacta.
            $depois = $modelo::query()->withoutGlobalScopes()->find($alheio->getKey());

            if ($depois === null) {
                $vazaram[] = "{$metodo} {$uri} → APAGOU o registro da clínica vizinha";
            }
        }

        $this->assertGreaterThan(
            10,
            $atacadas,
            'A varredura de escrita atacou poucas rotas para significar algo.'
        );

        $this->assertSame(
            [],
            $rotasSemControle,
            "Rotas de escrita que não respondem nem na própria clínica (o ataque nelas seria vazio):\n- "
            .implode("\n- ", $rotasSemControle)
        );

        $this->assertSame(
            [],
            $vazaram,
            "ESCRITA ALCANÇANDO outra clínica em {$atacadas} rotas atacadas:\n- ".implode("\n- ", $vazaram)
        );
    }

    /**
     * Sessão não é registrada com profissional de outra clínica.
     *
     * Este é o ataque que o binding NÃO cobre: o id não vem da URL, vem do
     * corpo. `StoreLancamentoRequest` valida `profissional_id` com
     * `exists:profissionais,id` SEM recorte de clínica, então a defesa aqui é o
     * `TenantScope` no `findOrFail` do controller. É uma defesa só, e é por
     * isso que este teste existe separado da varredura.
     */
    public function test_nao_registra_sessao_com_profissional_de_outra_clinica(): void
    {
        $this->autenticarComoAdmin();
        $vizinha = $this->clinicaVizinha();

        /*
         * A guia tem de aceitar lançamento (aprovada, com sessão sobrando).
         * Sem isso a requisição para em "esta guia ainda não está aprovada" e o
         * teste passa verde sem nunca chegar ao `profissional_id` — foi
         * exatamente o que a primeira versão dele fazia.
         */
        $guia = Guia::query()->orderBy('id')->firstOrFail();
        $guia->forceFill(['sessoes_autorizadas' => 20])->save();

        // Aprovação pela própria API: o model recusa mudança de status fora de
        // `GuiaService::registrarTransicao()` (ver a change guia-status-historico).
        $this->patchJson("/api/guias/{$guia->id}/aprovar")->assertSuccessful();

        $this->assertTrue(
            $guia->fresh()->aceitaLancamento(),
            'A montagem do ataque falhou: a guia não aceita lançamento, então o teste não provaria nada.'
        );

        $profissionalAlheio = $this->replicarPara(
            Profissional::query()->orderBy('id')->firstOrFail(),
            $vizinha
        );

        $this->assertNotNull($profissionalAlheio, 'A montagem do ataque falhou: sem profissional alheio.');

        $resposta = $this->postJson("/api/guias/{$guia->id}/lancamentos", [
            'profissional_id' => $profissionalAlheio->getKey(),
            'data_sessao' => '2026-10-15',
            'hora_inicio' => '09:00',
        ]);

        $this->assertNotSame(
            201,
            $resposta->getStatusCode(),
            'Sessão registrada com profissional de OUTRA clínica: o executante do prontuário '
            .'passou a ser alguém que não é desta clínica.'
        );

        $this->assertDatabaseMissing('lancamentos', [
            'profissional_id' => $profissionalAlheio->getKey(),
        ]);
    }

    // ── Apoio ───────────────────────────────────────────────────────────────

    /**
     * @return list<array{0: RotaDoLaravel, 1: string, 2: string, 3: class-string<Model>|null}>
     */
    private function rotasDeEscritaDeUmParametro(): array
    {
        $encontradas = [];

        foreach (Route::getRoutes() as $rota) {
            if (! str_starts_with($rota->uri(), 'api/')) {
                continue;
            }

            $parametros = $rota->parameterNames();

            if (count($parametros) !== 1) {
                continue;
            }

            $parametro = $parametros[0];

            if (in_array($parametro, self::PARAMETROS_SEM_MODEL, true)) {
                continue;
            }

            foreach (['DELETE', 'PUT', 'PATCH', 'POST'] as $metodo) {
                if (in_array($metodo, $rota->methods(), true)) {
                    $encontradas[] = [$rota, $metodo, $parametro, $this->modeloDoParametro($rota, $parametro)];

                    break;
                }
            }
        }

        return $encontradas;
    }

    private function autenticarComoAdmin(): User
    {
        $user = User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail();

        $this->assertFalse(
            $user->ehSuperAdmin(),
            'O atacante deste teste tem de ser admin de clínica, não super admin: '
            .'o super admin atravessa clínicas por desenho, e o teste passaria a medir outra coisa.'
        );

        Sanctum::actingAs($user);

        return $user;
    }

    private function clinicaVizinha(): Tenant
    {
        return Tenant::query()->create([
            'nome' => 'Clínica Vizinha da Varredura',
            'slug' => 'clinica-vizinha-varredura',
            'cnpj' => '66.666.666/0001-66',
            'ativo' => true,
        ]);
    }

    /**
     * @return list<array{0: RotaDoLaravel, 1: string, 2: class-string<Model>|null}>
     */
    private function rotasGetDeUmParametro(): array
    {
        $encontradas = [];

        foreach (Route::getRoutes() as $rota) {
            if (! str_starts_with($rota->uri(), 'api/') || ! in_array('GET', $rota->methods(), true)) {
                continue;
            }

            $parametros = $rota->parameterNames();

            if (count($parametros) !== 1) {
                continue;
            }

            $parametro = $parametros[0];

            if (in_array($parametro, self::PARAMETROS_SEM_MODEL, true)) {
                continue;
            }

            $encontradas[] = [$rota, $parametro, $this->modeloDoParametro($rota, $parametro)];
        }

        return $encontradas;
    }

    /** @return list<RotaDoLaravel> */
    private function rotasGetSemParametro(): array
    {
        $encontradas = [];

        foreach (Route::getRoutes() as $rota) {
            if (! str_starts_with($rota->uri(), 'api/') || ! in_array('GET', $rota->methods(), true)) {
                continue;
            }

            if ($rota->parameterNames() !== []) {
                continue;
            }

            $encontradas[] = $rota;
        }

        return $encontradas;
    }

    /**
     * Qual model o parâmetro resolve, pela assinatura do método do controller.
     *
     * @return class-string<Model>|null
     */
    private function modeloDoParametro(RotaDoLaravel $rota, string $parametro): ?string
    {
        foreach ($this->parametrosDaAcao($rota) as $nome => $tipo) {
            if ($nome === $parametro && is_subclass_of($tipo, Model::class)) {
                return $tipo;
            }
        }

        return null;
    }

    /**
     * O model de uma listagem, adivinhado pelo nome do controller.
     *
     * Adivinhar aqui é aceitável porque o teste de listagem só precisa de UM
     * model plausível para inserir a linha alheia: se o palpite errar, a rota
     * apenas não entra na conferência, e o contador de conferidas acusa.
     *
     * @return class-string<Model>|null
     */
    private function modeloDaListagem(RotaDoLaravel $rota): ?string
    {
        [$controller] = $this->controllerEMetodo($rota);

        if ($controller === null) {
            return null;
        }

        $base = str_replace('Controller', '', class_basename($controller));

        foreach ([$base, rtrim($base, 's'), rtrim($base, 'es')] as $palpite) {
            $classe = "App\\Models\\{$palpite}";

            if (class_exists($classe) && is_subclass_of($classe, Model::class)) {
                return $classe;
            }
        }

        return null;
    }

    /** @return array<string, string|null> nome do parâmetro => tipo declarado */
    private function parametrosDaAcao(RotaDoLaravel $rota): array
    {
        /*
         * Controller invocável não tem `@` no nome da ação — o método é
         * `__invoke`. Tratar só o formato com `@` deixava de fora justamente as
         * rotas de classe única (como `pacientes/{paciente}/pasta`), que é onde
         * um parâmetro passa mais fácil despercebido.
         */
        [$controller, $metodo] = $this->controllerEMetodo($rota);

        if ($controller === null || ! class_exists($controller) || ! method_exists($controller, $metodo)) {
            return [];
        }

        $tipos = [];

        foreach ((new ReflectionMethod($controller, $metodo))->getParameters() as $parametro) {
            $tipo = $parametro->getType();
            $tipos[$parametro->getName()] = $tipo instanceof \ReflectionNamedType ? $tipo->getName() : null;
        }

        return $tipos;
    }

    /**
     * A classe e o método que atendem a rota.
     *
     * @return array{0: class-string|null, 1: string}
     */
    private function controllerEMetodo(RotaDoLaravel $rota): array
    {
        $acao = $rota->getActionName();

        if ($acao === 'Closure') {
            return [null, ''];
        }

        if (str_contains($acao, '@')) {
            [$controller, $metodo] = explode('@', $acao);

            return [$controller, $metodo];
        }

        return [$acao, '__invoke'];
    }

    /**
     * Uma linha do model que já exista na clínica semeada.
     *
     * Quando a semente não tem nenhuma, cria — senão a rota ficaria fora da
     * varredura, e é entre as que a semente não cobre (execução de automação,
     * lote de analítico, lançamento) que mora dado sensível.
     *
     * @param  class-string<Model>  $modelo
     */
    private function linhaSemeada(string $modelo): ?Model
    {
        return $modelo::query()->orderBy('id')->first() ?? $this->criarLinha($modelo);
    }

    /**
     * @param  class-string<Model>  $modelo
     */
    private function criarLinha(string $modelo): ?Model
    {
        $criadores = [
            Lancamento::class => fn () => $this->criarLancamento(),
            AutomacaoExecucao::class => fn () => $this->criarAutomacaoExecucao(),
            AnaliticoUnimedLote::class => fn () => $this->criarAnaliticoLote(),
        ];

        try {
            return isset($criadores[$modelo]) ? $criadores[$modelo]() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * `tenant_id` explícito em todos: o hook de `BelongsToTenant` o preenche a
     * partir do `TenantContext`, que só existe dentro de uma requisição — e
     * aqui estamos montando a base antes de qualquer uma.
     */
    private function criarLancamento(): ?Model
    {
        $guia = Guia::query()->orderBy('id')->first();
        $profissional = Profissional::query()->orderBy('id')->first();

        if (! $guia || ! $profissional) {
            return null;
        }

        return Lancamento::query()->create([
            'tenant_id' => $guia->tenant_id,
            'guia_id' => $guia->id,
            'profissional_id' => $profissional->id,
            'data_sessao' => '2026-09-01',
            'hora_inicio' => '08:00',
            'status' => 'completed',
        ]);
    }

    private function criarAutomacaoExecucao(): ?Model
    {
        $guia = Guia::query()->orderBy('id')->first();

        if (! $guia) {
            return null;
        }

        return AutomacaoExecucao::query()->create([
            'tenant_id' => $guia->tenant_id,
            'guia_id' => $guia->id,
            'operacao' => 'consultar_status',
            'status' => 'queued',
            'idempotency_key' => 'varredura-'.uniqid(),
            'payload' => [],
        ]);
    }

    private function criarAnaliticoLote(): ?Model
    {
        $guia = Guia::query()->orderBy('id')->first();

        if (! $guia) {
            return null;
        }

        return AnaliticoUnimedLote::query()->create([
            'tenant_id' => $guia->tenant_id,
            'arquivo_nome_original' => 'varredura.xlsx',
            'arquivo_path' => 'analiticos/varredura.xlsx',
            'status' => 'importado',
        ]);
    }

    /**
     * A mesma linha, na clínica vizinha.
     *
     * `replicate()` em vez de montar campo a campo: o teste não deve saber o
     * formato de 37 models. Campos que costumam ser únicos globalmente levam
     * um prefixo, senão a cópia bate na constraint em vez de virar o ataque.
     */
    private function replicarParaOutraClinica(Model $original, Tenant $vizinha): ?Model
    {
        return $this->replicarPara($original, $vizinha);
    }

    /** A mesma linha, na clínica indicada — inclusive a própria. */
    private function replicarPara(Model $original, Tenant $destino): ?Model
    {
        $copia = $original->replicate();
        $copia->tenant_id = $destino->id;

        foreach (['email', 'slug', 'cnpj', 'cpf', 'idempotency_key'] as $campo) {
            if (! is_null($copia->getAttribute($campo) ?? null)) {
                $copia->setAttribute($campo, 'vizinha-'.$copia->getAttribute($campo));
            }
        }

        try {
            $copia->save();
        } catch (QueryException) {
            return null;
        }

        return $copia->fresh();
    }

    private function urlDe(RotaDoLaravel $rota, string $parametro, mixed $valor): string
    {
        return '/'.str_replace(['{'.$parametro.'}', '{'.$parametro.'?}'], (string) $valor, $rota->uri());
    }

    /** Quantos itens a resposta lista, seja ela paginada ou crua. */
    private function quantidadeDeItens(mixed $corpo): ?int
    {
        if (! is_array($corpo)) {
            return null;
        }

        if (array_key_exists('data', $corpo) && is_array($corpo['data'])) {
            return count($corpo['data']);
        }

        return array_is_list($corpo) ? count($corpo) : null;
    }
}
