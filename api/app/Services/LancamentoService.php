<?php

namespace App\Services;

use App\Models\Guia;
use App\Models\Lancamento;
use App\Models\Profissional;
use App\Services\Concerns\AppliesOwnScope;
use App\Services\LancamentoTranscricaoService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Support\OrdenaListagem;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class LancamentoService
{
    use AppliesOwnScope;

    public function __construct(
        private readonly LancamentoTranscricaoService $transcricaoService
    ) {
    }

    /**
     * Filtro livre (`busca`): id do lançamento, número da guia, nome do
     * profissional executante, do paciente ou do médico solicitante — um
     * campo só, mesmo padrão do `busca` de GuiaService (SelecionarGuiaModal).
     * Médico só é alcançável via guia -> solicitacaoItem -> solicitacao
     * (Guia não tem medico_id direto).
     */
    private function queryFiltrada(array $filtros)
    {
        $query = $this->aplicarEscopoOwn(
            Lancamento::query(),
            'lancamentos.view',
            'lancamentos.viewOwn',
            fn ($query, $user) => $query->where('profissional_id', $user->profissional_id)
        );

        return $query
            ->when(Arr::get($filtros, 'profissional_id'), fn ($query, $profissionalId) => $query->where('profissional_id', $profissionalId))
            ->when(Arr::get($filtros, 'data_sessao'), fn ($query, $dataSessao) => $query->whereDate('data_sessao', $dataSessao))
            ->when(trim((string) Arr::get($filtros, 'busca', '')) !== '', function ($query) use ($filtros) {
                $busca = trim((string) $filtros['busca']);

                $query->where(function ($nested) use ($busca) {
                    $nested->whereHas('guia', fn ($q) => $q->where('numero_guia', 'like', '%'.$busca.'%'))
                        ->orWhereHas('profissional', fn ($q) => $q->where('nome', 'like', '%'.$busca.'%'))
                        ->orWhereHas('guia.paciente', fn ($q) => $q->where('nome', 'like', '%'.$busca.'%'))
                        ->orWhereHas(
                            'guia.solicitacaoItem.solicitacao.medico',
                            fn ($q) => $q->where('nome', 'like', '%'.$busca.'%'),
                        );

                    if (ctype_digit($busca)) {
                        $nested->orWhere('lancamentos.id', (int) $busca);
                    }
                });
            });
    }

    /**
     * Cada guia vira um grupo com todas as suas sessões (que baterem os
     * filtros) dentro — paginado pela GUIA, não pela sessão: uma guia com
     * muitos lançamentos nunca fica cortada entre duas páginas.
     *
     * @return LengthAwarePaginator<int, array{guia_id: int, guia: Guia|null, lancamentos: \Illuminate\Support\Collection<int, Lancamento>}>
     */
    public function listarAgrupadoPorGuia(array $filtros = [], int $porPagina = 15): LengthAwarePaginator
    {
        // Guias distintas com pelo menos 1 lançamento batendo os filtros,
        // ordenadas pela sessão mais recente de cada uma — guia mais ativa
        // primeiro. Clona a base pra essa consulta de agregação não vazar
        // pra consulta de detalhe logo abaixo.
        $paginaDeGuias = (clone $this->queryFiltrada($filtros))
            ->select('lancamentos.guia_id')
            ->selectRaw('MAX(lancamentos.data_sessao) as ultima_sessao')
            ->groupBy('lancamentos.guia_id')
            ->orderByDesc('ultima_sessao')
            ->orderByDesc('lancamentos.guia_id')
            ->paginate($porPagina);

        $guiaIds = collect($paginaDeGuias->items())->pluck('guia_id');

        $porGuia = (clone $this->queryFiltrada($filtros))
            ->whereIn('lancamentos.guia_id', $guiaIds)
            ->with([
                'profissional',
                'guia.paciente',
                'guia.solicitacaoItem.solicitacao.medico',
            ])
            ->orderBy('lancamentos.data_sessao')
            ->orderBy('lancamentos.id')
            ->get()
            ->groupBy('guia_id');

        $paginaDeGuias->setCollection(
            collect($paginaDeGuias->items())->map(fn ($linha) => [
                'guia_id' => $linha->guia_id,
                'guia' => $porGuia->get($linha->guia_id, collect())->first()?->guia,
                'lancamentos' => $porGuia->get($linha->guia_id, collect())->values(),
            ])->values()
        );

        return $paginaDeGuias;
    }

    /**
     * O MESMO escopo de `listar()` — mesma correção feita em
     * `GuiaService::buscar()`, pela mesma razão.
     *
     * `lancamentos.viewOwn` valia só na listagem: o detalhe era `findOrFail`
     * puro, então quem só podia ver as próprias sessões lia qualquer uma da
     * clínica incrementando o id. Sessão de outro some pelo filtro e vira 404,
     * não 403: negar com 403 confirmaria que o id existe.
     */
    public function buscar(int $id): Lancamento
    {
        $query = $this->aplicarEscopoOwn(
            Lancamento::query()->with(['guia', 'profissional']),
            'lancamentos.view',
            'lancamentos.viewOwn',
            fn ($query, $user) => $query->where('profissional_id', $user->profissional_id)
        );

        return $query->findOrFail($id);
    }

    public function registrar(Guia $guia, Profissional $profissional, Carbon $data): Lancamento
    {
        return $this->registrarSessao($guia, $profissional, [
            'data_sessao' => $data->toDateString(),
            'hora_inicio' => null,
            'hora_fim' => null,
            'acompanhante' => null,
            'resumo_atividades' => null,
            'transcricao_bruta' => null,
        ]);
    }

    public function registrarSessao(Guia $guia, Profissional $profissional, array $dados): Lancamento
    {
        return DB::transaction(function () use ($guia, $profissional, $dados) {
            $this->garantirVaga($guia);

            return $this->persistirSessao($guia, $profissional, $dados)->refresh();
        });
    }

    /**
     * Guia precisa estar aprovada/finalizada (Guia::aceitaLancamento()) e
     * ter sessão sobrando (Guia::sessoesDisponiveis()) — substituiu o antigo
     * AntecipacaoService::consumirCota(), sem tabela de cota separada.
     */
    private function garantirVaga(Guia $guia): void
    {
        if (! $guia->aceitaLancamento()) {
            throw ValidationException::withMessages([
                'guia' => ['Esta guia ainda não está aprovada — não dá para lançar sessão nela.'],
            ]);
        }

        if ($guia->sessoesDisponiveis() <= 0) {
            throw ValidationException::withMessages([
                'guia' => ['Esta guia já não tem sessões disponíveis.'],
            ]);
        }
    }

    public function atualizar(Lancamento $lancamento, array $dados): Lancamento
    {
        $lancamento->fill(array_filter([
            'profissional_id' => $dados['profissional_id'] ?? null,
            'data_sessao' => $dados['data_sessao'] ?? null,
            'hora_inicio' => $dados['hora_inicio'] ?? null,
            'hora_fim' => $dados['hora_fim'] ?? null,
            'acompanhante' => array_key_exists('acompanhante', $dados) ? $dados['acompanhante'] : null,
            'resumo_atividades' => array_key_exists('resumo_atividades', $dados) ? $dados['resumo_atividades'] : null,
            'observacoes' => array_key_exists('observacoes', $dados) ? $dados['observacoes'] : null,
        ], fn ($value) => $value !== null));
        $lancamento->save();

        return $lancamento->refresh();
    }

    /** Sem balde de cota pra reabrir — a cota é contada ao vivo, então apagar já basta. */
    public function remover(Lancamento $lancamento): void
    {
        $lancamento->delete();
    }

    /**
     * @return array{cabecalho: array<string, string|null>, sessoes: array<int, array<string, string|null>>}
     */
    public function previsualizarTranscricao(string $transcricao): array
    {
        return $this->transcricaoService->extrair($transcricao);
    }

    /**
     * @param array<int, array{data_sessao?:string|null, hora_inicio?:string|null, hora_fim?:string|null, acompanhante?:string|null, resumo_atividades?:string|null}> $sessoes
     * @return array{cabecalho: array<string, string|null>, sessoes: array<int, array<string, string|null>>, registros: array<int, Lancamento>}
     */
    public function confirmarTranscricao(Guia $guia, Profissional $profissional, ?string $transcricao, array $sessoes): array
    {
        // Nula quando a leitura veio de imagem/PDF (a IA já devolveu cabeçalho
        // e sessões prontos) ou de preenchimento manual da grade — só a
        // transcrição colada precisa ser reprocessada pelo parser de texto.
        $cabecalho = $transcricao !== null && trim($transcricao) !== ''
            ? $this->transcricaoService->extrair($transcricao)['cabecalho']
            : array_fill_keys(['guia_numero', 'clinica', 'paciente', 'numero_cartao', 'profissional_executante', 'terapia_aplicada'], null);

        // A grade tem 10 linhas fixas, mas linha em branco (sem data) não é
        // sessão — só as preenchidas viram lançamento.
        $sessoesPreenchidas = array_values(array_filter(
            $sessoes,
            fn ($sessao) => ! empty($sessao['data_sessao'])
        ));

        if ($sessoesPreenchidas === []) {
            throw new RuntimeException('Nenhuma linha da grade tem data preenchida.');
        }

        $registros = DB::transaction(function () use ($guia, $profissional, $transcricao, $sessoesPreenchidas) {
            $registros = [];

            foreach ($sessoesPreenchidas as $sessao) {
                $this->garantirVaga($guia);

                $registros[] = $this->persistirSessao($guia, $profissional, [
                    'data_sessao' => $sessao['data_sessao'],
                    'hora_inicio' => $sessao['hora_inicio'] ?? null,
                    'hora_fim' => $sessao['hora_fim'] ?? null,
                    'acompanhante' => $sessao['acompanhante'] ?? null,
                    'resumo_atividades' => $sessao['resumo_atividades'] ?? null,
                    'transcricao_bruta' => $transcricao,
                ]);
            }

            return $registros;
        });

        return [
            'cabecalho' => $cabecalho,
            'sessoes' => $sessoes,
            'registros' => $registros,
        ];
    }

    private function persistirSessao(Guia $guia, Profissional $profissional, array $dados): Lancamento
    {
        return Lancamento::query()->create([
            'tenant_id' => $guia->tenant_id,
            'guia_id' => $guia->id,
            'profissional_id' => $profissional->id,
            'data_sessao' => $dados['data_sessao'],
            'hora_inicio' => $dados['hora_inicio'] ?? null,
            'hora_fim' => $dados['hora_fim'] ?? null,
            'acompanhante' => $dados['acompanhante'] ?? null,
            'resumo_atividades' => $dados['resumo_atividades'] ?? null,
            'transcricao_bruta' => $dados['transcricao_bruta'] ?? null,
            'status' => 'completed',
            'observacoes' => $dados['observacoes'] ?? null,
        ]);
    }
}
