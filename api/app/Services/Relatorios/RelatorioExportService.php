<?php

namespace App\Services\Relatorios;

use App\Support\Auditoria;
use Carbon\CarbonImmutable;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Uma tabela de relatório virando arquivo.
 *
 * Recebe a tabela já montada pelo serviço da aba — não refaz consulta nem
 * reinterpreta número. É o que garante que o arquivo exportado e a tela mostrem
 * exatamente a mesma coisa: duas montagens do mesmo relatório divergiriam no
 * primeiro ajuste de cálculo, e a planilha continuaria parecendo certa.
 *
 * ── CSV e XLSX não formatam igual, de propósito ─────────────────────────────
 *
 * No CSV todo valor vira TEXTO já formatado em pt-BR (1.234,56 / 12,5 /
 * 01/09/2026), porque CSV não tem tipo: quem abre é o Excel, que adivinha — e
 * adivinha errado com ponto decimal em máquina brasileira.
 *
 * No XLSX o número vai como NÚMERO, e dinheiro em reais, não em centavos. É o
 * motivo de existir a segunda opção: planilha serve para somar, ordenar e
 * dinamizar, e coluna de texto não faz nada disso. A formatação de exibição
 * fica com o Excel, que já conhece o locale de quem abriu.
 */
class RelatorioExportService
{
    public const FORMATO_CSV = 'csv';

    public const FORMATO_XLSX = 'xlsx';

    public const FORMATOS = [self::FORMATO_CSV, self::FORMATO_XLSX];

    /** Ação registrada na trilha a cada exportação. */
    public const ACAO_AUDITORIA = 'relatorio.exportado';

    /**
     * Ponto-e-vírgula porque o Excel em pt-BR usa a vírgula como separador
     * decimal: com vírgula separando campos, "1.234,56" quebraria em duas
     * colunas.
     */
    private const SEPARADOR_CSV = ';';

    /**
     * @param  array<string, mixed>  $tabela  uma entrada de `tabelas` do contrato
     */
    public function exportar(
        string $aba,
        string $formato,
        array $tabela,
        RelatorioFiltros $filtros,
    ): StreamedResponse {
        $this->registrarNaAuditoria($aba, $formato, $tabela, $filtros);

        $nome = $this->nomeDoArquivo($aba, $tabela['key'], $formato, $filtros);

        return $formato === self::FORMATO_XLSX
            ? $this->xlsx($tabela, $nome)
            : $this->csv($tabela, $nome);
    }

    /** `relatorio-operacao-por-convenio-2026-09-01-2026-09-30.csv` */
    public function nomeDoArquivo(string $aba, string $tabelaKey, string $formato, RelatorioFiltros $filtros): string
    {
        return sprintf(
            'relatorio-%s-%s-%s-%s.%s',
            $aba,
            str_replace('_', '-', $tabelaKey),
            $filtros->periodo->de->toDateString(),
            $filtros->periodo->ate->toDateString(),
            $formato,
        );
    }

    // ── CSV ─────────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $tabela */
    private function csv(array $tabela, string $arquivo): StreamedResponse
    {
        return response()->streamDownload(function () use ($tabela) {
            $saida = fopen('php://output', 'w');

            // BOM: sem ele o Excel em pt-BR lê o arquivo como ANSI e os acentos
            // viram caracteres soltos. Mesma solução da exportação da trilha.
            fwrite($saida, "\xEF\xBB\xBF");

            fputcsv($saida, array_column($tabela['colunas'], 'label'), self::SEPARADOR_CSV);

            foreach ($tabela['linhas'] as $linha) {
                fputcsv($saida, $this->celulasDeTexto($tabela['colunas'], $linha), self::SEPARADOR_CSV);
            }

            fclose($saida);
        }, $arquivo, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @param  array<int, array<string, string>>  $colunas
     * @param  array<string, mixed>  $linha
     * @return array<int, string>
     */
    private function celulasDeTexto(array $colunas, array $linha): array
    {
        return array_map(
            fn (array $coluna) => $this->protegerContraFormula(
                $this->formatarParaTexto($linha[$coluna['key']] ?? null, $coluna['formato']),
            ),
            $colunas,
        );
    }

    private function formatarParaTexto(mixed $valor, string $formato): string
    {
        // Ausência continua ausência no arquivo. Um zero aqui viraria, na
        // planilha de alguém, a afirmação de que o número foi medido e deu zero.
        if ($valor === null) {
            return '';
        }

        return match ($formato) {
            RelatorioService::FORMATO_MOEDA => number_format(((int) $valor) / 100, 2, ',', '.'),
            RelatorioService::FORMATO_PERCENTUAL => number_format((float) $valor, 1, ',', '.'),
            RelatorioService::FORMATO_HORAS => number_format((float) $valor, 1, ',', '.'),
            // Em SEGUNDOS, e não na unidade que a tela escolhe: numa planilha,
            // uma coluna que mistura "45 s" com "3,5 min" não ordena nem soma.
            // O cabeçalho da coluna carrega a unidade.
            RelatorioService::FORMATO_DURACAO => number_format((float) $valor, 1, ',', '.'),
            RelatorioService::FORMATO_INTEIRO => number_format((int) $valor, 0, ',', '.'),
            'data_hora' => $this->comoDataHora($valor),
            default => is_scalar($valor) ? (string) $valor : '',
        };
    }

    /**
     * Neutraliza fórmula plantada num campo de texto.
     *
     * Motivo de glosa, nome de convênio e nome de profissional são digitados por
     * gente — e chegam ao sistema pela planilha da operadora, que ninguém aqui
     * controla. Uma célula começando com `=`, `+`, `-` ou `@` é executada pelo
     * Excel ao abrir o arquivo; o apóstrofo à frente faz o Excel tratar tudo
     * como texto, sem alterar o que a pessoa lê.
     */
    private function protegerContraFormula(string $valor): string
    {
        return $valor !== '' && str_contains('=+-@', $valor[0]) ? "'".$valor : $valor;
    }

    // ── XLSX ────────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $tabela */
    private function xlsx(array $tabela, string $arquivo): StreamedResponse
    {
        return response()->streamDownload(function () use ($tabela) {
            $escritor = new XlsxWriter;

            // `php://output` em vez de `openToBrowser()`: o segundo manda os
            // próprios cabeçalhos HTTP, que colidiriam com os que o Laravel já
            // enviou. O arquivo é montado em disco temporário pelo openspout e
            // copiado para a saída no `close()` — a memória fica plana, que é o
            // ponto de usar esta biblioteca.
            $escritor->openToFile('php://output');

            $negrito = new Style(fontBold: true);
            $escritor->addRow(Row::fromValuesWithStyle(array_column($tabela['colunas'], 'label'), $negrito));

            foreach ($tabela['linhas'] as $linha) {
                $escritor->addRow(Row::fromValues($this->celulasTipadas($tabela['colunas'], $linha)));
            }

            $escritor->close();
        }, $arquivo, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  array<int, array<string, string>>  $colunas
     * @param  array<string, mixed>  $linha
     * @return array<int, mixed>
     */
    private function celulasTipadas(array $colunas, array $linha): array
    {
        return array_map(
            fn (array $coluna) => $this->valorTipado($linha[$coluna['key']] ?? null, $coluna['formato']),
            $colunas,
        );
    }

    private function valorTipado(mixed $valor, string $formato): mixed
    {
        // Célula vazia, e não zero: é a mesma distinção do CSV, e no XLSX ela
        // também muda a soma da coluna.
        if ($valor === null) {
            return '';
        }

        return match ($formato) {
            // Em reais: a planilha é para somar, e ninguém soma centavos.
            RelatorioService::FORMATO_MOEDA => ((int) $valor) / 100,
            RelatorioService::FORMATO_PERCENTUAL,
            RelatorioService::FORMATO_HORAS,
            RelatorioService::FORMATO_DURACAO => (float) $valor,
            RelatorioService::FORMATO_INTEIRO => (int) $valor,
            'data_hora' => $this->comoDataHora($valor),
            default => is_scalar($valor) ? (string) $valor : '',
        };
    }

    // ── Apoio ───────────────────────────────────────────────────────────────

    private function comoDataHora(mixed $valor): string
    {
        if (! is_string($valor) || $valor === '') {
            return '';
        }

        return CarbonImmutable::parse($valor, RelatorioPeriodo::FUSO)->format('d/m/Y H:i');
    }

    /**
     * Toda exportação fica na trilha.
     *
     * Relatório carrega o retrato financeiro e operacional da clínica, e um
     * arquivo sai do sistema para um lugar onde nenhum controle de permissão
     * alcança. Registrar quem levou, de qual aba e sob quais filtros é o que
     * permite responder a pergunta depois — sem isso, a permissão por aba só
     * protege a tela.
     *
     * @param  array<string, mixed>  $tabela
     */
    private function registrarNaAuditoria(string $aba, string $formato, array $tabela, RelatorioFiltros $filtros): void
    {
        Auditoria::registrar(
            acao: self::ACAO_AUDITORIA,
            entidade: 'relatorios',
            // Não há registro único por trás de um relatório; o mesmo zero que
            // `auditoria.expurgada` já usa para evento sem entidade.
            entidadeId: 0,
            payload: [
                'aba' => $aba,
                'tabela' => $tabela['key'],
                'formato' => $formato,
                'filtros' => [
                    'de' => $filtros->periodo->de->toDateString(),
                    'ate' => $filtros->periodo->ate->toDateString(),
                    'granularidade' => $filtros->periodo->granularidade,
                    'clinica' => $filtros->escopoDaClinica(),
                    ...$filtros->informados(),
                ],
                'linhas' => count($tabela['linhas']),
            ],
            tenantId: $filtros->tenantId,
        );
    }
}
