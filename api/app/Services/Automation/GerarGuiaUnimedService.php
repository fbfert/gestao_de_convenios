<?php

namespace App\Services\Automation;

use App\Exceptions\AutomationConcurrencyException;
use App\Jobs\ExecutarAutomacaoUnimedJob;
use App\Models\AutomacaoExecucao;
use App\Models\ConvenioEspecialidadeMapeamento;
use App\Models\ConvenioProfissionalMapeamento;
use App\Models\Guia;
use App\Models\SolicitacaoItem;
use App\Models\UnimedRdaCredential;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class GerarGuiaUnimedService
{
    private const ACTIVE_STATUSES = ['queued', 'running', 'uncertain'];

    public function __construct(private readonly AutomacaoService $automacoes)
    {
    }

    public function avaliar(SolicitacaoItem $item): array
    {
        $item->loadMissing([
            'solicitacao.convenio',
            'solicitacao.paciente',
            'solicitacao.documentos',
            'especialidade',
            'profissional',
            'guia',
            'automacaoExecucoes',
        ]);

        $motivos = [];
        $solicitacao = $item->solicitacao;
        $credential = UnimedRdaCredential::query()
            ->where('tenant_id', $item->tenant_id)
            ->where('ativo', true)
            ->first();

        if ($solicitacao?->status !== 'approved') {
            $motivos[] = 'A Solicitação precisa estar aprovada.';
        }

        if ($solicitacao?->convenio?->connector_driver !== 'unimed_rda') {
            $motivos[] = 'O Convênio não está configurado como Unimed RDA.';
        }

        if (! $credential || blank($credential->password)) {
            $motivos[] = 'A credencial Unimed ativa não está configurada.';
        }

        if (! $this->pedidoMedico($item)) {
            $motivos[] = 'Pedido Médico obrigatório não encontrado.';
        }

        if (! $item->profissional || ! $item->especialidade) {
            $motivos[] = 'Profissional e especialidade do item são obrigatórios.';
        }

        if ($solicitacao?->convenio_id && $item->especialidade_id && ! $this->mapeamentoEspecialidade($item)) {
            $motivos[] = 'Mapeamento Especialidade x Convênio não configurado.';
        }

        if ($solicitacao?->convenio_id && $item->profissional_id && ! $this->mapeamentoProfissional($item)) {
            $motivos[] = 'Mapeamento Profissional x Convênio não configurado.';
        }

        if ($item->guia) {
            $motivos[] = 'O item já possui Guia local vinculada.';
        }

        if ($item->automacaoExecucoes->whereIn('status', self::ACTIVE_STATUSES)->isNotEmpty()) {
            $motivos[] = 'Já existe execução Unimed ativa ou incerta para este item.';
        }

        return [
            'eligible' => $motivos === [],
            'motivos' => $motivos,
        ];
    }

    public function enviar(SolicitacaoItem $item): AutomacaoExecucao
    {
        $avaliacao = $this->avaliar($item);

        if (! $avaliacao['eligible']) {
            throw ValidationException::withMessages(['item' => $avaliacao['motivos']]);
        }

        try {
            $execucao = $this->automacoes->enfileirar(
                $item->tenant_id,
                'gerar_guia',
                $item,
                payload: $this->payloadPersistido($item),
            );
        } catch (AutomationConcurrencyException $exception) {
            throw ValidationException::withMessages([
                'item' => ["Já existe execução Unimed ativa para este tenant ({$exception->execucaoId})."],
            ]);
        }

        ExecutarAutomacaoUnimedJob::dispatch($execucao->id);

        return $execucao;
    }

    public function payloadParaWorker(AutomacaoExecucao $execucao): array
    {
        $execucao->loadMissing('solicitacaoItem.solicitacao');
        $credential = UnimedRdaCredential::query()
            ->where('tenant_id', $execucao->tenant_id)
            ->where('ativo', true)
            ->firstOrFail();

        return ($execucao->payload ?? []) + [
            'credential' => [
                'login' => $credential->login,
                'password' => $credential->password,
                'base_url' => $credential->base_url,
                'nome_contratado' => $credential->nome_contratado,
            ],
        ];
    }

    public function aplicarResultado(AutomacaoExecucao $execucao, array $resultado): AutomacaoExecucao
    {
        if (($resultado['status'] ?? null) === 'uncertain') {
            $execucao = $this->automacoes->concluir($execucao, $resultado);
            $execucao->solicitacaoItem?->update(['status_operacional' => 'uncertain']);

            return $execucao;
        }

        return DB::transaction(function () use ($execucao, $resultado) {
            $execucao = $this->automacoes->concluir($execucao, $resultado);

            if ($execucao->status === 'succeeded') {
                $this->criarOuAtualizarGuia($execucao, $resultado);
            }

            return $execucao->refresh();
        });
    }

    private function criarOuAtualizarGuia(AutomacaoExecucao $execucao, array $resultado): Guia
    {
        $item = $execucao->solicitacaoItem()->with(['solicitacao', 'profissional', 'especialidade'])->firstOrFail();
        $solicitacao = $item->solicitacao;

        $guia = Guia::query()->firstOrNew([
            'tenant_id' => $execucao->tenant_id,
            'solicitacao_item_id' => $item->id,
        ]);

        $guia->fill([
            'tenant_id' => $execucao->tenant_id,
            'solicitacao_id' => $solicitacao->id,
            'solicitacao_item_id' => $item->id,
            'automacao_execucao_id' => $execucao->id,
            'convenio_id' => $solicitacao->convenio_id,
            'paciente_id' => $solicitacao->paciente_id,
            'profissional_id' => $item->profissional_id,
            'especialidade_id' => $item->especialidade_id,
            'numero_guia' => $resultado['numero_guia'] ?? null,
            'tipo_terapia' => 'especializada',
            'status' => $resultado['guia_status'] ?? $resultado['status_guia'] ?? 'under_review',
            'unimed_status' => $resultado['unimed_status'] ?? $resultado['status_operadora'] ?? null,
            'sessoes_solicitadas' => $resultado['sessoes_solicitadas'] ?? null,
            'sessoes_autorizadas' => $resultado['sessoes_autorizadas'] ?? null,
            'protocolo_operadora' => $resultado['protocolo_operadora'] ?? null,
            'senha' => $resultado['senha'] ?? null,
            'data_solicitacao' => today(),
            'observacoes' => $solicitacao->observacoes,
        ])->save();

        $item->update(['status_operacional' => 'guia_generated']);

        return $guia;
    }

    private function payloadPersistido(SolicitacaoItem $item): array
    {
        $item->loadMissing([
            'solicitacao.paciente',
            'solicitacao.convenio',
            'solicitacao.medico',
            'solicitacao.cidCadastro',
            'solicitacao.documentos',
            'documentos',
            'especialidade',
            'profissional',
        ]);
        $pedidoMedico = $this->pedidoMedico($item);
        $mapeamentoEspecialidade = $this->mapeamentoEspecialidade($item);
        $mapeamentoProfissional = $this->mapeamentoProfissional($item);
        $documentos = $this->documentosPayload($item);

        return [
            'solicitacao_id' => $item->solicitacao_id,
            'solicitacao_item_id' => $item->id,
            'cid' => $item->solicitacao->cid_id ? $item->solicitacao->cidCadastro?->codigo : $item->solicitacao->cid,
            'medico' => [
                'id' => $item->solicitacao->medico_id,
                'nome' => $item->solicitacao->medico?->nome,
                'crm' => $item->solicitacao->medico?->crm,
            ],
            'paciente' => [
                'id' => $item->solicitacao->paciente_id,
                'nome' => $item->solicitacao->paciente?->nome,
                'carteirinha' => $item->solicitacao->paciente?->carteirinha,
            ],
            'convenio_id' => $item->solicitacao->convenio_id,
            'especialidade' => $item->especialidade?->nome,
            'codigo_procedimento' => $mapeamentoEspecialidade?->codigo_procedimento,
            'descricao_operadora' => $mapeamentoEspecialidade?->descricao_operadora,
            'quantidade_padrao' => $mapeamentoEspecialidade?->quantidade_padrao,
            'usa_descricao_generica' => $mapeamentoEspecialidade?->usa_descricao_generica,
            'valor_generico' => $mapeamentoEspecialidade?->valor_generico,
            'profissional' => $item->profissional?->nome,
            'codigo_profissional_operadora' => $mapeamentoProfissional?->codigo_operadora,
            'nome_profissional_operadora' => $mapeamentoProfissional?->nome_operadora,
            'quantidade' => $item->quantidade,
            'pedido_medico' => $pedidoMedico ? [
                'id' => $pedidoMedico->id,
                'nome_original' => $pedidoMedico->nome_original,
                'mime' => $pedidoMedico->mime,
                'path' => $pedidoMedico->path,
                'local_path' => $this->localPath($pedidoMedico->path),
                'size' => $this->fileSize($pedidoMedico->path),
            ] : null,
            'anexos' => $documentos,
        ];
    }

    private function pedidoMedico(SolicitacaoItem $item)
    {
        $item->loadMissing('solicitacao.documentos');

        return $item->solicitacao?->documentos
            ->firstWhere('tipo', 'pedido_medico');
    }

    private function documentosPayload(SolicitacaoItem $item): array
    {
        $item->loadMissing(['solicitacao.documentos', 'documentos']);

        // Documentos da Solicitação valem para todos os itens; os por item só valem para
        // o seu. Sem o filtro, o Plano de uma especialidade subiria na guia de outra.
        return $item->solicitacao?->documentos
            ->whereNull('solicitacao_item_id')
            ->merge($item->documentos)
            ->reject(fn ($documento) => $documento->tipo === 'pedido_medico')
            ->map(fn ($documento) => [
                'id' => $documento->id,
                'tipo' => $documento->tipo,
                'nome_original' => $documento->nome_original,
                'mime' => $documento->mime,
                'path' => $documento->path,
                'local_path' => $this->localPath($documento->path),
                'size' => $this->fileSize($documento->path),
            ])
            ->values()
            ->all() ?? [];
    }

    private function localPath(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        return Storage::disk('local')->path($path);
    }

    private function fileSize(?string $path): ?int
    {
        if (blank($path) || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        return Storage::disk('local')->size($path);
    }

    private function mapeamentoEspecialidade(SolicitacaoItem $item): ?ConvenioEspecialidadeMapeamento
    {
        $item->loadMissing('solicitacao');

        return ConvenioEspecialidadeMapeamento::query()
            ->where('tenant_id', $item->tenant_id)
            ->where('convenio_id', $item->solicitacao?->convenio_id)
            ->where('especialidade_id', $item->especialidade_id)
            ->where('ativo', true)
            ->first();
    }

    private function mapeamentoProfissional(SolicitacaoItem $item): ?ConvenioProfissionalMapeamento
    {
        $item->loadMissing('solicitacao');

        return ConvenioProfissionalMapeamento::query()
            ->where('tenant_id', $item->tenant_id)
            ->where('convenio_id', $item->solicitacao?->convenio_id)
            ->where('profissional_id', $item->profissional_id)
            ->where('ativo', true)
            ->first();
    }
}
