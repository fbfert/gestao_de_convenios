<?php

namespace App\Services;

use App\Models\Convenio;
use App\Models\Paciente;
use App\Models\PacienteImportLinha;
use App\Models\PacienteImportLote;
use App\Models\PacienteTelefone;
use App\Rules\Cpf;
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
 * Importação de Pacientes por planilha .xlsx — primeira entidade do padrão de
 * importação recorrente (upload → prévia editável → confirmar seletivo).
 *
 * Cada linha é comparada a pacientes já cadastrados pelo CPF (quando a linha
 * traz um) ou, na falta dele, por carteirinha + convênio — sem isso, reimportar
 * a mesma planilha depois duplicaria todo mundo em vez de atualizar.
 */
class PacienteImportService
{
    public function __construct(
        private readonly ImportacaoHeaderMappingAiService $headerMappingAi
    ) {
    }

    /** Cabeçalho aceito, na ordem em que aparece no modelo gerado. */
    private const COLUNAS = [
        'nome' => 'Nome',
        'cpf' => 'CPF',
        'carteirinha' => 'Carteirinha',
        'convenio' => 'Convênio',
        'validade_carteirinha' => 'Validade da carteirinha',
        'data_nascimento' => 'Data de nascimento',
        'telefone' => 'Telefone',
        'ativo' => 'Ativo',
    ];

    private const LINHA_EXEMPLO = [
        'nome' => 'Maria da Silva',
        'cpf' => '123.456.789-09',
        'carteirinha' => '0123456789',
        'convenio' => 'Unimed',
        'validade_carteirinha' => '31/12/2026',
        'data_nascimento' => '15/03/2018',
        'telefone' => '(48) 99999-9999',
        'ativo' => 'Sim',
    ];

    public function gerarTemplate(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Pacientes');

        $coluna = 'A';
        foreach (self::COLUNAS as $chave => $rotulo) {
            $sheet->setCellValue("{$coluna}1", $rotulo);
            $sheet->getColumnDimension($coluna)->setAutoSize(true);
            $sheet->setCellValue("{$coluna}2", self::LINHA_EXEMPLO[$chave]);
            $coluna++;
        }
        $sheet->getStyle('A1:H1')->getFont()->setBold(true);

        $writer = new Xlsx($spreadsheet);
        $nomeArquivo = 'modelo-importacao-pacientes.xlsx';

        return new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$nomeArquivo}\"",
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

        if (! isset($colunas['nome'], $colunas['carteirinha'], $colunas['convenio'])) {
            $colunas = $this->reforcarComIA($colunas, $this->lerCabecalhoBruto($sheet), $tenantId);
        }

        if (! isset($colunas['nome'], $colunas['carteirinha'], $colunas['convenio'])) {
            throw new RuntimeException('A planilha precisa ter pelo menos as colunas Nome, Carteirinha e Convênio.');
        }

        $convenios = Convenio::query()->where('tenant_id', $tenantId)->get(['id', 'nome', 'carteirinha_blocos']);
        $linhasProcessadas = [];
        $ultimaLinha = $sheet->getHighestDataRow();

        for ($numeroLinha = 2; $numeroLinha <= $ultimaLinha; $numeroLinha++) {
            $bruta = $this->lerLinha($sheet, $colunas, $numeroLinha);

            if ($this->linhaEmBranco($bruta)) {
                continue;
            }

            $linhasProcessadas[] = $this->normalizarLinha($bruta, $numeroLinha, $tenantId, $convenios);
        }

        return $this->persistirLote($arquivo, $linhasProcessadas, $tenantId);
    }

    /**
     * @param array<int, int> $linhaIdsSelecionadas
     * @param array<int, array<string, mixed>> $edicoes chave = paciente_import_linha.id
     * @return array{lote: array<string, mixed>, linhas: array<int, array<string, mixed>>}
     */
    public function confirmar(
        PacienteImportLote $lote,
        array $linhaIdsSelecionadas,
        array $edicoes,
        int $tenantId
    ): array {
        if ($lote->status !== 'previsualizado') {
            throw ValidationException::withMessages([
                'lote' => ['Este lote já foi confirmado (ou cancelado) e não pode ser reenviado.'],
            ]);
        }

        $convenios = Convenio::query()->where('tenant_id', $tenantId)->get(['id', 'nome', 'carteirinha_blocos']);
        $selecionadas = array_flip($linhaIdsSelecionadas);

        return DB::transaction(function () use ($lote, $selecionadas, $edicoes, $tenantId, $convenios) {
            $totais = ['importados' => 0, 'atualizados' => 0, 'ignorados' => 0, 'invalidas' => 0];

            foreach ($lote->linhas as $linha) {
                $dados = array_merge($linha->dados_json, $edicoes[$linha->id] ?? []);
                $normalizado = $this->normalizarLinha($dados, $linha->linha, $tenantId, $convenios);

                if (! isset($selecionadas[$linha->id])) {
                    $novoStatus = $normalizado['erros'] === [] ? 'ignorado' : 'erro';
                    $linha->update([
                        'status' => $novoStatus,
                        'dados_json' => $normalizado['dados'],
                        'erros_json' => $normalizado['erros'] ?: null,
                        'matched_paciente_id' => $normalizado['matched_paciente_id'],
                    ]);
                    $totais[$novoStatus === 'ignorado' ? 'ignorados' : 'invalidas']++;

                    continue;
                }

                if ($normalizado['erros'] !== []) {
                    $linha->update([
                        'status' => 'erro',
                        'dados_json' => $normalizado['dados'],
                        'erros_json' => $normalizado['erros'],
                        'matched_paciente_id' => $normalizado['matched_paciente_id'],
                    ]);
                    $totais['invalidas']++;

                    continue;
                }

                $pacienteExistente = $normalizado['matched_paciente_id']
                    ? Paciente::query()->find($normalizado['matched_paciente_id'])
                    : null;

                $paciente = $this->gravarPaciente($normalizado['dados'], $tenantId, $pacienteExistente);

                $linha->update([
                    'status' => $pacienteExistente ? 'atualizado' : 'importado',
                    'dados_json' => $normalizado['dados'],
                    'erros_json' => null,
                    'matched_paciente_id' => $paciente->id,
                ]);
                $totais[$pacienteExistente ? 'atualizados' : 'importados']++;
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
                acao: 'paciente_import.confirmado',
                entidade: 'paciente_import_lotes',
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

    /**
     * @return array<string, string> letra da coluna -> texto literal do cabeçalho
     */
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
     * colunas obrigatórias — a clínica mandou a própria planilha, não o
     * modelo baixável. Qualquer falha (sem IA configurada, erro de rede)
     * é silenciosa aqui — quem chama continua com o $colunas de entrada e
     * cai no erro de "faltam colunas" de sempre.
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

    /**
     * @return array<string, string>
     */
    private function mapearCabecalho($sheet): array
    {
        $colunas = [];
        $mapaAliases = $this->aliasesDeCabecalho();

        foreach ($sheet->getRowIterator(1, 1) as $row) {
            foreach ($row->getCellIterator() as $cell) {
                $valor = $this->normalizarTextoCabecalho((string) $cell->getValue());

                if ($valor !== '' && isset($mapaAliases[$valor])) {
                    $colunas[$mapaAliases[$valor]] = $cell->getColumn();
                }
            }
        }

        return $colunas;
    }

    /**
     * @return array<string, string> texto normalizado -> chave canônica
     */
    private function aliasesDeCabecalho(): array
    {
        $aliases = [];

        foreach (array_keys(self::COLUNAS) as $chave) {
            $aliases[$chave] = $chave;
        }

        foreach (self::COLUNAS as $chave => $rotulo) {
            $aliases[$this->normalizarTextoCabecalho($rotulo)] = $chave;
        }

        return $aliases;
    }

    private function normalizarTextoCabecalho(string $texto): string
    {
        $semAcento = strtr($texto, [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a',
            'é' => 'e', 'ê' => 'e',
            'í' => 'i',
            'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ü' => 'u',
            'ç' => 'c',
            'Á' => 'a', 'À' => 'a', 'Ã' => 'a', 'Â' => 'a',
            'É' => 'e', 'Ê' => 'e',
            'Í' => 'i',
            'Ó' => 'o', 'Ô' => 'o', 'Õ' => 'o',
            'Ú' => 'u', 'Ü' => 'u',
            'Ç' => 'c',
        ]);

        $normalizado = preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($semAcento))) ?? '';

        return trim($normalizado, '_');
    }

    /**
     * @param array<string, string> $colunas
     * @return array<string, mixed>
     */
    private function lerLinha($sheet, array $colunas, int $numeroLinha): array
    {
        $bruta = [];

        foreach ($colunas as $chave => $coluna) {
            $cell = $sheet->getCell("{$coluna}{$numeroLinha}");
            $bruta[$chave] = $this->valorCelula($cell);
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
     * Normaliza, valida e resolve o match de uma linha — usada tanto na prévia
     * quanto na confirmação (que reaplica a mesma lógica sobre os dados já
     * editados, para não confirmar algo que nunca foi validado).
     *
     * @param array<int, \App\Models\Convenio> $convenios
     * @return array{dados: array<string, mixed>, erros: array<string, string>, matched_paciente_id: int|null}
     */
    private function normalizarLinha(
        array $bruta,
        int $numeroLinha,
        int $tenantId,
        $convenios,
    ): array {
        $erros = [];

        $nome = trim((string) ($bruta['nome'] ?? ''));
        if ($nome === '') {
            $erros['nome'] = 'Nome é obrigatório.';
        }

        $cpf = preg_replace('/\D+/', '', (string) ($bruta['cpf'] ?? '')) ?: null;
        if ($cpf !== null) {
            (new Cpf())->validate('cpf', $cpf, function (string $mensagem) use (&$erros) {
                $erros['cpf'] = $mensagem;
            });
        }

        $nomeConvenio = trim((string) ($bruta['convenio'] ?? ''));
        $convenio = $nomeConvenio === '' ? null : $convenios->first(
            fn ($item) => mb_strtolower($item->nome) === mb_strtolower($nomeConvenio)
        );

        if ($nomeConvenio === '') {
            $erros['convenio'] = 'Convênio é obrigatório.';
        } elseif (! $convenio) {
            $erros['convenio'] = "Convênio \"{$nomeConvenio}\" não encontrado — cadastre-o antes de importar.";
        }

        $carteirinha = preg_replace('/\s+/', '', (string) ($bruta['carteirinha'] ?? ''));
        if ($carteirinha === '') {
            $erros['carteirinha'] = 'Carteirinha é obrigatória.';
        } elseif ($convenio) {
            $blocos = $convenio->blocosCarteirinha();

            if ($blocos !== null) {
                $digitos = preg_replace('/\D+/', '', $carteirinha);
                $esperado = array_sum($blocos);

                if (strlen($digitos) !== $esperado) {
                    $erros['carteirinha'] = sprintf(
                        'A carteirinha do convênio %s deve conter %d dígitos.',
                        $convenio->nome,
                        $esperado,
                    );
                } else {
                    $carteirinha = $digitos;
                }
            }
        }

        $dataNascimento = $this->normalizarData($bruta['data_nascimento'] ?? null);
        if ($bruta['data_nascimento'] ?? null) {
            if ($dataNascimento === null) {
                $erros['data_nascimento'] = 'Data de nascimento inválida.';
            } elseif (Carbon::parse($dataNascimento)->isFuture()) {
                $erros['data_nascimento'] = 'A data de nascimento não pode estar no futuro.';
            }
        }

        $validadeCarteirinha = $this->normalizarData($bruta['validade_carteirinha'] ?? null);
        if (($bruta['validade_carteirinha'] ?? null) && $validadeCarteirinha === null) {
            $erros['validade_carteirinha'] = 'Validade da carteirinha inválida.';
        }

        $telefone = preg_replace('/\D+/', '', (string) ($bruta['telefone'] ?? '')) ?: null;
        $ativo = $this->normalizarBooleano($bruta['ativo'] ?? null, padrao: true);

        $matchedPacienteId = null;
        if ($cpf) {
            $matchedPacienteId = Paciente::query()
                ->where('tenant_id', $tenantId)
                ->where('cpf', $cpf)
                ->value('id');
        } elseif ($carteirinha !== '' && $convenio) {
            $matchedPacienteId = Paciente::query()
                ->where('tenant_id', $tenantId)
                ->where('carteirinha', $carteirinha)
                ->where('convenio_id', $convenio->id)
                ->value('id');
        }

        return [
            'dados' => [
                'linha' => $numeroLinha,
                'nome' => $nome,
                'cpf' => $cpf,
                'carteirinha' => $carteirinha,
                'convenio' => $nomeConvenio,
                'convenio_id' => $convenio?->id,
                'data_nascimento' => $dataNascimento,
                'validade_carteirinha' => $validadeCarteirinha,
                'telefone' => $telefone,
                'ativo' => $ativo,
            ],
            'erros' => $erros,
            'matched_paciente_id' => $matchedPacienteId,
        ];
    }

    private function normalizarData(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        // Já normalizado na leitura da célula (data real do Excel).
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

    private function normalizarBooleano(mixed $valor, bool $padrao): bool
    {
        if ($valor === null || $valor === '') {
            return $padrao;
        }

        if (is_bool($valor)) {
            return $valor;
        }

        $texto = mb_strtolower(trim((string) $valor));

        return in_array($texto, ['sim', 's', 'true', '1', 'ativo'], true);
    }

    /**
     * @param array<int, array{dados: array<string, mixed>, erros: array<string, string>, matched_paciente_id: int|null}> $linhasProcessadas
     * @return array{lote: array<string, mixed>, linhas: array<int, array<string, mixed>>}
     */
    private function persistirLote(UploadedFile $arquivo, array $linhasProcessadas, int $tenantId): array
    {
        return DB::transaction(function () use ($arquivo, $linhasProcessadas, $tenantId) {
            $arquivoPath = Storage::disk('local')->putFileAs(
                'paciente-import',
                $arquivo,
                $arquivo->hashName()
            );

            $validas = count(array_filter($linhasProcessadas, fn ($item) => $item['erros'] === []));

            $lote = PacienteImportLote::query()->create([
                'tenant_id' => $tenantId,
                'arquivo_nome_original' => $arquivo->getClientOriginalName(),
                'arquivo_path' => $arquivoPath,
                'status' => 'previsualizado',
                'total_linhas' => count($linhasProcessadas),
                'total_validas' => $validas,
                'total_invalidas' => count($linhasProcessadas) - $validas,
            ]);

            foreach ($linhasProcessadas as $item) {
                PacienteImportLinha::query()->create([
                    'tenant_id' => $tenantId,
                    'paciente_import_lote_id' => $lote->id,
                    'linha' => $item['dados']['linha'],
                    'status' => $item['erros'] === [] ? 'valida' : 'erro',
                    'matched_paciente_id' => $item['matched_paciente_id'],
                    'dados_json' => $item['dados'],
                    'erros_json' => $item['erros'] ?: null,
                ]);
            }

            return $this->serializarLote($lote->fresh('linhas'));
        });
    }

    /**
     * @return array{lote: array<string, mixed>, linhas: array<int, array<string, mixed>>}
     */
    private function serializarLote(PacienteImportLote $lote): array
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
            'linhas' => $lote->linhas->map(fn (PacienteImportLinha $linha) => [
                'id' => $linha->id,
                'linha' => $linha->linha,
                'status' => $linha->status,
                'matched_paciente_id' => $linha->matched_paciente_id,
                'dados' => $linha->dados_json,
                'erros' => $linha->erros_json ?? [],
            ])->all(),
        ];
    }

    private function gravarPaciente(array $dados, int $tenantId, ?Paciente $existente): Paciente
    {
        $atributos = [
            'tenant_id' => $tenantId,
            'nome' => $dados['nome'],
            'cpf' => $dados['cpf'],
            'carteirinha' => $dados['carteirinha'],
            'convenio_id' => $dados['convenio_id'],
            'data_nascimento' => $dados['data_nascimento'],
            'validade_carteirinha' => $dados['validade_carteirinha'],
            'ativo' => $dados['ativo'],
        ];

        if ($existente) {
            $existente->fill($atributos);
            $existente->save();
            $paciente = $existente;
        } else {
            $paciente = Paciente::query()->create($atributos);
        }

        if ($dados['telefone']) {
            $this->definirTelefonePrincipal($paciente, $dados['telefone']);
        }

        return $paciente;
    }

    /**
     * Só mexe no telefone quando a planilha trouxe um valor — célula vazia
     * significa "a linha não fala de telefone", não "apague o que já existe".
     */
    private function definirTelefonePrincipal(Paciente $paciente, string $telefone): void
    {
        $principal = $paciente->telefones()->where('principal', true)->first();

        if ($principal) {
            $principal->update(['numero' => $telefone]);

            return;
        }

        PacienteTelefone::query()->create([
            'tenant_id' => $paciente->tenant_id,
            'paciente_id' => $paciente->id,
            'numero' => $telefone,
            'rotulo' => 'celular',
            'principal' => true,
            'ordem' => 0,
        ]);
    }
}
