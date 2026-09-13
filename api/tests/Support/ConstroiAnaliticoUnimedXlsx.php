<?php

namespace Tests\Support;

use Illuminate\Http\UploadedFile;
use RuntimeException;
use ZipArchive;

/**
 * Monta um .xlsx no formato do analítico da Unimed (aba Analítico + aba Glosa),
 * do jeito que o AnaliticoUnimedImportService espera ler.
 *
 * Existe para que os testes não dependam de nenhum arquivo solto no disco: o
 * AnaliticosApiTest lia um `item3.3.xlsx` da raiz do projeto que nunca esteve
 * versionado — quando o arquivo sumiu da máquina, três testes quebraram, e num
 * clone novo eles nunca passariam.
 */
trait ConstroiAnaliticoUnimedXlsx
{
    /**
     * @param  array<int, array{0: int, 1: array<string, string>}>  $linhasAnalitico
     * @param  array<int, array{0: int, 1: array<string, string>}>  $linhasGlosa
     */
    private function montarArquivoAnaliticoUnimed(
        array $linhasAnalitico,
        array $linhasGlosa,
        string $nomeArquivo = 'analitico-unimed.xlsx',
    ): UploadedFile {
        $path = tempnam(sys_get_temp_dir(), 'analitico');

        if ($path === false) {
            throw new RuntimeException('Não foi possível criar arquivo temporário para o analítico.');
        }

        $arquivo = $path.'.xlsx';
        @unlink($path);

        $zip = new ZipArchive;
        if ($zip->open($arquivo, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Não foi possível montar o xlsx de teste.');
        }

        $zip->addFromString('[Content_Types].xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
</Types>
XML);
        $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML);
        $zip->addFromString('xl/workbook.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Analítico" sheetId="1" r:id="rId1"/>
    <sheet name="Glosa" sheetId="2" r:id="rId2"/>
  </sheets>
</workbook>
XML);
        $zip->addFromString('xl/_rels/workbook.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>
</Relationships>
XML);
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->montarWorksheetXml($linhasAnalitico));
        $zip->addFromString('xl/worksheets/sheet2.xml', $this->montarWorksheetXml($linhasGlosa));

        $zip->close();

        return UploadedFile::fake()->createWithContent($nomeArquivo, file_get_contents($arquivo));
    }

    /**
     * Cabeçalho das duas primeiras linhas da aba Analítico: o importador lê o
     * código, o nome e o CNPJ a partir deste texto corrido.
     *
     * @return array<int, array{0: int, 1: array<string, string>}>
     */
    private function cabecalhoAnaliticoUnimed(): array
    {
        return [
            [1, ['A' => 'Unimed Executante: 220 - COOPERATIVA DE TRABALHO MEDICO DO PLANALTO SERRANO / CNPJ: 85246916000189']],
            [2, ['A' => 'Prestador Executante: 22099808 - CENTRO NEUROKIDS LTDA']],
            [3, [
                'A' => 'Número Guia da Operadora',
                'B' => 'Número Guia do Prestador',
                'C' => 'Código',
                'D' => 'Usuário',
                'E' => 'Data Autorização',
                'F' => 'Data Realização',
                'G' => 'Proced.',
                'H' => 'Tabela',
                'I' => 'Descrição do Proced.',
                'J' => 'Qtd.',
                'K' => 'Filme',
                'L' => 'Custo',
                'M' => 'Hono',
                'N' => 'Valor',
                'O' => 'Local Realização',
            ]],
        ];
    }

    /**
     * Cabeçalho da aba Glosa. Só a linha 1 — o importador pula exatamente ela.
     *
     * @return array<int, array{0: int, 1: array<string, string>}>
     */
    private function cabecalhoGlosaUnimed(): array
    {
        return [
            [1, [
                'A' => 'Número Guia da Operadora',
                'B' => 'Número Guia do Prestador',
                'C' => 'Código',
                'D' => 'Usuário',
                'E' => 'Data Autorização',
                'F' => 'Data Realização',
                'G' => 'Proced.',
                'H' => 'Tabela',
                'I' => 'Descrição do Proced.',
                'J' => 'Qtd.',
                'K' => 'Tipo',
                'L' => 'Motivo',
                'M' => 'Valor',
                'N' => 'Local Realização',
            ]],
        ];
    }

    /**
     * @param  array<int, array{0: int, 1: array<string, string>}>  $rows
     */
    private function montarWorksheetXml(array $rows): string
    {
        $rowsXml = '';

        foreach ($rows as [$rowNumber, $cells]) {
            $cellsXml = '';
            foreach ($cells as $column => $value) {
                $cellsXml .= sprintf(
                    '<c r="%s%d" t="inlineStr"><is><t>%s</t></is></c>',
                    $column,
                    $rowNumber,
                    e($value)
                );
            }

            $rowsXml .= sprintf('<row r="%d">%s</row>', $rowNumber, $cellsXml);
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <sheetData>
    {$rowsXml}
  </sheetData>
</worksheet>
XML;
    }
}
