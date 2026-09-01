<?php

namespace App\Services;

use App\Models\Convenio;
use App\Models\Especialidade;
use App\Models\Guia;
use App\Models\GuiaImportLinha;
use App\Models\GuiaImportLote;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\Solicitacao;
use App\Support\Auditoria;
use App\Support\GuiaStatus;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Importação de Guias por planilha .xlsx — terceira entidade do mesmo padrão
 * de Pacientes/Solicitações. Chave de correspondência: número da guia,
 * escopado ao convênio (não há índice único no banco — se mais de uma guia
 * já existente bater no mesmo número+convênio, a linha vira erro em vez de
 * adivinhar qual atualizar).
 */
class GuiaImportService
{
    public function __construct(
        private readonly ImportacaoHeaderMappingAiService $headerMappingAi
    ) {
    }

    private const COLUNAS = [
        'numero_guia' => 'Número da guia',
        'convenio' => 'Convênio',
        'paciente_cpf' => 'Paciente CPF',
        'paciente_carteirinha' => 'Paciente Carteirinha',
        'profissional' => 'Profissional',
        'especialidade' => 'Especialidade',
        'tipo_terapia' => 'Tipo de terapia',
        'data_solicitacao' => 'Data da solicitação',
        'status' => 'Status',
        'senha' => 'Senha',
        'validade_senha' => 'Validade da senha',
        'data_finalizacao' => 'Data de finalização',
        'sessoes_solicitadas' => 'Sessões solicitadas',
        'sessoes_autorizadas' => 'Sessões autorizadas',
        'protocolo_operadora' => 'Protocolo operadora',
        'solicitacao_protocolo' => 'Protocolo da solicitação',
        'observacoes' => 'Observações',
    ];

    private const LINHA_EXEMPLO = [
        'numero_guia' => '50143966538',
        'convenio' => 'Unimed',
        'paciente_cpf' => '123.456.789-09',
        'paciente_carteirinha' => '',
        'profissional' => 'João Terapeuta',
        'especialidade' => 'Fonoaudiologia',
        'tipo_terapia' => 'especializada',
        'data_solicitacao' => '15/01/2026',
        'status' => '',
        'senha' => '',
        'validade_senha' => '',
        'data_finalizacao' => '',
        'sessoes_solicitadas' => '10',
        'sessoes_autorizadas' => '10',
        'protocolo_operadora' => '',
        'solicitacao_protocolo' => '',
        'observacoes' => '',
    ];

    private const TIPOS_TERAPIA = ['especializada', 'convencional', 'outro'];

    /** dados_json guarda a chave canônica — precisa reconhecer a própria
     * chave numa segunda passada (ver mesmo problema resolvido em
     * SolicitacaoImportService::STATUS_ACEITOS). */
    private const STATUS_ACEITOS = [
        '' => GuiaStatus::UNDER_REVIEW,
        'em_analise' => GuiaStatus::UNDER_REVIEW,
        'autorizado' => GuiaStatus::APPROVED,
        'finalizado' => GuiaStatus::FINALIZED,
        'aprovado' => GuiaStatus::FINALIZED,
        'negado' => GuiaStatus::DENIED,
        'cancelado' => GuiaStatus::CANCELED,
        'verificar_restricao' => GuiaStatus::NEEDS_VERIFICATION,
        GuiaStatus::UNDER_REVIEW => GuiaStatus::UNDER_REVIEW,
        GuiaStatus::APPROVED => GuiaStatus::APPROVED,
        GuiaStatus::FINALIZED => GuiaStatus::FINALIZED,
        GuiaStatus::DENIED => GuiaStatus::DENIED,
        GuiaStatus::CANCELED => GuiaStatus::CANCELED,
        GuiaStatus::NEEDS_VERIFICATION => GuiaStatus::NEEDS_VERIFICATION,
    ];

    public function gerarTemplate(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Guias');

        $coluna = 'A';
        foreach (self::COLUNAS as $chave => $rotulo) {
            $sheet->setCellValue("{$coluna}1", $rotulo);
            $sheet->getColumnDimension($coluna)->setAutoSize(true);
            $sheet->setCellValue("{$coluna}2", self::LINHA_EXEMPLO[$chave]);
            $coluna++;
        }
        $sheet->getStyle('A1:Q1')->getFont()->setBold(true);

        $writer = new Xlsx($spreadsheet);

        return new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="modelo-importacao-guias.xlsx"',
        ]);
    }

    /**
     * @return array{lote: array<string, mixed>, linhas: array<int, array<string, mixed>>}
     */
    public function previsualizar(UploadedFile $arquivo, int $tenantId): array
    {
        $spreadsheet = IOFactory::load($arquivo->getPathname());
        $sheet = $spreadsheet->getActiveSheet();
        $colunas = $this->mapearCabecalho($sheet);

        $obrigatorias = ['numero_guia', 'convenio', 'profissional', 'especialidade', 'tipo_terapia', 'data_solicitacao'];
        if (count(array_intersect($obrigatorias, array_keys($colunas))) < count($obrigatorias)) {
            $colunas = $this->reforcarComIA($colunas, $this->lerCabecalhoBruto($sheet), $tenantId);
        }

        if (count(array_intersect($obrigatorias, array_keys($colunas))) < count($obrigatorias)) {
            throw ValidationException::withMessages([
                'arquivo' => ['A planilha precisa ter pelo menos as colunas Número da guia, Convênio, Profissional, Especialidade, Tipo de terapia e Data da solicitação.'],
            ]);
        }

        $refs = $this->carregarReferencias($tenantId);
        $linhasProcessadas = [];
        $ultimaLinha = $sheet->getHighestDataRow();

        for ($numeroLinha = 2; $numeroLinha <= $ultimaLinha; $numeroLinha++) {
            $bruta = $this->lerLinha($sheet, $colunas, $numeroLinha);

            if ($this->linhaEmBranco($bruta)) {
                continue;
            }

            $linhasProcessadas[] = $this->normalizarLinha($bruta, $numeroLinha, $tenantId, $refs);
        }

        return $this->persistirLote($arquivo, $linhasProcessadas, $tenantId);
    }

    /**
     * @param array<int, int> $linhaIdsSelecionadas
     * @param array<int, array<string, mixed>> $edicoes
     * @return array{lote: array<string, mixed>, linhas: array<int, array<string, mixed>>}
     */
    public function confirmar(GuiaImportLote $lote, array $linhaIdsSelecionadas, array $edicoes, int $tenantId): array
    {
        if ($lote->status !== 'previsualizado') {
            throw ValidationException::withMessages([
                'lote' => ['Este lote já foi confirmado (ou cancelado) e não pode ser reenviado.'],
            ]);
        }

        $refs = $this->carregarReferencias($tenantId);
        $selecionadas = array_flip($linhaIdsSelecionadas);

        return DB::transaction(function () use ($lote, $selecionadas, $edicoes, $tenantId, $refs) {
            $totais = ['importados' => 0, 'atualizados' => 0, 'ignorados' => 0, 'invalidas' => 0];

            foreach ($lote->linhas as $linha) {
                $dados = array_merge($linha->dados_json, $edicoes[$linha->id] ?? []);
                $normalizado = $this->normalizarLinha($dados, $linha->linha, $tenantId, $refs);

                if (! isset($selecionadas[$linha->id])) {
                    $novoStatus = $normalizado['erros'] === [] ? 'ignorado' : 'erro';
                    $linha->update([
                        'status' => $novoStatus,
                        'dados_json' => $normalizado['dados'],
                        'erros_json' => $normalizado['erros'] ?: null,
                        'matched_guia_id' => $normalizado['matched_guia_id'],
                    ]);
                    $totais[$novoStatus === 'ignorado' ? 'ignorados' : 'invalidas']++;

                    continue;
                }

                if ($normalizado['erros'] !== []) {
                    $linha->update([
                        'status' => 'erro',
                        'dados_json' => $normalizado['dados'],
                        'erros_json' => $normalizado['erros'],
                        'matched_guia_id' => $normalizado['matched_guia_id'],
                    ]);
                    $totais['invalidas']++;

                    continue;
                }

                $existente = $normalizado['matched_guia_id'] ? Guia::query()->find($normalizado['matched_guia_id']) : null;
                $guia = $this->gravarGuia($normalizado['dados'], $tenantId, $existente);

                $linha->update([
                    'status' => $existente ? 'atualizado' : 'importado',
                    'dados_json' => $normalizado['dados'],
                    'erros_json' => null,
                    'matched_guia_id' => $guia->id,
                ]);
                $totais[$existente ? 'atualizados' : 'importados']++;
            }

            $lote->update([
                'status' => 'confirmado',
                'confirmado_em' => now(),
                'total_importados' => $totais['importados'],
                'total_atualizados' => $totais['atualizados'],
                'total_ignorados' => $totais['ignorados'],
                'total_invalidas' => $totais['invalidas'],
            ]);

            Auditoria::registrar(
                acao: 'guia_import.confirmado',
                entidade: 'guia_import_lotes',
                entidadeId: (int) $lote->id,
                payload: [
                    'arquivo' => $lote->arquivo_nome_original,
                    'importados' => $totais['importados'],
                    'atualizados' => $totais['atualizados'],
                    'ignorados' => $totais['ignorados'],
                    'invalidas' => $totais['invalidas'],
                ],
                tenantId: $tenantId,
            );

            return $this->serializarLote($lote->fresh('linhas'));
        });
    }

    /** @return array<string, \Illuminate\Support\Collection> */
    private function carregarReferencias(int $tenantId): array
    {
        return [
            'convenios' => Convenio::query()->where('tenant_id', $tenantId)->get(['id', 'nome']),
            'especialidades' => Especialidade::query()->where('tenant_id', $tenantId)->get(['id', 'nome']),
            'profissionais' => Profissional::query()->where('tenant_id', $tenantId)->get(['id', 'nome']),
        ];
    }

    /** @return array<string, string> letra da coluna -> texto literal do cabeçalho */
    private function lerCabecalhoBruto($sheet): array
    {
        $colunas = [];

        foreach ($sheet->getRowIterator(1, 1) as $row) {
            foreach ($row->getCellIterator() as $cell) {
                $valor = trim((string) $cell->getValue());

                if ($valor !== '') {
                    $colunas[$cell->getColumn()] = $valor;
                }
            }
        }

        return $colunas;
    }

    /**
     * Reforço por IA: só entra quando o casamento estrito não achou as
     * colunas obrigatórias — ver ImportacaoHeaderMappingAiService.
     *
     * @param array<string, string> $colunas chave canônica -> letra da coluna (já resolvidas)
     * @param array<string, string> $colunasBrutas letra da coluna -> texto literal do cabeçalho
     * @return array<string, string> chave canônica -> letra da coluna
     */
    private function reforcarComIA(array $colunas, array $colunasBrutas, int $tenantId): array
    {
        try {
            $mapeamento = $this->headerMappingAi->mapear($tenantId, array_values($colunasBrutas), self::COLUNAS);
        } catch (\Throwable) {
            return $colunas;
        }

        $textoParaColuna = array_flip($colunasBrutas);

        foreach ($mapeamento as $textoBruto => $chaveCanonica) {
            if (isset($colunas[$chaveCanonica]) || ! isset($textoParaColuna[$textoBruto])) {
                continue;
            }

            $colunas[$chaveCanonica] = $textoParaColuna[$textoBruto];
        }

        return $colunas;
    }

    /** @return array<string, string> */
    private function mapearCabecalho($sheet): array
    {
        $colunas = [];
        $aliases = $this->aliasesDeCabecalho();

        foreach ($sheet->getRowIterator(1, 1) as $row) {
            foreach ($row->getCellIterator() as $cell) {
                $valor = $this->normalizarTexto((string) $cell->getValue());

                if ($valor !== '' && isset($aliases[$valor])) {
                    $colunas[$aliases[$valor]] = $cell->getColumn();
                }
            }
        }

        return $colunas;
    }

    /** @return array<string, string> */
    private function aliasesDeCabecalho(): array
    {
        $aliases = [];

        foreach (array_keys(self::COLUNAS) as $chave) {
            $aliases[$chave] = $chave;
        }

        foreach (self::COLUNAS as $chave => $rotulo) {
            $aliases[$this->normalizarTexto($rotulo)] = $chave;
        }

        return $aliases;
    }

    private function normalizarTexto(string $texto): string
    {
        $semAcento = strtr($texto, [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a',
            'é' => 'e', 'ê' => 'e', 'í' => 'i',
            'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ü' => 'u', 'ç' => 'c',
            'Á' => 'a', 'À' => 'a', 'Ã' => 'a', 'Â' => 'a',
            'É' => 'e', 'Ê' => 'e', 'Í' => 'i',
            'Ó' => 'o', 'Ô' => 'o', 'Õ' => 'o',
            'Ú' => 'u', 'Ü' => 'u', 'Ç' => 'c',
        ]);

        $normalizado = preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($semAcento))) ?? '';

        return trim($normalizado, '_');
    }

    /** @param array<string, string> $colunas @return array<string, mixed> */
    private function lerLinha($sheet, array $colunas, int $numeroLinha): array
    {
        $bruta = [];

        foreach ($colunas as $chave => $coluna) {
            $bruta[$chave] = $this->valorCelula($sheet->getCell("{$coluna}{$numeroLinha}"));
        }

        return $bruta;
    }

    private function valorCelula(Cell $cell): mixed
    {
        $valor = $cell->getValue();

        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('Y-m-d');
        }

        if (is_numeric($valor) && ExcelDate::isDateTime($cell)) {
            return ExcelDate::excelToDateTimeObject($valor)->format('Y-m-d');
        }

        return is_string($valor) ? trim($valor) : $valor;
    }

    private function linhaEmBranco(array $dados): bool
    {
        foreach ($dados as $valor) {
            if ($valor !== null && $valor !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, \Illuminate\Support\Collection> $refs
     * @return array{dados: array<string, mixed>, erros: array<string, string>, matched_guia_id: int|null}
     */
    private function normalizarLinha(array $bruta, int $numeroLinha, int $tenantId, array $refs): array
    {
        $erros = [];

        $numeroGuia = trim((string) ($bruta['numero_guia'] ?? ''));
        if ($numeroGuia === '') {
            $erros['numero_guia'] = 'Número da guia é obrigatório.';
        }

        $nomeConvenio = trim((string) ($bruta['convenio'] ?? ''));
        $convenio = $nomeConvenio === '' ? null : $refs['convenios']->first(
            fn ($item) => mb_strtolower($item->nome) === mb_strtolower($nomeConvenio)
        );
        if ($nomeConvenio === '') {
            $erros['convenio'] = 'Convênio é obrigatório.';
        } elseif (! $convenio) {
            $erros['convenio'] = "Convênio \"{$nomeConvenio}\" não encontrado.";
        }

        $cpf = preg_replace('/\D+/', '', (string) ($bruta['paciente_cpf'] ?? '')) ?: null;
        $carteirinha = trim((string) ($bruta['paciente_carteirinha'] ?? '')) ?: null;
        $pacienteId = null;
        if ($cpf) {
            $pacienteId = Paciente::query()->where('tenant_id', $tenantId)->where('cpf', $cpf)->value('id');
        } elseif ($carteirinha && $convenio) {
            $pacienteId = Paciente::query()
                ->where('tenant_id', $tenantId)
                ->where('carteirinha', $carteirinha)
                ->where('convenio_id', $convenio->id)
                ->value('id');
        }
        if (! $cpf && ! $carteirinha) {
            $erros['paciente_cpf'] = 'Informe o CPF ou a carteirinha do paciente.';
        } elseif (! $pacienteId) {
            $erros['paciente_cpf'] = 'Paciente não encontrado — cadastre-o antes de importar.';
        }

        $nomeProfissional = trim((string) ($bruta['profissional'] ?? ''));
        $profissional = $nomeProfissional === '' ? null : $refs['profissionais']->first(
            fn ($item) => mb_strtolower($item->nome) === mb_strtolower($nomeProfissional)
        );
        if ($nomeProfissional === '') {
            $erros['profissional'] = 'Profissional é obrigatório.';
        } elseif (! $profissional) {
            $erros['profissional'] = "Profissional \"{$nomeProfissional}\" não encontrado.";
        }

        $nomeEspecialidade = trim((string) ($bruta['especialidade'] ?? ''));
        $especialidade = $nomeEspecialidade === '' ? null : $refs['especialidades']->first(
            fn ($item) => mb_strtolower($item->nome) === mb_strtolower($nomeEspecialidade)
        );
        if ($nomeEspecialidade === '') {
            $erros['especialidade'] = 'Especialidade é obrigatória.';
        } elseif (! $especialidade) {
            $erros['especialidade'] = "Especialidade \"{$nomeEspecialidade}\" não encontrada.";
        }

        $tipoTerapia = mb_strtolower(trim((string) ($bruta['tipo_terapia'] ?? '')));
        if (! in_array($tipoTerapia, self::TIPOS_TERAPIA, true)) {
            $erros['tipo_terapia'] = 'Tipo de terapia deve ser especializada, convencional ou outro.';
        }

        $dataSolicitacao = $this->normalizarData($bruta['data_solicitacao'] ?? null);
        if (! $dataSolicitacao) {
            $erros['data_solicitacao'] = 'Data da solicitação inválida ou ausente.';
        }

        $statusTexto = $this->normalizarTexto((string) ($bruta['status'] ?? ''));
        $status = self::STATUS_ACEITOS[$statusTexto] ?? null;
        if ($status === null) {
            $erros['status'] = 'Status inválido. Use Em análise, Autorizado, Finalizado, Negado, Cancelado ou Verificar Restrição (ou deixe em branco).';
        }

        $validadeSenha = $this->normalizarData($bruta['validade_senha'] ?? null);
        if (($bruta['validade_senha'] ?? null) && ! $validadeSenha) {
            $erros['validade_senha'] = 'Validade da senha inválida.';
        }

        $dataFinalizacao = $this->normalizarData($bruta['data_finalizacao'] ?? null);
        if (($bruta['data_finalizacao'] ?? null) && ! $dataFinalizacao) {
            $erros['data_finalizacao'] = 'Data de finalização inválida.';
        }

        $protocoloSolicitacao = trim((string) ($bruta['solicitacao_protocolo'] ?? '')) ?: null;
        $solicitacaoId = null;
        if ($protocoloSolicitacao) {
            $solicitacaoId = Solicitacao::query()
                ->where('tenant_id', $tenantId)
                ->where('protocolo_importacao', $protocoloSolicitacao)
                ->value('id');

            if (! $solicitacaoId) {
                $erros['solicitacao_protocolo'] = "Solicitação com protocolo \"{$protocoloSolicitacao}\" não encontrada.";
            }
        }

        $matchedGuiaId = null;
        if ($numeroGuia !== '' && $convenio) {
            $candidatos = Guia::query()
                ->where('tenant_id', $tenantId)
                ->where('convenio_id', $convenio->id)
                ->where('numero_guia', $numeroGuia)
                ->pluck('id');

            if ($candidatos->count() === 1) {
                $matchedGuiaId = $candidatos->first();
            } elseif ($candidatos->count() > 1) {
                $erros['numero_guia'] = 'Mais de uma guia já cadastrada com este número neste convênio — não dá para saber qual atualizar.';
            }
        }

        return [
            'dados' => [
                'linha' => $numeroLinha,
                'numero_guia' => $numeroGuia,
                'convenio' => $nomeConvenio,
                'convenio_id' => $convenio?->id,
                'paciente_cpf' => $cpf,
                'paciente_carteirinha' => $carteirinha,
                'paciente_id' => $pacienteId,
                'profissional' => $nomeProfissional,
                'profissional_id' => $profissional?->id,
                'especialidade' => $nomeEspecialidade,
                'especialidade_id' => $especialidade?->id,
                'tipo_terapia' => $tipoTerapia,
                'data_solicitacao' => $dataSolicitacao,
                'status' => $status,
                'senha' => trim((string) ($bruta['senha'] ?? '')) ?: null,
                'validade_senha' => $validadeSenha,
                'data_finalizacao' => $dataFinalizacao,
                'sessoes_solicitadas' => (int) preg_replace('/\D+/', '', (string) ($bruta['sessoes_solicitadas'] ?? '')) ?: null,
                'sessoes_autorizadas' => (int) preg_replace('/\D+/', '', (string) ($bruta['sessoes_autorizadas'] ?? '')) ?: null,
                'protocolo_operadora' => trim((string) ($bruta['protocolo_operadora'] ?? '')) ?: null,
                'solicitacao_protocolo' => $protocoloSolicitacao,
                'solicitacao_id' => $solicitacaoId,
                'observacoes' => trim((string) ($bruta['observacoes'] ?? '')) ?: null,
            ],
            'erros' => $erros,
            'matched_guia_id' => $matchedGuiaId,
        ];
    }

    private function normalizarData(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $valor)) {
            return (string) $valor;
        }

        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d'] as $formato) {
            try {
                return Carbon::createFromFormat($formato, trim((string) $valor))->format('Y-m-d');
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    /**
     * @param array<int, array{dados: array<string, mixed>, erros: array<string, string>, matched_guia_id: int|null}> $linhasProcessadas
     * @return array{lote: array<string, mixed>, linhas: array<int, array<string, mixed>>}
     */
    private function persistirLote(UploadedFile $arquivo, array $linhasProcessadas, int $tenantId): array
    {
        return DB::transaction(function () use ($arquivo, $linhasProcessadas, $tenantId) {
            $arquivoPath = Storage::disk('local')->putFileAs('guia-import', $arquivo, $arquivo->hashName());
            $validas = count(array_filter($linhasProcessadas, fn ($item) => $item['erros'] === []));

            $lote = GuiaImportLote::query()->create([
                'tenant_id' => $tenantId,
                'arquivo_nome_original' => $arquivo->getClientOriginalName(),
                'arquivo_path' => $arquivoPath,
                'status' => 'previsualizado',
                'total_linhas' => count($linhasProcessadas),
                'total_validas' => $validas,
                'total_invalidas' => count($linhasProcessadas) - $validas,
            ]);

            foreach ($linhasProcessadas as $item) {
                GuiaImportLinha::query()->create([
                    'tenant_id' => $tenantId,
                    'guia_import_lote_id' => $lote->id,
                    'linha' => $item['dados']['linha'],
                    'status' => $item['erros'] === [] ? 'valida' : 'erro',
                    'matched_guia_id' => $item['matched_guia_id'],
                    'dados_json' => $item['dados'],
                    'erros_json' => $item['erros'] ?: null,
                ]);
            }

            return $this->serializarLote($lote->fresh('linhas'));
        });
    }

    private function serializarLote(GuiaImportLote $lote): array
    {
        return [
            'lote' => [
                'id' => $lote->id,
                'arquivo_nome_original' => $lote->arquivo_nome_original,
                'status' => $lote->status,
                'confirmado_em' => $lote->confirmado_em?->toISOString(),
                'total_linhas' => $lote->total_linhas,
                'total_validas' => $lote->total_validas,
                'total_invalidas' => $lote->total_invalidas,
                'total_importados' => $lote->total_importados,
                'total_atualizados' => $lote->total_atualizados,
                'total_ignorados' => $lote->total_ignorados,
            ],
            'linhas' => $lote->linhas->map(fn (GuiaImportLinha $linha) => [
                'id' => $linha->id,
                'linha' => $linha->linha,
                'status' => $linha->status,
                'matched_guia_id' => $linha->matched_guia_id,
                'dados' => $linha->dados_json,
                'erros' => $linha->erros_json ?? [],
            ])->all(),
        ];
    }

    private function gravarGuia(array $dados, int $tenantId, ?Guia $existente): Guia
    {
        $atributos = [
            'tenant_id' => $tenantId,
            'solicitacao_id' => $dados['solicitacao_id'],
            'convenio_id' => $dados['convenio_id'],
            'paciente_id' => $dados['paciente_id'],
            'profissional_id' => $dados['profissional_id'],
            'especialidade_id' => $dados['especialidade_id'],
            'numero_guia' => $dados['numero_guia'],
            'tipo_terapia' => $dados['tipo_terapia'],
            'status' => $dados['status'],
            'sessoes_solicitadas' => $dados['sessoes_solicitadas'],
            'sessoes_autorizadas' => $dados['sessoes_autorizadas'],
            'protocolo_operadora' => $dados['protocolo_operadora'],
            'data_solicitacao' => $dados['data_solicitacao'],
            'data_finalizacao' => $dados['data_finalizacao'],
            'senha' => $dados['senha'],
            'validade_senha' => $dados['validade_senha'],
            'observacoes' => $dados['observacoes'],
        ];

        if ($existente) {
            $existente->fill($atributos);
            $existente->save();

            return $existente;
        }

        return Guia::query()->create($atributos);
    }
}
