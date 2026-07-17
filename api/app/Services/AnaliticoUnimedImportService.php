<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
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

            return [
                'arquivo' => $arquivo->getClientOriginalName(),
                'planilhas' => [
                    ['nome' => $analiticoSheet['name'], 'linhas' => count($analitico['linhas'])],
                    ['nome' => $glosaSheet['name'], 'linhas' => count($glosas['linhas'])],
                ],
                'analitico' => $analitico,
                'glosas' => $glosas,
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
}
