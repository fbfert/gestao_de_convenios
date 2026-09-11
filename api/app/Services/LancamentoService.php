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

    public function listar(array $filtros = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->aplicarEscopoOwn(
            Lancamento::query()->with(['guia', 'profissional']),
            'lancamentos.view',
            'lancamentos.viewOwn',
            fn ($query, $user) => $query->where('profissional_id', $user->profissional_id)
        );

        return $query
            ->when(Arr::get($filtros, 'profissional_id'), fn ($query, $profissionalId) => $query->where('profissional_id', $profissionalId))
            ->when(Arr::get($filtros, 'data_sessao'), fn ($query, $dataSessao) => $query->whereDate('data_sessao', $dataSessao))
            ->tap(fn ($query) => OrdenaListagem::aplicar(
                $query->select('lancamentos.*'),
                $filtros,
                [
                    'id' => 'lancamentos.id',
                    'guia' => 'lancamentos.guia_id',
                    'data' => 'lancamentos.data_sessao',
                    'acompanhante' => 'lancamentos.acompanhante',
                    'status' => 'lancamentos.status',
                    'profissional' => fn ($query, $direcao) => $query
                        ->leftJoin('profissionais', 'profissionais.id', '=', 'lancamentos.profissional_id')
                        ->orderBy('profissionais.nome', $direcao),
                ],
                padrao: 'lancamentos.id',
                direcaoPadrao: 'desc',
                desempate: 'lancamentos.id',
            ))
            ->paginate($perPage);
    }

    public function buscar(int $id): Lancamento
    {
        return Lancamento::query()->with(['guia', 'profissional'])->findOrFail($id);
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
