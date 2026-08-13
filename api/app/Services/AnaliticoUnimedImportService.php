<?php

namespace App\Services;

use App\Models\AnaliticoUnimedLinha;
use App\Models\AnaliticoUnimedLote;
use App\Support\Auditoria;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class AnaliticoUnimedImportService
{
    /**
     * @return array{
     *   arquivo: string,
     *   planilhas: array<int, array{nome: string, linhas: int}>,
     *   analitico: array{
     *     cabecalho: array{unimed_executante: array<string, string|null>, prestador_executante: array<string, string|null>},
     *     linhas: array<int, array<string, string|null>>,
     *     totais: array<string, string|null>
     *   },
     *   glosas: array{
     *     linhas: array<int, array<string, string|null>>,
     *     total: array<string, string|null>
     *   },
     *   conciliacao: array{
     *     linhas: array<int, array<string, string|null|bool|int>>,
     *     resumo_por_guia: array<int, array<string, string|null|int>>,
     *     totais: array{pago: string, glosado: string, saldo: string}
     *   },
     *   lote: array{
     *     id: int,
     *     arquivo_nome_original: string,
     *     arquivo_path: string|null,
     *     status: string,
     *     importado_em: string|null,
     *     total_linhas_analitico: int,
     *     total_linhas_glosa: int,
     *     total_linhas_conciliacao: int,
     *     total_pago: string,
     *     total_glosado: string,
     *     saldo_total: string
     *   }
     * }
     */
    public function previsualizar(UploadedFile $arquivo): array
    {
        $zip = new ZipArchive();

        if ($zip->open($arquivo->getPathname()) !== true) {
            throw new RuntimeException('Não foi possível abrir o arquivo Excel do analítico.');
        }

        try {
            $sharedStrings = $this->lerSharedStrings($zip);
            $sheets = $this->lerPlanilhas($zip);

            if (count($sheets) < 2) {
                throw new RuntimeException('A planilha precisa conter as abas Analítico e Glosa.');
            }

            $analiticoSheet = $sheets[0];
            $glosaSheet = $sheets[1];

            $analiticoRows = $this->lerWorksheet($zip, $analiticoSheet['path'], $sharedStrings);
            $glosaRows = $this->lerWorksheet($zip, $glosaSheet['path'], $sharedStrings);

            $analitico = $this->normalizarAnalitico($analiticoRows);
            $glosas = $this->normalizarGlosas($glosaRows);
            $conciliacao = $this->normalizarConciliacao($analitico['linhas'], $glosas['linhas']);
            $lote = $this->persistirLote(
                $arquivo,
                $analitico,
                $glosas,
                $conciliacao,
                [
                    ['nome' => $analiticoSheet['name'], 'linhas' => count($analitico['linhas'])],
                    ['nome' => $glosaSheet['name'], 'linhas' => count($glosas['linhas'])],
                ]
            );

            return [
                'arquivo' => $arquivo->getClientOriginalName(),
                'planilhas' => [
                    ['nome' => $analiticoSheet['name'], 'linhas' => count($analitico['linhas'])],
                    ['nome' => $glosaSheet['name'], 'linhas' => count($glosas['linhas'])],
                ],
                'analitico' => $analitico,
                'glosas' => $glosas,
                'conciliacao' => $conciliacao,
                'lote' => $lote,
            ];
        } finally {
            $zip->close();
        }
    }

    /**
     * @return array<int, array{name: string, path: string}>
     */
    private function lerPlanilhas(ZipArchive $zip): array
    {
        $workbookXml = $this->carregarXml($zip, 'xl/workbook.xml');
        $relsXml = $this->carregarXml($zip, 'xl/_rels/workbook.xml.rels');

        $workbookXml->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $workbookXml->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $relsXml->registerXPathNamespace('rel', 'http://schemas.openxmlformats.org/package/2006/relationships');

        $targets = [];
        foreach ($relsXml->xpath('//rel:Relationship') ?: [] as $relationship) {
            $targets[(string) $relationship['Id']] = (string) $relationship['Target'];
        }

        $planilhas = [];
        foreach ($workbookXml->xpath('//main:sheets/main:sheet') ?: [] as $sheet) {
            $sheetAttributes = $sheet->attributes('r', true);
            $relationshipId = (string) ($sheetAttributes['id'] ?? '');
            $target = $targets[$relationshipId] ?? null;

            if (! $target) {
                continue;
            }

            $planilhas[] = [
                'name' => (string) $sheet['name'],
                'path' => $this->normalizarTarget($target),
            ];
        }

        return $planilhas;
    }

    /**
     * @return array<int, string>
     */
    private function lerSharedStrings(ZipArchive $zip): array
    {
        if ($zip->locateName('xl/sharedStrings.xml') === false) {
            return [];
        }

        $xml = $this->carregarXml($zip, 'xl/sharedStrings.xml');
        $xml->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $sharedStrings = [];

        foreach ($xml->xpath('//main:si') ?: [] as $sharedString) {
            $sharedStrings[] = $this->nodeText($sharedString);
        }

        return $sharedStrings;
    }

    /**
     * @return array<int, array{r: int, cells: array<string, string|null>}>
     */
    private function lerWorksheet(ZipArchive $zip, string $path, array $sharedStrings): array
    {
        $xml = $this->carregarXml($zip, $path);
        $xml->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $rows = [];
        foreach ($xml->xpath('//main:sheetData/main:row') ?: [] as $row) {
            $row->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $cells = [];

            foreach ($row->xpath('./main:c') ?: [] as $cell) {
                $reference = (string) $cell['r'];
                $column = preg_replace('/\d+$/', '', $reference) ?: $reference;
                $cells[$column] = $this->cellValue($cell, $sharedStrings);
            }

            $rows[] = [
                'r' => (int) $row['r'],
                'cells' => $cells,
            ];
        }

        return $rows;
    }

    /**
     * @param array<int, array{r: int, cells: array<string, string|null>}> $rows
     * @return array{
     *   cabecalho: array{unimed_executante: array<string, string|null>, prestador_executante: array<string, string|null>},
     *   linhas: array<int, array<string, string|null>>,
     *   totais: array<string, string|null>
     * }
     */
    private function normalizarAnalitico(array $rows): array
    {
        $cabecalhoUnimed = $rows[0]['cells']['A'] ?? null;
        $cabecalhoPrestador = $rows[1]['cells']['A'] ?? null;
        $linhas = [];
        $totais = [
            'prestador' => null,
            'lote' => null,
        ];

        foreach ($rows as $row) {
            if ($row['r'] <= 3) {
                continue;
            }

            $numeroGuiaOperadora = $row['cells']['A'] ?? null;
            $rotuloOperadora = is_string($numeroGuiaOperadora) ? trim($numeroGuiaOperadora) : null;
            $numeroGuiaPrestador = $row['cells']['B'] ?? null;

            if ($rotuloOperadora !== null && str_starts_with($rotuloOperadora, 'TOTAL')) {
                if ($rotuloOperadora === 'TOTAL DO PRESTADOR') {
                    $totais['prestador'] = $row['cells']['N'] ?? null;
                } elseif ($rotuloOperadora === 'TOTAL DO LOTE') {
                    $totais['lote'] = $row['cells']['N'] ?? null;
                }

                continue;
            }

            $normalizado = [
                'linha' => (string) $row['r'],
                'numero_guia_operadora' => $numeroGuiaOperadora,
                'numero_guia_prestador' => $numeroGuiaPrestador,
                'codigo' => $row['cells']['C'] ?? null,
                'usuario' => $row['cells']['D'] ?? null,
                'data_autorizacao' => $row['cells']['E'] ?? null,
                'data_realizacao' => $row['cells']['F'] ?? null,
                'procedimento' => $row['cells']['G'] ?? null,
                'tabela' => $row['cells']['H'] ?? null,
                'descricao_procedimento' => $row['cells']['I'] ?? null,
                'qtd' => $row['cells']['J'] ?? null,
                'filme' => $row['cells']['K'] ?? null,
                'custo' => $row['cells']['L'] ?? null,
                'hono' => $row['cells']['M'] ?? null,
                'valor' => $row['cells']['N'] ?? null,
                'local_realizacao' => $row['cells']['O'] ?? null,
            ];

            if ($this->linhaEmBranco($normalizado)) {
                continue;
            }

            $linhas[] = $normalizado;
        }

        return [
            'cabecalho' => [
                'unimed_executante' => $this->analisarCabecalhoExecutante($cabecalhoUnimed),
                'prestador_executante' => $this->analisarCabecalhoPrestador($cabecalhoPrestador),
            ],
            'linhas' => $linhas,
            'totais' => $totais,
        ];
    }

    /**
     * @param array<int, array{r: int, cells: array<string, string|null>}> $rows
     * @return array{
     *   linhas: array<int, array<string, string|null>>,
     *   total: array<string, string|null>
     * }
     */
    private function normalizarGlosas(array $rows): array
    {
        $linhas = [];
        $total = [
            'valor' => null,
        ];

        foreach ($rows as $row) {
            if ($row['r'] === 1) {
                continue;
            }

            $numeroGuiaOperadora = $row['cells']['A'] ?? null;
            $rotuloOperadora = is_string($numeroGuiaOperadora) ? trim($numeroGuiaOperadora) : null;

            if ($rotuloOperadora === 'TOTAL:') {
                $total['valor'] = $row['cells']['M'] ?? null;
                continue;
            }

            $normalizado = [
                'linha' => (string) $row['r'],
                'numero_guia_operadora' => $numeroGuiaOperadora,
                'numero_guia_prestador' => $row['cells']['B'] ?? null,
                'codigo' => $row['cells']['C'] ?? null,
                'usuario' => $row['cells']['D'] ?? null,
                'data_autorizacao' => $row['cells']['E'] ?? null,
                'data_realizacao' => $row['cells']['F'] ?? null,
                'procedimento' => $row['cells']['G'] ?? null,
                'tabela' => $row['cells']['H'] ?? null,
                'descricao_procedimento' => $row['cells']['I'] ?? null,
                'qtd' => $row['cells']['J'] ?? null,
                'tipo' => $row['cells']['K'] ?? null,
                'motivo' => $row['cells']['L'] ?? null,
                'valor' => $row['cells']['M'] ?? null,
                'local_realizacao' => $row['cells']['N'] ?? null,
            ];

            if ($this->linhaEmBranco($normalizado)) {
                continue;
            }

            $linhas[] = $normalizado;
        }

        return [
            'linhas' => $linhas,
            'total' => $total,
        ];
    }

    /**
     * @param array<int, array<string, string|null>> $analiticoRows
     * @param array<int, array<string, string|null>> $glosaRows
     * @return array{
     *   linhas: array<int, array<string, string|null|bool|int>>,
     *   resumo_por_guia: array<int, array<string, string|null|int>>,
     *   totais: array{pago: string, glosado: string, saldo: string}
     * }
     */
    private function normalizarConciliacao(array $analiticoRows, array $glosaRows): array
    {
        $linhas = [];
        $resumoPorGuia = [];
        $totalPago = 0.0;
        $totalGlosado = 0.0;

        foreach ($analiticoRows as $row) {
            $linha = $this->mapearLinhaConciliacao($row, 'analitico', 'pago');
            if (! $linha) {
                continue;
            }

            $totalPago += $this->moedaParaFloat($linha['valor']);
            $this->acumularResumoPorGuia($resumoPorGuia, $linha, 'analitico');
            $linhas[] = $linha;
        }

        foreach ($glosaRows as $row) {
            $linha = $this->mapearLinhaConciliacao($row, 'glosa', 'glosado');
            if (! $linha) {
                continue;
            }

            $totalGlosado += $this->moedaParaFloat($linha['valor']);
            $this->acumularResumoPorGuia($resumoPorGuia, $linha, 'glosa');
            $linhas[] = $linha;
        }

        return [
            'linhas' => $linhas,
            'resumo_por_guia' => array_values($resumoPorGuia),
            'totais' => [
                'pago' => $this->floatParaMoeda($totalPago),
                'glosado' => $this->floatParaMoeda($totalGlosado),
                'saldo' => $this->floatParaMoeda($totalPago - $totalGlosado),
            ],
        ];
    }

    /**
     * @param array<string, string|null> $row
     * @return array<string, string|null|bool|int>|null
     */
    private function mapearLinhaConciliacao(array $row, string $origem, string $natureza): ?array
    {
        $numeroGuia = $row['numero_guia_operadora'] ?? null;
        $valor = $row['valor'] ?? null;

        if (! $numeroGuia || ! $valor) {
            return null;
        }

        $qtd = $this->converteInteiro($row['qtd'] ?? null);

        return [
            'linha' => $row['linha'] ?? null,
            'origem' => $origem,
            'natureza' => $natureza,
            'processavel' => true,
            'numero_guia_operadora' => $numeroGuia,
            'numero_guia_prestador' => $row['numero_guia_prestador'] ?? null,
            'codigo' => $row['codigo'] ?? null,
            'usuario' => $row['usuario'] ?? null,
            'data_autorizacao' => $row['data_autorizacao'] ?? null,
            'data_realizacao' => $row['data_realizacao'] ?? null,
            'procedimento' => $row['procedimento'] ?? null,
            'descricao_procedimento' => $row['descricao_procedimento'] ?? null,
            'qtd' => $row['qtd'] ?? null,
            'qtd_normalizada' => $qtd,
            'tipo' => $row['tipo'] ?? null,
            'motivo' => $row['motivo'] ?? null,
            'valor' => $valor,
            'valor_normalizado' => $this->floatParaMoeda($this->moedaParaFloat($valor)),
            'local_realizacao' => $row['local_realizacao'] ?? null,
            'chave_conciliacao' => implode('|', array_filter([
                $numeroGuia,
                $row['codigo'] ?? null,
                $row['data_realizacao'] ?? null,
                $origem,
            ], static fn ($value) => $value !== null && $value !== '')),
        ];
    }

    /**
     * @param array<string, array<string, string|null|int>> $resumoPorGuia
     * @param array<string, string|null|bool|int> $linha
     */
    private function acumularResumoPorGuia(array &$resumoPorGuia, array $linha, string $origem): void
    {
        $numeroGuia = (string) ($linha['numero_guia_operadora'] ?? '');

        if ($numeroGuia === '') {
            return;
        }

        if (! isset($resumoPorGuia[$numeroGuia])) {
            $resumoPorGuia[$numeroGuia] = [
                'numero_guia_operadora' => $numeroGuia,
                'linhas_analitico' => 0,
                'linhas_glosa' => 0,
                'qtd_paga' => 0,
                'qtd_glosada' => 0,
                'valor_pago' => '0,00',
                'valor_glosado' => '0,00',
            ];
        }

        $qtd = $this->converteInteiro($linha['qtd'] ?? null);
        $campoValor = $origem === 'glosa' ? 'valor_glosado' : 'valor_pago';
        $valorAtual = $this->moedaParaFloat($resumoPorGuia[$numeroGuia][$campoValor]);
        $valorLinha = $this->moedaParaFloat($linha['valor'] ?? null);

        if ($origem === 'analitico') {
            $resumoPorGuia[$numeroGuia]['linhas_analitico']++;
            $resumoPorGuia[$numeroGuia]['qtd_paga'] += $qtd;
            $resumoPorGuia[$numeroGuia]['valor_pago'] = $this->floatParaMoeda($valorAtual + $valorLinha);

            return;
        }

        $resumoPorGuia[$numeroGuia]['linhas_glosa']++;
        $resumoPorGuia[$numeroGuia]['qtd_glosada'] += $qtd;
        $resumoPorGuia[$numeroGuia]['valor_glosado'] = $this->floatParaMoeda($valorAtual + $valorLinha);
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
     * @return array<string, string|null>
     */
    private function analisarCabecalhoExecutante(?string $texto): array
    {
        return $this->analisarCabecalho($texto, 'Unimed Executante');
    }

    /**
     * @return array<string, string|null>
     */
    private function analisarCabecalhoPrestador(?string $texto): array
    {
        return $this->analisarCabecalho($texto, 'Prestador Executante');
    }

    /**
     * @return array<string, string|null>
     */
    private function analisarCabecalho(?string $texto, string $prefixo): array
    {
        if (! $texto) {
            return [
                'raw' => null,
                'codigo' => null,
                'nome' => null,
                'cnpj' => null,
            ];
        }

        $dados = [
            'raw' => $texto,
            'codigo' => null,
            'nome' => null,
            'cnpj' => null,
        ];

        if (preg_match('/^'.preg_quote($prefixo, '/').':\s*([0-9]+)\s*-\s*(.+?)(?:\s*\/\s*CNPJ:\s*([0-9.\/-]+))?$/u', $texto, $matches)) {
            $dados['codigo'] = $matches[1] ?? null;
            $dados['nome'] = trim($matches[2] ?? '');
            $dados['cnpj'] = $matches[3] ?? null;
        }

        return $dados;
    }

    /**
     * @param array<int, string> $sharedStrings
     */
    private function cellValue(SimpleXMLElement $cell, array $sharedStrings): ?string
    {
        $cell->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $type = (string) $cell['t'];
        $valueNodes = $cell->xpath('./main:v') ?: [];

        if ($type === 's') {
            $index = isset($valueNodes[0]) ? (int) (string) $valueNodes[0] : -1;

            return $sharedStrings[$index] ?? null;
        }

        if ($type === 'inlineStr') {
            $inlineNodes = $cell->xpath('./main:is') ?: [];

            return $this->nodeText($inlineNodes[0] ?? null);
        }

        if (isset($valueNodes[0])) {
            return (string) $valueNodes[0];
        }

        return null;
    }

    private function normalizarTarget(string $target): string
    {
        return str_starts_with($target, 'xl/')
            ? $target
            : 'xl/'.ltrim($target, '/');
    }

    private function carregarXml(ZipArchive $zip, string $path): SimpleXMLElement
    {
        $conteudo = $this->readEntry($zip, $path);
        $xml = simplexml_load_string($conteudo);

        if (! $xml) {
            throw new RuntimeException("Não foi possível ler o XML do arquivo: {$path}");
        }

        return $xml;
    }

    private function readEntry(ZipArchive $zip, string $path): string
    {
        $stream = $zip->getStream($path);

        if (! $stream) {
            throw new RuntimeException("Arquivo Excel sem a entrada esperada: {$path}");
        }

        try {
            $content = stream_get_contents($stream);
        } finally {
            fclose($stream);
        }

        if ($content === false) {
            throw new RuntimeException("Não foi possível ler a entrada {$path} do Excel.");
        }

        return $content;
    }

    private function nodeText(?SimpleXMLElement $node): string
    {
        if (! $node) {
            return '';
        }

        $texto = '';

        foreach ($node->children() as $child) {
            $texto .= $this->nodeText($child);
        }

        if ($texto === '') {
            $texto = (string) $node;
        }

        return $texto;
    }

    private function moedaParaFloat(?string $valor): float
    {
        if ($valor === null) {
            return 0.0;
        }

        $normalizado = trim($valor);

        if ($normalizado === '') {
            return 0.0;
        }

        $normalizado = str_replace(['.', ' '], ['', ''], $normalizado);
        $normalizado = str_replace(',', '.', $normalizado);

        return is_numeric($normalizado) ? (float) $normalizado : 0.0;
    }

    private function floatParaMoeda(float $valor): string
    {
        return number_format($valor, 2, ',', '.');
    }

    private function converteInteiro(?string $valor): int
    {
        if ($valor === null) {
            return 0;
        }

        $normalizado = trim($valor);

        if ($normalizado === '') {
            return 0;
        }

        $normalizado = preg_replace('/[^0-9-]/', '', $normalizado) ?? '';

        return $normalizado === '' ? 0 : (int) $normalizado;
    }

    /**
     * @param array{cabecalho: array{unimed_executante: array<string, string|null>, prestador_executante: array<string, string|null>}, linhas: array<int, array<string, string|null>>, totais: array<string, string|null>} $analitico
     * @param array{linhas: array<int, array<string, string|null>>, total: array<string, string|null>} $glosas
     * @param array{linhas: array<int, array<string, string|null|bool|int>>, resumo_por_guia: array<int, array<string, string|null|int>>, totais: array{pago: string, glosado: string, saldo: string}} $conciliacao
     * @param array<int, array{nome: string, linhas: int}> $planilhas
     * @return array{id: int, arquivo_nome_original: string, arquivo_path: string|null, status: string, importado_em: string|null, total_linhas_analitico: int, total_linhas_glosa: int, total_linhas_conciliacao: int, total_pago: string, total_glosado: string, saldo_total: string}
     */
    private function persistirLote(
        UploadedFile $arquivo,
        array $analitico,
        array $glosas,
        array $conciliacao,
        array $planilhas
    ): array {
        return DB::transaction(function () use ($arquivo, $analitico, $glosas, $conciliacao, $planilhas) {
            $arquivoPath = Storage::disk('local')->putFileAs(
                'analitico-unimed',
                $arquivo,
                $arquivo->hashName()
            );

            $lote = AnaliticoUnimedLote::query()->create([
                'tenant_id' => request()->user()?->tenant_id,
                'arquivo_nome_original' => $arquivo->getClientOriginalName(),
                'arquivo_path' => $arquivoPath,
                'status' => 'importado',
                'importado_em' => now(),
                'total_linhas_analitico' => count($analitico['linhas']),
                'total_linhas_glosa' => count($glosas['linhas']),
                'total_linhas_conciliacao' => count($conciliacao['linhas']),
                'total_pago' => number_format($this->moedaParaFloat($conciliacao['totais']['pago']), 2, '.', ''),
                'total_glosado' => number_format($this->moedaParaFloat($conciliacao['totais']['glosado']), 2, '.', ''),
                'saldo_total' => number_format($this->moedaParaFloat($conciliacao['totais']['saldo']), 2, '.', ''),
                'cabecalho_json' => $analitico['cabecalho'],
                'planilhas_json' => $planilhas,
                'totais_json' => [
                    'analitico' => $analitico['totais'],
                    'glosas' => $glosas['total'],
                    'conciliacao' => $conciliacao['totais'],
                ],
            ]);

            foreach ($analitico['linhas'] as $linha) {
                AnaliticoUnimedLinha::query()->create([
                    'tenant_id' => request()->user()?->tenant_id,
                    'analitico_unimed_lote_id' => $lote->id,
                    'linha' => isset($linha['linha']) ? (int) $linha['linha'] : null,
                    'origem' => 'analitico',
                    'natureza' => 'pago',
                    'processavel' => true,
                    'numero_guia_operadora' => $linha['numero_guia_operadora'] ?? null,
                    'numero_guia_prestador' => $linha['numero_guia_prestador'] ?? null,
                    'codigo' => $linha['codigo'] ?? null,
                    'usuario' => $linha['usuario'] ?? null,
                    'data_autorizacao' => $linha['data_autorizacao'] ?? null,
                    'data_realizacao' => $linha['data_realizacao'] ?? null,
                    'procedimento' => $linha['procedimento'] ?? null,
                    'descricao_procedimento' => $linha['descricao_procedimento'] ?? null,
                    'qtd' => $linha['qtd'] ?? null,
                    'qtd_normalizada' => $this->converteInteiro($linha['qtd'] ?? null),
                    'tipo' => null,
                    'motivo' => null,
                    'valor' => $linha['valor'] ?? null,
                    'valor_normalizado' => $this->moedaParaFloat($linha['valor'] ?? null),
                    'local_realizacao' => $linha['local_realizacao'] ?? null,
                    'chave_conciliacao' => $this->montarChaveConciliacao($linha, 'analitico'),
                    'dados_json' => $linha,
                ]);
            }

            foreach ($glosas['linhas'] as $linha) {
                AnaliticoUnimedLinha::query()->create([
                    'tenant_id' => request()->user()?->tenant_id,
                    'analitico_unimed_lote_id' => $lote->id,
                    'linha' => isset($linha['linha']) ? (int) $linha['linha'] : null,
                    'origem' => 'glosa',
                    'natureza' => 'glosado',
                    'processavel' => true,
                    'numero_guia_operadora' => $linha['numero_guia_operadora'] ?? null,
                    'numero_guia_prestador' => $linha['numero_guia_prestador'] ?? null,
                    'codigo' => $linha['codigo'] ?? null,
                    'usuario' => $linha['usuario'] ?? null,
                    'data_autorizacao' => $linha['data_autorizacao'] ?? null,
                    'data_realizacao' => $linha['data_realizacao'] ?? null,
                    'procedimento' => $linha['procedimento'] ?? null,
                    'descricao_procedimento' => $linha['descricao_procedimento'] ?? null,
                    'qtd' => $linha['qtd'] ?? null,
                    'qtd_normalizada' => $this->converteInteiro($linha['qtd'] ?? null),
                    'tipo' => $linha['tipo'] ?? null,
                    'motivo' => $linha['motivo'] ?? null,
                    'valor' => $linha['valor'] ?? null,
                    'valor_normalizado' => $this->moedaParaFloat($linha['valor'] ?? null),
                    'local_realizacao' => $linha['local_realizacao'] ?? null,
                    'chave_conciliacao' => $this->montarChaveConciliacao($linha, 'glosa'),
                    'dados_json' => $linha,
                ]);
            }

            // Um evento pelo lote, e nao um por linha: uma importacao sozinha
            // geraria milhares de registros e dominaria a trilha inteira. A
            // alteracao manual de uma linha depois registra o evento dela.
            Auditoria::registrar(
                acao: 'analitico.importado',
                entidade: 'analitico_unimed_lotes',
                entidadeId: (int) $lote->id,
                payload: [
                    'arquivo' => $lote->arquivo_nome_original,
                    'linhas_analitico' => $lote->total_linhas_analitico,
                    'linhas_glosa' => $lote->total_linhas_glosa,
                    'linhas_conciliacao' => $lote->total_linhas_conciliacao,
                    'total_pago' => $lote->total_pago,
                    'total_glosado' => $lote->total_glosado,
                    'saldo_total' => $lote->saldo_total,
                ],
                tenantId: $lote->tenant_id,
            );

            return [
                'id' => $lote->id,
                'arquivo_nome_original' => $lote->arquivo_nome_original,
                'arquivo_path' => $lote->arquivo_path,
                'status' => $lote->status,
                'importado_em' => $lote->importado_em?->toISOString(),
                'total_linhas_analitico' => $lote->total_linhas_analitico,
                'total_linhas_glosa' => $lote->total_linhas_glosa,
                'total_linhas_conciliacao' => $lote->total_linhas_conciliacao,
                'total_pago' => $this->floatParaMoeda((float) $lote->total_pago),
                'total_glosado' => $this->floatParaMoeda((float) $lote->total_glosado),
                'saldo_total' => $this->floatParaMoeda((float) $lote->saldo_total),
            ];
        });
    }

    /**
     * @param array<string, string|null> $linha
     */
    private function montarChaveConciliacao(array $linha, string $origem): string
    {
        return implode('|', array_filter([
            $linha['numero_guia_operadora'] ?? null,
            $linha['codigo'] ?? null,
            $linha['data_realizacao'] ?? null,
            $origem,
        ], static fn ($value) => $value !== null && $value !== ''));
    }
}
