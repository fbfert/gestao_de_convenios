<?php

namespace App\Services;

use App\Models\ConciliacaoFinanceira;
use App\Models\ConciliacaoImportLinha;
use App\Models\ConciliacaoImportLote;
use App\Models\Convenio;
use App\Models\Guia;
use App\Models\Profissional;
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
 * Importação de Conciliações por planilha .xlsx — sexta e última entidade do
 * padrão. Igual Antecipações, grava os valores DIRETO por cima da derivação
 * normal (que só cria uma Conciliação via "Gerar conciliação" numa guia
 * finalizada) — mesma decisão deliberada do usuário. Não gera
 * MovimentoFinanceiro (isso é cálculo de repasse/retenção, fora do escopo
 * desta importação — fica só o registro financeiro em si).
 *
 * Chave de correspondência: guia (não há índice único no banco, mas na
 * prática é 1 conciliação por guia — se já existir mais de uma, a linha vira
 * erro em vez de adivinhar qual atualizar).
 */
class ConciliacaoImportService
{
    public function __construct(
        private readonly ImportacaoHeaderMappingAiService $headerMappingAi
    ) {
    }

    private const COLUNAS = [
        'numero_guia' => 'Número da guia',
        'convenio' => 'Convênio',
        'profissional' => 'Profissional',
        'quantidade' => 'Quantidade',
        'valor_unitario' => 'Valor unitário',
        'valor_total' => 'Valor total',
        'referencia_analitico_convenio' => 'Referência no analítico do convênio',
        'status' => 'Status',
        'conferido_em' => 'Conferido em',
    ];

    private const LINHA_EXEMPLO = [
        'numero_guia' => '50143966538',
        'convenio' => 'Unimed',
        'profissional' => 'João Terapeuta',
        'quantidade' => '10',
        'valor_unitario' => '80,00',
        'valor_total' => '800,00',
        'referencia_analitico_convenio' => '',
        'status' => '',
        'conferido_em' => '',
    ];

    private const STATUS_ACEITOS = [
        '' => 'pending',
        'pendente' => 'pending',
        'conferida' => 'reviewed',
        'paga' => 'paid',
        'pending' => 'pending',
        'reviewed' => 'reviewed',
        'paid' => 'paid',
    ];

    public function gerarTemplate(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Conciliações');

        $coluna = 'A';
        foreach (self::COLUNAS as $chave => $rotulo) {
            $sheet->setCellValue("{$coluna}1", $rotulo);
            $sheet->getColumnDimension($coluna)->setAutoSize(true);
            $sheet->setCellValue("{$coluna}2", self::LINHA_EXEMPLO[$chave]);
            $coluna++;
        }
        $sheet->getStyle('A1:I1')->getFont()->setBold(true);

        $writer = new Xlsx($spreadsheet);

        return new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="modelo-importacao-conciliacoes.xlsx"',
        ]);
    }

    /** @return array{lote: array<string, mixed>, linhas: array<int, array<string, mixed>>} */
    public function previsualizar(UploadedFile $arquivo, int $tenantId): array
    {
        $spreadsheet = IOFactory::load($arquivo->getPathname());
        $sheet = $spreadsheet->getActiveSheet();
        $colunas = $this->mapearCabecalho($sheet);

        $obrigatorias = ['numero_guia', 'convenio', 'profissional', 'quantidade'];
        if (count(array_intersect($obrigatorias, array_keys($colunas))) < count($obrigatorias)) {
            $colunas = $this->reforcarComIA($colunas, $this->lerCabecalhoBruto($sheet), $tenantId);
        }

        if (count(array_intersect($obrigatorias, array_keys($colunas))) < count($obrigatorias)) {
            throw new RuntimeException('A planilha precisa ter pelo menos as colunas Número da guia, Convênio, Profissional e Quantidade.');
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
    public function confirmar(ConciliacaoImportLote $lote, array $linhaIdsSelecionadas, array $edicoes, int $tenantId): array
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
                        'matched_conciliacao_id' => $normalizado['matched_conciliacao_id'],
                    ]);
                    $totais[$novoStatus === 'ignorado' ? 'ignorados' : 'invalidas']++;

                    continue;
                }

                if ($normalizado['erros'] !== []) {
                    $linha->update([
                        'status' => 'erro',
                        'dados_json' => $normalizado['dados'],
                        'erros_json' => $normalizado['erros'],
                        'matched_conciliacao_id' => $normalizado['matched_conciliacao_id'],
                    ]);
                    $totais['invalidas']++;

                    continue;
                }

                $existente = $normalizado['matched_conciliacao_id']
                    ? ConciliacaoFinanceira::query()->find($normalizado['matched_conciliacao_id'])
                    : null;

                $conciliacao = $this->gravarConciliacao($normalizado['dados'], $tenantId, $existente);

                $linha->update([
                    'status' => $existente ? 'atualizado' : 'importado',
                    'dados_json' => $normalizado['dados'],
                    'erros_json' => null,
                    'matched_conciliacao_id' => $conciliacao->id,
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
                acao: 'conciliacao_import.confirmado',
                entidade: 'conciliacao_import_lotes',
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
     * @return array{dados: array<string, mixed>, erros: array<string, string>, matched_conciliacao_id: int|null}
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

        $guiaId = null;
        if ($numeroGuia !== '' && $convenio) {
            $candidatos = Guia::query()
                ->where('tenant_id', $tenantId)
                ->where('convenio_id', $convenio->id)
                ->where('numero_guia', $numeroGuia)
                ->pluck('id');

            if ($candidatos->count() === 0) {
                $erros['numero_guia'] = 'Guia não encontrada — importe a guia antes da conciliação dela.';
            } elseif ($candidatos->count() > 1) {
                $erros['numero_guia'] = 'Mais de uma guia com este número neste convênio — não dá para saber qual usar.';
            } else {
                $guiaId = $candidatos->first();
            }
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

        $quantidade = (string) ($bruta['quantidade'] ?? '') !== ''
            ? (int) preg_replace('/\D+/', '', (string) $bruta['quantidade'])
            : null;
        if ($quantidade === null) {
            $erros['quantidade'] = 'Quantidade é obrigatória.';
        }

        $valorUnitario = $this->normalizarMoeda($bruta['valor_unitario'] ?? null);
        $valorTotal = $this->normalizarMoeda($bruta['valor_total'] ?? null);
        if ($valorTotal === null && $valorUnitario !== null && $quantidade !== null) {
            $valorTotal = round($valorUnitario * $quantidade, 2);
        }

        $statusTexto = $this->normalizarTexto((string) ($bruta['status'] ?? ''));
        $status = self::STATUS_ACEITOS[$statusTexto] ?? null;
        if ($status === null) {
            $erros['status'] = 'Status inválido. Use Pendente, Conferida ou Paga (ou deixe em branco).';
        }

        $conferidoEm = $this->normalizarData($bruta['conferido_em'] ?? null);
        if (($bruta['conferido_em'] ?? null) && ! $conferidoEm) {
            $erros['conferido_em'] = 'Data de conferência inválida.';
        }

        $matchedConciliacaoId = null;
        if ($guiaId) {
            $candidatas = ConciliacaoFinanceira::query()
                ->where('tenant_id', $tenantId)
                ->where('guia_id', $guiaId)
                ->pluck('id');

            if ($candidatas->count() === 1) {
                $matchedConciliacaoId = $candidatas->first();
            } elseif ($candidatas->count() > 1) {
                $erros['numero_guia'] = ($erros['numero_guia'] ?? '').' Esta guia já tem mais de uma conciliação — não dá para saber qual atualizar.';
            }
        }

        return [
            'dados' => [
                'linha' => $numeroLinha,
                'numero_guia' => $numeroGuia,
                'convenio' => $nomeConvenio,
                'convenio_id' => $convenio?->id,
                'guia_id' => $guiaId,
                'profissional' => $nomeProfissional,
                'profissional_id' => $profissional?->id,
                'quantidade' => $quantidade,
                'valor_unitario' => $valorUnitario,
                'valor_total' => $valorTotal,
                'referencia_analitico_convenio' => trim((string) ($bruta['referencia_analitico_convenio'] ?? '')) ?: null,
                'status' => $status,
                'conferido_em' => $conferidoEm,
            ],
            'erros' => $erros,
            'matched_conciliacao_id' => $matchedConciliacaoId,
        ];
    }

    private function normalizarMoeda(mixed $valor): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if (is_numeric($valor)) {
            return round((float) $valor, 2);
        }

        $normalizado = str_replace(['.', ' '], '', (string) $valor);
        $normalizado = str_replace(',', '.', $normalizado);

        return is_numeric($normalizado) ? round((float) $normalizado, 2) : null;
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
     * @param array<int, array{dados: array<string, mixed>, erros: array<string, string>, matched_conciliacao_id: int|null}> $linhasProcessadas
     * @return array{lote: array<string, mixed>, linhas: array<int, array<string, mixed>>}
     */
    private function persistirLote(UploadedFile $arquivo, array $linhasProcessadas, int $tenantId): array
    {
        return DB::transaction(function () use ($arquivo, $linhasProcessadas, $tenantId) {
            $arquivoPath = Storage::disk('local')->putFileAs('conciliacao-import', $arquivo, $arquivo->hashName());
            $validas = count(array_filter($linhasProcessadas, fn ($item) => $item['erros'] === []));

            $lote = ConciliacaoImportLote::query()->create([
                'tenant_id' => $tenantId,
                'arquivo_nome_original' => $arquivo->getClientOriginalName(),
                'arquivo_path' => $arquivoPath,
                'status' => 'previsualizado',
                'total_linhas' => count($linhasProcessadas),
                'total_validas' => $validas,
                'total_invalidas' => count($linhasProcessadas) - $validas,
            ]);

            foreach ($linhasProcessadas as $item) {
                ConciliacaoImportLinha::query()->create([
                    'tenant_id' => $tenantId,
                    'conciliacao_import_lote_id' => $lote->id,
                    'linha' => $item['dados']['linha'],
                    'status' => $item['erros'] === [] ? 'valida' : 'erro',
                    'matched_conciliacao_id' => $item['matched_conciliacao_id'],
                    'dados_json' => $item['dados'],
                    'erros_json' => $item['erros'] ?: null,
                ]);
            }

            return $this->serializarLote($lote->fresh('linhas'));
        });
    }

    private function serializarLote(ConciliacaoImportLote $lote): array
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
            'linhas' => $lote->linhas->map(fn (ConciliacaoImportLinha $linha) => [
                'id' => $linha->id,
                'linha' => $linha->linha,
                'status' => $linha->status,
                'matched_conciliacao_id' => $linha->matched_conciliacao_id,
                'dados' => $linha->dados_json,
                'erros' => $linha->erros_json ?? [],
            ])->all(),
        ];
    }

    private function gravarConciliacao(array $dados, int $tenantId, ?ConciliacaoFinanceira $existente): ConciliacaoFinanceira
    {
        $atributos = [
            'tenant_id' => $tenantId,
            'guia_id' => $dados['guia_id'],
            'profissional_id' => $dados['profissional_id'],
            'quantidade' => $dados['quantidade'],
            'valor_unitario' => $dados['valor_unitario'],
            'valor_total' => $dados['valor_total'],
            'referencia_analitico_convenio' => $dados['referencia_analitico_convenio'],
            'status' => $dados['status'],
            'conferido_em' => $dados['conferido_em'],
        ];

        if ($existente) {
            $existente->fill($atributos);
            $existente->save();

            return $existente;
        }

        return ConciliacaoFinanceira::query()->create($atributos);
    }
}
