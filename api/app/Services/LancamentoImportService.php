<?php

namespace App\Services;

use App\Models\Antecipacao;
use App\Models\Convenio;
use App\Models\Guia;
use App\Models\Lancamento;
use App\Models\LancamentoImportLinha;
use App\Models\LancamentoImportLote;
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
 * Importação de Sessões (Lançamentos) por planilha .xlsx — quarta entidade
 * do padrão. Diferente das anteriores, uma sessão histórica é gravada mesmo
 * que ultrapasse a cota autorizada da Antecipação — a sessão já aconteceu de
 * verdade, travar por cota (bookkeeping) esconderia dado real; ver
 * AntecipacaoService::consumirCotaForcado().
 *
 * A sessão não referencia a Antecipação diretamente na planilha (número
 * interno, sem sentido pra quem preenche) — resolve pelo número da guia +
 * convênio, e dentro das Antecipações da guia escolhe a que cobre a data da
 * sessão pelo ciclo (ciclo_inicio/ciclo_fim). Chave de correspondência pra
 * reimportação: antecipacao + data + hora_inicio + profissional — sem isso,
 * reimportar a mesma planilha duplicaria sessões E consumiria cota duas
 * vezes.
 */
class LancamentoImportService
{
    private const COLUNAS = [
        'numero_guia' => 'Número da guia',
        'convenio' => 'Convênio',
        'profissional' => 'Profissional',
        'data_sessao' => 'Data da sessão',
        'hora_inicio' => 'Hora início',
        'hora_fim' => 'Hora fim',
        'acompanhante' => 'Acompanhante',
        'resumo_atividades' => 'Resumo das atividades',
        'status' => 'Status',
        'observacoes' => 'Observações',
    ];

    private const LINHA_EXEMPLO = [
        'numero_guia' => '50143966538',
        'convenio' => 'Unimed',
        'profissional' => 'João Terapeuta',
        'data_sessao' => '20/01/2026',
        'hora_inicio' => '14:00',
        'hora_fim' => '15:00',
        'acompanhante' => 'Mãe',
        'resumo_atividades' => 'Sessão de fonoaudiologia.',
        'status' => '',
        'observacoes' => '',
    ];

    private const STATUS_ACEITOS = [
        '' => 'completed',
        'concluido' => 'completed',
        'perdido' => 'missed',
        'cancelado' => 'canceled',
        'completed' => 'completed',
        'missed' => 'missed',
        'canceled' => 'canceled',
    ];

    public function gerarTemplate(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Sessões');

        $coluna = 'A';
        foreach (self::COLUNAS as $chave => $rotulo) {
            $sheet->setCellValue("{$coluna}1", $rotulo);
            $sheet->getColumnDimension($coluna)->setAutoSize(true);
            $sheet->setCellValue("{$coluna}2", self::LINHA_EXEMPLO[$chave]);
            $coluna++;
        }
        $sheet->getStyle('A1:J1')->getFont()->setBold(true);

        $writer = new Xlsx($spreadsheet);

        return new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="modelo-importacao-sessoes.xlsx"',
        ]);
    }

    /** @return array{lote: array<string, mixed>, linhas: array<int, array<string, mixed>>} */
    public function previsualizar(UploadedFile $arquivo, int $tenantId): array
    {
        $spreadsheet = IOFactory::load($arquivo->getPathname());
        $sheet = $spreadsheet->getActiveSheet();
        $colunas = $this->mapearCabecalho($sheet);

        $obrigatorias = ['numero_guia', 'convenio', 'profissional', 'data_sessao'];
        if (count(array_intersect($obrigatorias, array_keys($colunas))) < count($obrigatorias)) {
            throw new RuntimeException('A planilha precisa ter pelo menos as colunas Número da guia, Convênio, Profissional e Data da sessão.');
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
    public function confirmar(LancamentoImportLote $lote, array $linhaIdsSelecionadas, array $edicoes, int $tenantId): array
    {
        if ($lote->status !== 'previsualizado') {
            throw ValidationException::withMessages([
                'lote' => ['Este lote já foi confirmado (ou cancelado) e não pode ser reenviado.'],
            ]);
        }

        $refs = $this->carregarReferencias($tenantId);
        $selecionadas = array_flip($linhaIdsSelecionadas);
        $antecipacaoService = app(AntecipacaoService::class);

        return DB::transaction(function () use ($lote, $selecionadas, $edicoes, $tenantId, $refs, $antecipacaoService) {
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
                        'matched_lancamento_id' => $normalizado['matched_lancamento_id'],
                    ]);
                    $totais[$novoStatus === 'ignorado' ? 'ignorados' : 'invalidas']++;

                    continue;
                }

                if ($normalizado['erros'] !== []) {
                    $linha->update([
                        'status' => 'erro',
                        'dados_json' => $normalizado['dados'],
                        'erros_json' => $normalizado['erros'],
                        'matched_lancamento_id' => $normalizado['matched_lancamento_id'],
                    ]);
                    $totais['invalidas']++;

                    continue;
                }

                $existente = $normalizado['matched_lancamento_id']
                    ? Lancamento::query()->find($normalizado['matched_lancamento_id'])
                    : null;

                $lancamento = $this->gravarLancamento($normalizado['dados'], $tenantId, $existente, $antecipacaoService);

                $linha->update([
                    'status' => $existente ? 'atualizado' : 'importado',
                    'dados_json' => $normalizado['dados'],
                    'erros_json' => null,
                    'matched_lancamento_id' => $lancamento->id,
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
                acao: 'lancamento_import.confirmado',
                entidade: 'lancamento_import_lotes',
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

    /**
     * Datas/horas do Excel viram 'Y-m-d H:i:s' completo — diferente do resto
     * dos importadores (que só precisam da data), aqui a hora importa de
     * verdade, então não dá pra descartar a parte de tempo na leitura.
     */
    private function valorCelula(Cell $cell): mixed
    {
        $valor = $cell->getValue();

        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('Y-m-d H:i:s');
        }

        if (is_numeric($valor) && ExcelDate::isDateTime($cell)) {
            return ExcelDate::excelToDateTimeObject($valor)->format('Y-m-d H:i:s');
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
     * @return array{dados: array<string, mixed>, erros: array<string, string>, matched_lancamento_id: int|null}
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
        $antecipacaoId = null;
        if ($numeroGuia !== '' && $convenio) {
            $guias = Guia::query()
                ->where('tenant_id', $tenantId)
                ->where('convenio_id', $convenio->id)
                ->where('numero_guia', $numeroGuia)
                ->pluck('id');

            if ($guias->count() === 0) {
                $erros['numero_guia'] = 'Guia não encontrada — importe a guia antes das sessões dela.';
            } elseif ($guias->count() > 1) {
                $erros['numero_guia'] = 'Mais de uma guia com este número neste convênio — não dá para saber qual usar.';
            } else {
                $guiaId = $guias->first();
            }
        }

        $dataSessao = $this->normalizarData($bruta['data_sessao'] ?? null);
        if (! $dataSessao) {
            $erros['data_sessao'] = 'Data da sessão inválida ou ausente.';
        }

        if ($guiaId && $dataSessao && ! isset($erros['numero_guia'])) {
            $antecipacoes = Antecipacao::query()->where('guia_id', $guiaId)->get(['id', 'ciclo_inicio', 'ciclo_fim']);

            if ($antecipacoes->count() === 0) {
                $erros['numero_guia'] = 'Guia sem Antecipação — finalize a guia no sistema ou importe a Antecipação primeiro.';
            } elseif ($antecipacoes->count() === 1) {
                $antecipacaoId = $antecipacoes->first()->id;
            } else {
                $noCiclo = $antecipacoes->filter(
                    fn ($a) => $dataSessao >= $a->ciclo_inicio->toDateString() && $dataSessao <= $a->ciclo_fim->toDateString()
                );

                if ($noCiclo->count() === 1) {
                    $antecipacaoId = $noCiclo->first()->id;
                } else {
                    $erros['numero_guia'] = 'Esta guia tem mais de uma Antecipação e nenhuma (ou mais de uma) cobre a data desta sessão pelo ciclo — não dá para saber qual usar.';
                }
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

        $horaInicio = $this->normalizarHora($bruta['hora_inicio'] ?? null);
        $horaFim = $this->normalizarHora($bruta['hora_fim'] ?? null);

        $statusTexto = $this->normalizarTexto((string) ($bruta['status'] ?? ''));
        $status = self::STATUS_ACEITOS[$statusTexto] ?? null;
        if ($status === null) {
            $erros['status'] = 'Status inválido. Use Concluído, Perdido ou Cancelado (ou deixe em branco).';
        }

        $matchedLancamentoId = null;
        if ($antecipacaoId && $dataSessao && $profissional) {
            $matchedLancamentoId = Lancamento::query()
                ->where('tenant_id', $tenantId)
                ->where('antecipacao_id', $antecipacaoId)
                ->whereDate('data_sessao', $dataSessao)
                ->where('profissional_id', $profissional->id)
                ->where(function ($query) use ($horaInicio) {
                    $horaInicio ? $query->where('hora_inicio', $horaInicio) : $query->whereNull('hora_inicio');
                })
                ->value('id');
        }

        return [
            'dados' => [
                'linha' => $numeroLinha,
                'numero_guia' => $numeroGuia,
                'convenio' => $nomeConvenio,
                'convenio_id' => $convenio?->id,
                'guia_id' => $guiaId,
                'antecipacao_id' => $antecipacaoId,
                'profissional' => $nomeProfissional,
                'profissional_id' => $profissional?->id,
                'data_sessao' => $dataSessao,
                'hora_inicio' => $horaInicio,
                'hora_fim' => $horaFim,
                'acompanhante' => trim((string) ($bruta['acompanhante'] ?? '')) ?: null,
                'resumo_atividades' => trim((string) ($bruta['resumo_atividades'] ?? '')) ?: null,
                'status' => $status,
                'observacoes' => trim((string) ($bruta['observacoes'] ?? '')) ?: null,
            ],
            'erros' => $erros,
            'matched_lancamento_id' => $matchedLancamentoId,
        ];
    }

    private function normalizarData(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $texto = (string) $valor;

        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $texto, $m)) {
            return $m[1];
        }

        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d'] as $formato) {
            try {
                return Carbon::createFromFormat($formato, trim($texto))->format('Y-m-d');
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    private function normalizarHora(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $texto = (string) $valor;

        if (preg_match('/(\d{2}:\d{2})(:\d{2})?$/', $texto, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * @param array<int, array{dados: array<string, mixed>, erros: array<string, string>, matched_lancamento_id: int|null}> $linhasProcessadas
     * @return array{lote: array<string, mixed>, linhas: array<int, array<string, mixed>>}
     */
    private function persistirLote(UploadedFile $arquivo, array $linhasProcessadas, int $tenantId): array
    {
        return DB::transaction(function () use ($arquivo, $linhasProcessadas, $tenantId) {
            $arquivoPath = Storage::disk('local')->putFileAs('lancamento-import', $arquivo, $arquivo->hashName());
            $validas = count(array_filter($linhasProcessadas, fn ($item) => $item['erros'] === []));

            $lote = LancamentoImportLote::query()->create([
                'tenant_id' => $tenantId,
                'arquivo_nome_original' => $arquivo->getClientOriginalName(),
                'arquivo_path' => $arquivoPath,
                'status' => 'previsualizado',
                'total_linhas' => count($linhasProcessadas),
                'total_validas' => $validas,
                'total_invalidas' => count($linhasProcessadas) - $validas,
            ]);

            foreach ($linhasProcessadas as $item) {
                LancamentoImportLinha::query()->create([
                    'tenant_id' => $tenantId,
                    'lancamento_import_lote_id' => $lote->id,
                    'linha' => $item['dados']['linha'],
                    'status' => $item['erros'] === [] ? 'valida' : 'erro',
                    'matched_lancamento_id' => $item['matched_lancamento_id'],
                    'dados_json' => $item['dados'],
                    'erros_json' => $item['erros'] ?: null,
                ]);
            }

            return $this->serializarLote($lote->fresh('linhas'));
        });
    }

    private function serializarLote(LancamentoImportLote $lote): array
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
            'linhas' => $lote->linhas->map(fn (LancamentoImportLinha $linha) => [
                'id' => $linha->id,
                'linha' => $linha->linha,
                'status' => $linha->status,
                'matched_lancamento_id' => $linha->matched_lancamento_id,
                'dados' => $linha->dados_json,
                'erros' => $linha->erros_json ?? [],
            ])->all(),
        ];
    }

    private function gravarLancamento(array $dados, int $tenantId, ?Lancamento $existente, AntecipacaoService $antecipacaoService): Lancamento
    {
        $atributos = [
            'tenant_id' => $tenantId,
            'antecipacao_id' => $dados['antecipacao_id'],
            'profissional_id' => $dados['profissional_id'],
            'data_sessao' => $dados['data_sessao'],
            'hora_inicio' => $dados['hora_inicio'],
            'hora_fim' => $dados['hora_fim'],
            'acompanhante' => $dados['acompanhante'],
            'resumo_atividades' => $dados['resumo_atividades'],
            'status' => $dados['status'],
            'observacoes' => $dados['observacoes'],
        ];

        if ($existente) {
            $existente->fill($atributos);
            $existente->save();

            return $existente;
        }

        $lancamento = Lancamento::query()->create($atributos);

        $antecipacao = Antecipacao::query()->lockForUpdate()->findOrFail($dados['antecipacao_id']);
        $antecipacaoService->consumirCotaForcado($antecipacao);

        return $lancamento;
    }
}
