<?php

namespace App\Services;

use App\Exceptions\ConciliacaoStatusInvalidoException;
use App\Models\AnaliticoUnimedLote;
use App\Models\ConciliacaoFinanceira;
use App\Models\Guia;
use App\Models\Lancamento;
use App\Models\MovimentoFinanceiro;
use App\Models\Profissional;
use App\Services\Concerns\AppliesOwnScope;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Support\OrdenaListagem;
use Illuminate\Support\Arr;

class ConciliacaoService
{
    use AppliesOwnScope;

    private const PERCENTUAL_REPASSE_PADRAO = '64.00';

    public function __construct(
        private readonly TabelaValoresService $tabelaValoresService
    ) {
    }

    public function listar(array $filtros = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->aplicarEscopoOwn(
            ConciliacaoFinanceira::query()->with([
                'guia.profissional',
                'profissional',
                'movimentosFinanceiros.profissionalInformado',
                'movimentosFinanceiros.profissionalExecutor',
            ]),
            'conciliacoes.view',
            'conciliacoes.viewOwn',
            fn ($query, $user) => $query->where('profissional_id', $user->profissional_id)
        );

        return $query
            ->when(Arr::get($filtros, 'convenio_id'), fn ($query, $convenioId) => $query->whereHas('guia', fn ($guiaQuery) => $guiaQuery->where('convenio_id', $convenioId)))
            ->when(Arr::get($filtros, 'especialidade_id'), fn ($query, $especialidadeId) => $query->whereHas('guia', fn ($guiaQuery) => $guiaQuery->where('especialidade_id', $especialidadeId)))
            ->when(Arr::get($filtros, 'profissional_id'), fn ($query, $profissionalId) => $query->where('profissional_id', $profissionalId))
            ->when(Arr::get($filtros, 'status'), fn ($query, $status) => $query->where('status', $status))
            ->tap(fn ($query) => OrdenaListagem::aplicar(
                $query->select('conciliacoes_financeiras.*'),
                $filtros,
                [
                    'id' => 'conciliacoes_financeiras.id',
                    'qtd' => 'conciliacoes_financeiras.quantidade',
                    'valor_unitario' => 'conciliacoes_financeiras.valor_unitario',
                    'valor_total' => 'conciliacoes_financeiras.valor_total',
                    'status' => 'conciliacoes_financeiras.status',
                    'profissional' => fn ($query, $direcao) => $query
                        ->leftJoin('profissionais', 'profissionais.id', '=', 'conciliacoes_financeiras.profissional_id')
                        ->orderBy('profissionais.nome', $direcao),
                ],
                padrao: 'conciliacoes_financeiras.id',
                direcaoPadrao: 'desc',
                desempate: 'conciliacoes_financeiras.id',
            ))
            ->paginate($perPage);
    }

    public function gerarParaGuia(Guia $guia): ConciliacaoFinanceira
    {
        $guia->loadMissing('profissional');

        $loteImportado = $this->buscarLoteImportadoParaGuia($guia);
        $quantidade = $this->quantidadeConsolidada($guia, $loteImportado);
        $valorTotalRecebido = $this->valorTotalRecebido($guia, $loteImportado, $quantidade);

        $valorUnitario = $this->tabelaValoresService->obterValorVigente($guia, $guia->profissional_id);
        $valorTotal = number_format($valorTotalRecebido, 2, '.', '');
        $conciliacao = ConciliacaoFinanceira::query()->create([
            'tenant_id' => $guia->tenant_id,
            'guia_id' => $guia->id,
            'profissional_id' => $guia->profissional_id,
            'quantidade' => $quantidade,
            'valor_unitario' => $valorUnitario,
            'valor_total' => $valorTotal,
            'referencia_analitico_convenio' => $loteImportado ? 'LOTE-'.$loteImportado->id : null,
            'status' => 'pending',
            'conferido_em' => null,
        ]);

        $this->sincronizarMovimentosFinanceiros($conciliacao);

        return $conciliacao->fresh([
            'guia.profissional',
            'profissional',
            'movimentosFinanceiros.profissionalInformado',
            'movimentosFinanceiros.profissionalExecutor',
        ]);
    }

    /**
     * @return array{percentual_repasse_profissional: string, percentual_retencao_clinica: string, valor_repasse_unitario: string, valor_repasse_total: string, valor_retencao_unitario: string, valor_retencao_total: string}
     */
    public function calcularRepasse(Guia $guia, ?int $profissionalId = null, ?int $quantidade = null): array
    {
        $profissional = $profissionalId
            ? Profissional::query()->findOrFail($profissionalId)
            : $guia->profissional()->firstOrFail();

        $valorUnitario = $this->tabelaValoresService->obterValorVigente($guia, $profissional->id);
        $percentualRepasse = $this->percentualRepasseProfissional($profissional);
        $percentualRetencao = number_format(100 - (float) $percentualRepasse, 2, '.', '');
        $quantidade ??= 1;
        $valorRepasseUnitario = $this->calcularPercentual($valorUnitario, $percentualRepasse);
        $valorRetencaoUnitario = $this->calcularPercentual($valorUnitario, $percentualRetencao);

        return [
            'percentual_repasse_profissional' => $percentualRepasse,
            'percentual_retencao_clinica' => $percentualRetencao,
            'valor_repasse_unitario' => $valorRepasseUnitario,
            'valor_repasse_total' => number_format((float) $valorRepasseUnitario * $quantidade, 2, '.', ''),
            'valor_retencao_unitario' => $valorRetencaoUnitario,
            'valor_retencao_total' => number_format((float) $valorRetencaoUnitario * $quantidade, 2, '.', ''),
        ];
    }

    public function marcarConferido(ConciliacaoFinanceira $conciliacao): ConciliacaoFinanceira
    {
        if ($conciliacao->status !== 'pending') {
            throw ConciliacaoStatusInvalidoException::transicaoInvalida($conciliacao->status, 'reviewed');
        }

        $conciliacao->status = 'reviewed';
        $conciliacao->conferido_em = Carbon::now();
        $conciliacao->save();

        return $conciliacao->refresh();
    }

    public function marcarPago(ConciliacaoFinanceira $conciliacao): ConciliacaoFinanceira
    {
        if ($conciliacao->status !== 'reviewed') {
            throw ConciliacaoStatusInvalidoException::transicaoInvalida($conciliacao->status, 'paid');
        }

        $conciliacao->status = 'paid';
        $conciliacao->save();

        return $conciliacao->refresh();
    }

    private function sincronizarMovimentosFinanceiros(ConciliacaoFinanceira $conciliacao): void
    {
        $conciliacao->loadMissing(['guia.profissional', 'profissional', 'guia.antecipacoes.lancamentos.profissional']);

        MovimentoFinanceiro::query()
            ->where('conciliacao_financeira_id', $conciliacao->id)
            ->delete();

        $movimentos = [];
        $agora = now();

        $movimentos[] = [
            'tenant_id' => $conciliacao->tenant_id,
            'conciliacao_financeira_id' => $conciliacao->id,
            'guia_id' => $conciliacao->guia_id,
            'profissional_informado_id' => $conciliacao->guia?->profissional_id,
            'profissional_executor_id' => null,
            'tipo' => 'entrada',
            'origem' => 'analitico',
            'quantidade' => $conciliacao->quantidade,
            'valor_unitario' => $conciliacao->valor_unitario,
            'valor_total' => $conciliacao->valor_total,
            'referencia_analitico_convenio' => $conciliacao->guia?->numero_guia,
            'descricao' => 'Valor recebido da Unimed',
            'created_at' => $agora,
            'updated_at' => $agora,
        ];

        $lancamentos = Lancamento::query()
            ->with('profissional')
            ->where('status', 'completed')
            ->whereHas('antecipacao', function ($query) use ($conciliacao) {
                $query->where('guia_id', $conciliacao->guia_id);
            })
            ->get()
            ->groupBy('profissional_id');

        foreach ($lancamentos as $profissionalId => $grupo) {
            $repasse = $this->calcularRepasse($conciliacao->guia, (int) $profissionalId, $grupo->count());

            $movimentos[] = [
                'tenant_id' => $conciliacao->tenant_id,
                'conciliacao_financeira_id' => $conciliacao->id,
                'guia_id' => $conciliacao->guia_id,
                'profissional_informado_id' => $conciliacao->guia?->profissional_id,
                'profissional_executor_id' => (int) $profissionalId,
                'tipo' => 'saida',
                'origem' => 'repasse',
                'quantidade' => $grupo->count(),
                'valor_unitario' => $repasse['valor_repasse_unitario'],
                'valor_total' => $repasse['valor_repasse_total'],
                'referencia_analitico_convenio' => $conciliacao->guia?->numero_guia,
                'descricao' => 'Repasse ao profissional executor',
                'created_at' => $agora,
                'updated_at' => $agora,
            ];
        }

        MovimentoFinanceiro::query()->insert($movimentos);
    }

    private function percentualRepasseProfissional(?Profissional $profissional): string
    {
        return number_format((float) ($profissional?->percentual_repasse ?? self::PERCENTUAL_REPASSE_PADRAO), 2, '.', '');
    }

    private function calcularPercentual(string $valor, string $percentual): string
    {
        return number_format(((float) $valor * (float) $percentual) / 100, 2, '.', '');
    }

    private function buscarLoteImportadoParaGuia(Guia $guia): ?AnaliticoUnimedLote
    {
        return AnaliticoUnimedLote::query()
            ->where('tenant_id', $guia->tenant_id)
            ->whereHas('linhas', function ($query) use ($guia) {
                $query->where('numero_guia_operadora', $guia->numero_guia)
                    ->where('origem', 'analitico');
            })
            ->with(['linhas' => function ($query) use ($guia) {
                $query->where('numero_guia_operadora', $guia->numero_guia)
                    ->where('origem', 'analitico');
            }])
            ->latest('importado_em')
            ->first();
    }

    private function quantidadeConsolidada(Guia $guia, ?AnaliticoUnimedLote $loteImportado): int
    {
        if (! $loteImportado) {
            return Lancamento::query()
                ->where('status', 'completed')
                ->whereHas('antecipacao', function ($query) use ($guia) {
                    $query->where('guia_id', $guia->id);
                })
                ->count();
        }

        $quantidade = (int) $loteImportado->linhas->sum('qtd_normalizada');

        return $quantidade > 0 ? $quantidade : $loteImportado->linhas->count();
    }

    private function valorTotalRecebido(Guia $guia, ?AnaliticoUnimedLote $loteImportado, int $quantidade): float
    {
        if (! $loteImportado) {
            $valorUnitario = (float) $this->tabelaValoresService->obterValorVigente($guia, $guia->profissional_id);

            return $quantidade * $valorUnitario;
        }

        $valorTotal = (float) $loteImportado->linhas->sum('valor_normalizado');

        if ($valorTotal > 0) {
            return $valorTotal;
        }

        $valorUnitario = (float) $this->tabelaValoresService->obterValorVigente($guia, $guia->profissional_id);

        return $quantidade * $valorUnitario;
    }
}
