<?php

namespace App\Services;

use App\Models\Antecipacao;
use App\Models\AntecipacaoImportLinha;
use App\Models\AntecipacaoImportLote;
use App\Models\Convenio;
use App\Models\Guia;
use App\Support\Auditoria;
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
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Importação de Antecipações por planilha .xlsx — quinta entidade do padrão.
 * Diferente de Pacientes/Solicitações/Guias, esta grava os valores DIRETO,
 * por cima da derivação automática normal (que só cria uma Antecipação
 * quando uma Guia é finalizada, ver AntecipacaoService::abrirCiclo) —
 * decisão deliberada do usuário: quem está migrando histórico já sabe a
 * cota real e não quer passar pela Guia de novo só pra abrir o ciclo.
 *
 * Uma guia pode ter mais de uma Antecipação (vários ciclos) — a chave de
 * correspondência é guia + ciclo_inicio, não só a guia.
 */
class AntecipacaoImportService
{
    private const COLUNAS = [
        'numero_guia' => 'Número da guia',
        'convenio' => 'Convênio',
        'ciclo_inicio' => 'Início do ciclo',
        'ciclo_fim' => 'Fim do ciclo',
        'qtd_autorizada' => 'Quantidade autorizada',
        'qtd_utilizada' => 'Quantidade utilizada',
        'status' => 'Status',
    ];

    private const LINHA_EXEMPLO = [
        'numero_guia' => '50143966538',
        'convenio' => 'Unimed',
        'ciclo_inicio' => '01/01/2026',
        'ciclo_fim' => '31/12/2026',
        'qtd_autorizada' => '10',
        'qtd_utilizada' => '0',
        'status' => '',
    ];

    private const STATUS_ACEITOS = [
        '' => null,
        'aberta' => 'open',
        'fechada' => 'closed',
        'open' => 'open',
        'closed' => 'closed',
    ];

    public function gerarTemplate(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Antecipações');

        $coluna = 'A';
        foreach (self::COLUNAS as $chave => $rotulo) {
            $sheet->setCellValue("{$coluna}1", $rotulo);
            $sheet->getColumnDimension($coluna)->setAutoSize(true);
            $sheet->setCellValue("{$coluna}2", self::LINHA_EXEMPLO[$chave]);
            $coluna++;
        }
        $sheet->getStyle('A1:G1')->getFont()->setBold(true);

        $writer = new Xlsx($spreadsheet);

        return new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="modelo-importacao-antecipacoes.xlsx"',
        ]);
    }

    /** @return array{lote: array<string, mixed>, linhas: array<int, array<string, mixed>>} */
    public function previsualizar(UploadedFile $arquivo, int $tenantId): array
    {
        $spreadsheet = IOFactory::load($arquivo->getPathname());
        $sheet = $spreadsheet->getActiveSheet();
        $colunas = $this->mapearCabecalho($sheet);

        $obrigatorias = ['numero_guia', 'convenio', 'ciclo_inicio', 'ciclo_fim', 'qtd_autorizada'];
        if (count(array_intersect($obrigatorias, array_keys($colunas))) < count($obrigatorias)) {
            throw new RuntimeException('A planilha precisa ter pelo menos as colunas Número da guia, Convênio, Início do ciclo, Fim do ciclo e Quantidade autorizada.');
        }

        $refs = ['convenios' => Convenio::query()->where('tenant_id', $tenantId)->get(['id', 'nome'])];
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
    public function confirmar(AntecipacaoImportLote $lote, array $linhaIdsSelecionadas, array $edicoes, int $tenantId): array
    {
        if ($lote->status !== 'previsualizado') {
            throw ValidationException::withMessages([
                'lote' => ['Este lote já foi confirmado (ou cancelado) e não pode ser reenviado.'],
            ]);
        }

        $refs = ['convenios' => Convenio::query()->where('tenant_id', $tenantId)->get(['id', 'nome'])];
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
                        'matched_antecipacao_id' => $normalizado['matched_antecipacao_id'],
                    ]);
                    $totais[$novoStatus === 'ignorado' ? 'ignorados' : 'invalidas']++;

                    continue;
                }

                if ($normalizado['erros'] !== []) {
                    $linha->update([
                        'status' => 'erro',
                        'dados_json' => $normalizado['dados'],
                        'erros_json' => $normalizado['erros'],
                        'matched_antecipacao_id' => $normalizado['matched_antecipacao_id'],
                    ]);
                    $totais['invalidas']++;

                    continue;
                }

                $existente = $normalizado['matched_antecipacao_id']
                    ? Antecipacao::query()->find($normalizado['matched_antecipacao_id'])
                    : null;

                $antecipacao = $this->gravarAntecipacao($normalizado['dados'], $tenantId, $existente);

                $linha->update([
                    'status' => $existente ? 'atualizado' : 'importado',
                    'dados_json' => $normalizado['dados'],
                    'erros_json' => null,
                    'matched_antecipacao_id' => $antecipacao->id,
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
                acao: 'antecipacao_import.confirmado',
                entidade: 'antecipacao_import_lotes',
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
     * @return array{dados: array<string, mixed>, erros: array<string, string>, matched_antecipacao_id: int|null}
     */
    private function normalizarLinha(array $bruta, int $numeroLinha, int $tenantId, array $refs): array
    {
        $erros = [];

        $numeroGuia = trim((string) ($bruta['numero_guia'] ?? ''));
        $nomeConvenio = trim((string) ($bruta['convenio'] ?? ''));
        $convenio = $nomeConvenio === '' ? null : $refs['convenios']->first(
            fn ($item) => mb_strtolower($item->nome) === mb_strtolower($nomeConvenio)
        );

        if ($numeroGuia === '') {
            $erros['numero_guia'] = 'Número da guia é obrigatório.';
        }
        if ($nomeConvenio === '') {
            $erros['convenio'] = 'Convênio é obrigatório.';
        } elseif (! $convenio) {
            $erros['convenio'] = "Convênio \"{$nomeConvenio}\" não encontrado.";
        }

        $guia = null;
        if ($numeroGuia !== '' && $convenio) {
            $candidatos = Guia::query()
                ->where('tenant_id', $tenantId)
                ->where('convenio_id', $convenio->id)
                ->where('numero_guia', $numeroGuia)
                ->get(['id', 'paciente_id', 'convenio_id']);

            if ($candidatos->count() === 0) {
                $erros['numero_guia'] = 'Guia não encontrada — importe a guia antes da Antecipação dela.';
            } elseif ($candidatos->count() > 1) {
                $erros['numero_guia'] = 'Mais de uma guia com este número neste convênio — não dá para saber qual usar.';
            } else {
                $guia = $candidatos->first();
            }
        }

        $cicloInicio = $this->normalizarData($bruta['ciclo_inicio'] ?? null);
        if (! $cicloInicio) {
            $erros['ciclo_inicio'] = 'Início do ciclo inválido ou ausente.';
        }

        $cicloFim = $this->normalizarData($bruta['ciclo_fim'] ?? null);
        if (! $cicloFim) {
            $erros['ciclo_fim'] = 'Fim do ciclo inválido ou ausente.';
        }

        $qtdAutorizada = (string) ($bruta['qtd_autorizada'] ?? '') !== ''
            ? (int) preg_replace('/\D+/', '', (string) $bruta['qtd_autorizada'])
            : null;
        if ($qtdAutorizada === null) {
            $erros['qtd_autorizada'] = 'Quantidade autorizada é obrigatória.';
        }

        $qtdUtilizada = (string) ($bruta['qtd_utilizada'] ?? '') !== ''
            ? (int) preg_replace('/\D+/', '', (string) $bruta['qtd_utilizada'])
            : 0;

        $statusTexto = $this->normalizarTexto((string) ($bruta['status'] ?? ''));
        $statusInformado = array_key_exists($statusTexto, self::STATUS_ACEITOS);
        if (! $statusInformado) {
            $erros['status'] = 'Status inválido. Use Aberta ou Fechada (ou deixe em branco para calcular sozinho).';
        }
        $status = $statusInformado
            ? (self::STATUS_ACEITOS[$statusTexto] ?? ($qtdUtilizada >= ($qtdAutorizada ?? 0) ? 'closed' : 'open'))
            : null;

        $matchedAntecipacaoId = null;
        if ($guia && $cicloInicio) {
            $matchedAntecipacaoId = Antecipacao::query()
                ->where('tenant_id', $tenantId)
                ->where('guia_id', $guia->id)
                ->whereDate('ciclo_inicio', $cicloInicio)
                ->value('id');
        }

        return [
            'dados' => [
                'linha' => $numeroLinha,
                'numero_guia' => $numeroGuia,
                'convenio' => $nomeConvenio,
                'convenio_id' => $convenio?->id,
                'guia_id' => $guia?->id,
                'paciente_id' => $guia?->paciente_id,
                'ciclo_inicio' => $cicloInicio,
                'ciclo_fim' => $cicloFim,
                'qtd_autorizada' => $qtdAutorizada,
                'qtd_utilizada' => $qtdUtilizada,
                'status' => $status,
            ],
            'erros' => $erros,
            'matched_antecipacao_id' => $matchedAntecipacaoId,
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
     * @param array<int, array{dados: array<string, mixed>, erros: array<string, string>, matched_antecipacao_id: int|null}> $linhasProcessadas
     * @return array{lote: array<string, mixed>, linhas: array<int, array<string, mixed>>}
     */
    private function persistirLote(UploadedFile $arquivo, array $linhasProcessadas, int $tenantId): array
    {
        return DB::transaction(function () use ($arquivo, $linhasProcessadas, $tenantId) {
            $arquivoPath = Storage::disk('local')->putFileAs('antecipacao-import', $arquivo, $arquivo->hashName());
            $validas = count(array_filter($linhasProcessadas, fn ($item) => $item['erros'] === []));

            $lote = AntecipacaoImportLote::query()->create([
                'tenant_id' => $tenantId,
                'arquivo_nome_original' => $arquivo->getClientOriginalName(),
                'arquivo_path' => $arquivoPath,
                'status' => 'previsualizado',
                'total_linhas' => count($linhasProcessadas),
                'total_validas' => $validas,
                'total_invalidas' => count($linhasProcessadas) - $validas,
            ]);

            foreach ($linhasProcessadas as $item) {
                AntecipacaoImportLinha::query()->create([
                    'tenant_id' => $tenantId,
                    'antecipacao_import_lote_id' => $lote->id,
                    'linha' => $item['dados']['linha'],
                    'status' => $item['erros'] === [] ? 'valida' : 'erro',
                    'matched_antecipacao_id' => $item['matched_antecipacao_id'],
                    'dados_json' => $item['dados'],
                    'erros_json' => $item['erros'] ?: null,
                ]);
            }

            return $this->serializarLote($lote->fresh('linhas'));
        });
    }

    private function serializarLote(AntecipacaoImportLote $lote): array
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
            'linhas' => $lote->linhas->map(fn (AntecipacaoImportLinha $linha) => [
                'id' => $linha->id,
                'linha' => $linha->linha,
                'status' => $linha->status,
                'matched_antecipacao_id' => $linha->matched_antecipacao_id,
                'dados' => $linha->dados_json,
                'erros' => $linha->erros_json ?? [],
            ])->all(),
        ];
    }

    private function gravarAntecipacao(array $dados, int $tenantId, ?Antecipacao $existente): Antecipacao
    {
        $atributos = [
            'tenant_id' => $tenantId,
            'guia_id' => $dados['guia_id'],
            'paciente_id' => $dados['paciente_id'],
            'convenio_id' => $dados['convenio_id'],
            'ciclo_inicio' => $dados['ciclo_inicio'],
            'ciclo_fim' => $dados['ciclo_fim'],
            'qtd_autorizada' => $dados['qtd_autorizada'],
            'qtd_utilizada' => $dados['qtd_utilizada'],
            'status' => $dados['status'],
        ];

        if ($existente) {
            $existente->fill($atributos);
            $existente->save();

            return $existente;
        }

        return Antecipacao::query()->create($atributos);
    }
}
