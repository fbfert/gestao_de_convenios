<?php

namespace App\Services;

use App\Exceptions\SolicitacaoStatusInvalidoException;
use App\Models\Guia;
use App\Models\Solicitacao;
use App\Support\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Support\OrdenaListagem;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class SolicitacaoService
{
    private const STATUS_PERMITIDOS = ['under_review', 'approved', 'denied'];

    public function listar(array $filtros = [], int $perPage = 15): LengthAwarePaginator
    {
        return Solicitacao::query()
            ->with([
                'paciente',
                'profissional',
                'especialidade',
                'convenio',
                'medico',
                'cidCadastro',
                'itens.especialidade.convenioMapeamentos',
                'itens.profissional',
                'itens.documentos',
                'itens.guia',
                'itens.automacaoExecucoes',
                'documentos',
                'guia.paciente',
                'guia.convenio',
                'guia.profissional',
                'guia.especialidade',
                'guia.solicitacaoItem.especialidade',
                'guia.solicitacaoItem.profissional',
                'guia.antecipacoes',
                'guia.conciliacoes',
            ])
            ->when(Arr::get($filtros, 'status'), fn ($query, $status) => $query->where('status', $status))
            ->when(Arr::get($filtros, 'convenio_id'), fn ($query, $convenioId) => $query->where('convenio_id', $convenioId))
            ->when(Arr::get($filtros, 'medico_id'), fn ($query, $medicoId) => $query->where('medico_id', $medicoId))
            ->tap(fn ($query) => OrdenaListagem::aplicar(
                $query->select('solicitacoes.*'),
                $filtros,
                [
                    'id' => 'solicitacoes.id',
                    'status' => 'solicitacoes.status',
                    'paciente' => fn ($query, $direcao) => $query
                        ->leftJoin('pacientes', 'pacientes.id', '=', 'solicitacoes.paciente_id')
                        ->orderBy('pacientes.nome', $direcao),
                    'convenio' => fn ($query, $direcao) => $query
                        ->leftJoin('convenios', 'convenios.id', '=', 'solicitacoes.convenio_id')
                        ->orderBy('convenios.nome', $direcao),
                    'medico' => fn ($query, $direcao) => $query
                        ->leftJoin('medicos', 'medicos.id', '=', 'solicitacoes.medico_id')
                        ->orderBy('medicos.nome', $direcao),
                ],
                padrao: 'solicitacoes.id',
                direcaoPadrao: 'desc',
                desempate: 'solicitacoes.id',
            ))
            ->paginate($perPage);
    }

    public function criar(array $dados): Solicitacao
    {
        $tenantId = $this->tenantId();
        $pedidoMedico = $this->resolverPedidoMedico($dados, $tenantId);

        return DB::transaction(function () use ($dados, $pedidoMedico, $tenantId) {
            $itens = $this->normalizarItens($dados);
            $primeiroItem = $itens[0];

            $solicitacao = Solicitacao::query()->create([
                'tenant_id' => $tenantId,
                'paciente_id' => $dados['paciente_id'],
                'profissional_id' => $dados['profissional_id'] ?? $primeiroItem['profissional_id'],
                'especialidade_id' => $dados['especialidade_id'] ?? $primeiroItem['especialidade_id'],
                'convenio_id' => $dados['convenio_id'],
                'medico_id' => $dados['medico_id'],
                'cid_id' => $dados['cid_id'] ?? null,
                'status' => 'under_review',
                'solicitado_em' => $dados['solicitado_em'],
                'observacoes' => $dados['observacoes'] ?? null,
            ] + $pedidoMedico['campos']);

            foreach ($itens as $item) {
                $solicitacao->itens()->create([
                    'tenant_id' => $tenantId,
                    'especialidade_id' => $item['especialidade_id'],
                    'profissional_id' => $item['profissional_id'],
                    'quantidade' => $item['quantidade'] ?? 10,
                    'status_operacional' => 'pending',
                    'observacoes' => $item['observacoes'] ?? null,
                ]);
            }

            if ($pedidoMedico['documento']) {
                $solicitacao->documentos()->create($pedidoMedico['documento'] + [
                    'tenant_id' => $tenantId,
                ]);
            }

            return $solicitacao->refresh();
        });
    }

    private function resolverPedidoMedico(array $dados, int $tenantId): array
    {
        $uploadId = $dados['pedido_medico_upload_id'] ?? null;

        if (! $uploadId) {
            return ['campos' => [], 'documento' => null];
        }

        $prefix = "pedidos-medicos/pendentes/{$tenantId}/";
        if (! str_starts_with($uploadId, $prefix) || ! Storage::disk('local')->exists($uploadId)) {
            return ['campos' => [], 'documento' => null];
        }

        $target = str_replace('/pendentes/', '/solicitacoes/', $uploadId);
        Storage::disk('local')->move($uploadId, $target);
        $nomeOriginal = $dados['pedido_medico_nome_original'] ?? basename($target);
        $mime = $dados['pedido_medico_mime'] ?? Storage::disk('local')->mimeType($target);

        return [
            'campos' => [
                'pedido_medico_path' => $target,
                'pedido_medico_nome_original' => $nomeOriginal,
                'pedido_medico_mime' => $mime,
                'pedido_medico_ai_result' => $dados['pedido_medico_ai_result'] ?? null,
            ],
            'documento' => [
                'solicitacao_item_id' => null,
                'tipo' => 'pedido_medico',
                'nome_original' => $nomeOriginal,
                'mime' => $mime,
                'path' => $target,
                'metadata' => $dados['pedido_medico_ai_result'] ?? null,
            ],
        ];
    }

    private function normalizarItens(array $dados): array
    {
        $itens = $dados['itens'] ?? [];

        if ($itens !== []) {
            return array_values(array_map(fn (array $item) => [
                'especialidade_id' => $item['especialidade_id'],
                'profissional_id' => $item['profissional_id'],
                'quantidade' => $item['quantidade'] ?? 10,
                'observacoes' => $item['observacoes'] ?? null,
            ], $itens));
        }

        return [[
            'especialidade_id' => $dados['especialidade_id'],
            'profissional_id' => $dados['profissional_id'],
            'quantidade' => 10,
            'observacoes' => null,
        ]];
    }

    /**
     * Edicao manual (admin): so medico/cid/data/observacoes — ver
     * UpdateSolicitacaoRequest. paciente_id/convenio_id ficam de fora de
     * proposito: sao identidade com dados gravados em Guia/Antecipacao/
     * Conciliacao ja geradas a partir do valor original.
     */
    public function atualizar(Solicitacao $solicitacao, array $dados): Solicitacao
    {
        $solicitacao->fill(array_filter([
            'medico_id' => $dados['medico_id'] ?? null,
            'cid_id' => $dados['cid_id'] ?? null,
            'solicitado_em' => $dados['solicitado_em'] ?? null,
            'observacoes' => array_key_exists('observacoes', $dados) ? $dados['observacoes'] : null,
        ], fn ($value) => $value !== null));

        $solicitacao->save();

        return $solicitacao->refresh();
    }

    public function aprovar(Solicitacao $solicitacao): Solicitacao
    {
        return $this->alterarStatus($solicitacao, 'approved');
    }

    public function negar(Solicitacao $solicitacao): Solicitacao
    {
        return $this->alterarStatus($solicitacao, 'denied');
    }

    public function alterarStatus(Solicitacao $solicitacao, string $destino): Solicitacao
    {
        if (! in_array($destino, self::STATUS_PERMITIDOS, true)) {
            throw SolicitacaoStatusInvalidoException::transicaoInvalida($solicitacao->status, $destino);
        }

        return DB::transaction(function () use ($solicitacao, $destino) {
            $solicitacao->update(['status' => $destino]);

            if ($destino === 'approved') {
                $this->sincronizarGuiaDaSolicitacao($solicitacao->refresh());
            }

            return $solicitacao->refresh();
        });
    }

    private function sincronizarGuiaDaSolicitacao(Solicitacao $solicitacao): void
    {
        if ($solicitacao->convenio?->connector_driver === 'unimed_rda') {
            return;
        }

        $item = $solicitacao->itens()->orderBy('id')->first();
        $guia = $solicitacao->guia;
        $dadosBase = [
            'tenant_id' => $solicitacao->tenant_id,
            'solicitacao_id' => $solicitacao->id,
            'solicitacao_item_id' => $item?->id,
            'convenio_id' => $solicitacao->convenio_id,
            'paciente_id' => $solicitacao->paciente_id,
            'profissional_id' => $item?->profissional_id ?? $solicitacao->profissional_id,
            'especialidade_id' => $item?->especialidade_id ?? $solicitacao->especialidade_id,
            'status' => 'under_review',
            'data_solicitacao' => $solicitacao->solicitado_em?->toDateString() ?? today()->toDateString(),
            'observacoes' => $solicitacao->observacoes,
        ];

        if ($guia) {
            $guia->fill($dadosBase);
            if (! $guia->numero_guia) {
                $guia->numero_guia = $this->numeroGuiaDaSolicitacao($solicitacao);
            }

            $guia->save();

            return;
        }

        Guia::query()->create($dadosBase + [
            'numero_guia' => $this->numeroGuiaDaSolicitacao($solicitacao),
            'tipo_terapia' => 'especializada',
            'data_finalizacao' => null,
            'senha' => null,
            'validade_senha' => null,
        ]);
    }

    private function numeroGuiaDaSolicitacao(Solicitacao $solicitacao): string
    {
        return 'GUIA-SOLICITACAO-'.$solicitacao->id;
    }

    private function tenantId(): int
    {
        $tenantId = TenantContext::get() ?? auth()->user()?->tenant_id;

        if (! $tenantId) {
            throw new RuntimeException('Tenant não resolvido para criar solicitação.');
        }

        return (int) $tenantId;
    }
}
