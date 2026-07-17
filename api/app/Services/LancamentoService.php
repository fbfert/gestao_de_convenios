<?php

namespace App\Services;

use App\Models\Antecipacao;
use App\Models\Lancamento;
use App\Models\Profissional;
use App\Services\Concerns\AppliesOwnScope;
use App\Services\LancamentoTranscricaoService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LancamentoService
{
    use AppliesOwnScope;

    public function __construct(
        private readonly AntecipacaoService $antecipacaoService,
        private readonly LancamentoTranscricaoService $transcricaoService
    ) {
    }

    public function listar(array $filtros = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->aplicarEscopoOwn(
            Lancamento::query()->with(['antecipacao.guia', 'profissional']),
            'lancamentos.view',
            'lancamentos.viewOwn',
            fn ($query, $user) => $query->where('profissional_id', $user->profissional_id)
        );

        return $query
            ->when(Arr::get($filtros, 'profissional_id'), fn ($query, $profissionalId) => $query->where('profissional_id', $profissionalId))
            ->when(Arr::get($filtros, 'data_sessao'), fn ($query, $dataSessao) => $query->whereDate('data_sessao', $dataSessao))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function buscar(int $id): Lancamento
    {
        return Lancamento::query()->with(['antecipacao.guia', 'profissional'])->findOrFail($id);
    }

    public function registrar(Antecipacao $antecipacao, Profissional $profissional, Carbon $data): Lancamento
    {
        return $this->registrarSessao($antecipacao, $profissional, [
            'data_sessao' => $data->toDateString(),
            'hora_inicio' => null,
            'hora_fim' => null,
            'acompanhante' => null,
            'resumo_atividades' => null,
            'transcricao_bruta' => null,
        ]);
    }

    public function registrarSessao(Antecipacao $antecipacao, Profissional $profissional, array $dados): Lancamento
    {
        return DB::transaction(function () use ($antecipacao, $profissional, $dados) {
            $lancamento = $this->persistirSessao($antecipacao, $profissional, $dados);
            $this->antecipacaoService->consumirCota($antecipacao);

            return $lancamento->refresh();
        });
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

    public function remover(Lancamento $lancamento): void
    {
        DB::transaction(function () use ($lancamento) {
            $antecipacao = $lancamento->antecipacao()->lockForUpdate()->firstOrFail();

            if ($antecipacao->qtd_utilizada > 0) {
                $antecipacao->qtd_utilizada--;
                if ($antecipacao->status === 'closed' && $antecipacao->qtd_utilizada < $antecipacao->qtd_autorizada) {
                    $antecipacao->status = 'open';
                }
                $antecipacao->save();
            }

            $lancamento->delete();
        });
    }

    /**
     * @return array{cabecalho: array<string, string|null>, sessoes: array<int, array<string, string|null>>}
     */
    public function previsualizarTranscricao(string $transcricao): array
    {
        return $this->transcricaoService->extrair($transcricao);
    }

    /**
     * @param array<int, array{data_sessao:string, hora_inicio?:string|null, hora_fim?:string|null, acompanhante?:string|null, resumo_atividades?:string|null}> $sessoes
     * @return array{cabecalho: array<string, string|null>, sessoes: array<int, array<string, string|null>>, registros: array<int, Lancamento>}
     */
    public function confirmarTranscricao(Antecipacao $antecipacao, Profissional $profissional, string $transcricao, array $sessoes): array
    {
        $extraido = $this->transcricaoService->extrair($transcricao);

        if ($sessoes === []) {
            throw new RuntimeException('A transcrição não contém sessões reconhecíveis.');
        }

        $registros = DB::transaction(function () use ($antecipacao, $profissional, $transcricao, $sessoes) {
            $registros = [];

            foreach ($sessoes as $sessao) {
                $registros[] = $this->persistirSessao($antecipacao, $profissional, [
                    'data_sessao' => $sessao['data_sessao'],
                    'hora_inicio' => $sessao['hora_inicio'],
                    'hora_fim' => $sessao['hora_fim'],
                    'acompanhante' => $sessao['acompanhante'],
                    'resumo_atividades' => $sessao['resumo_atividades'],
                    'transcricao_bruta' => $transcricao,
                ]);
                $this->antecipacaoService->consumirCota($antecipacao);
            }

            return $registros;
        });

        return [
            'cabecalho' => $extraido['cabecalho'],
            'sessoes' => $sessoes,
            'registros' => $registros,
        ];
    }

    private function persistirSessao(Antecipacao $antecipacao, Profissional $profissional, array $dados): Lancamento
    {
        return Lancamento::query()->create([
            'tenant_id' => $antecipacao->tenant_id,
            'antecipacao_id' => $antecipacao->id,
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
