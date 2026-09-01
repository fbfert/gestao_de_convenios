<?php

namespace App\Services;

use App\Models\Cid;
use App\Models\Convenio;
use App\Models\Especialidade;
use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\Solicitacao;
use App\Models\SolicitacaoImportLinha;
use App\Models\SolicitacaoImportLote;
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
 * Importação de Solicitações por planilha .xlsx — segunda entidade do mesmo
 * padrão usado em Pacientes (upload → prévia editável → confirmar seletivo).
 *
 * Diferença de Pacientes: uma Solicitação pode ter vários itens (especialidade
 * + profissional), então cada linha da planilha é UM ITEM — linhas com o mesmo
 * "Protocolo" viram itens da mesma Solicitação. Protocolo também é a chave de
 * correspondência: reimportar o mesmo protocolo atualiza em vez de duplicar
 * (linha sem protocolo nunca casa com nada, sempre cria uma Solicitação nova
 * de item único).
 */
class SolicitacaoImportService
{
    public function __construct(
        private readonly ImportacaoHeaderMappingAiService $headerMappingAi
    ) {
    }

    private const COLUNAS = [
        'protocolo' => 'Protocolo',
        'paciente_cpf' => 'Paciente CPF',
        'paciente_carteirinha' => 'Paciente Carteirinha',
        'convenio' => 'Convênio',
        'medico' => 'Médico',
        'cids' => 'CID(s)',
        'solicitado_em' => 'Data da solicitação',
        'status' => 'Status',
        'observacoes' => 'Observações',
        'especialidade' => 'Especialidade',
        'profissional' => 'Profissional',
        'quantidade' => 'Quantidade',
        'item_observacoes' => 'Observações do item',
    ];

    private const LINHA_EXEMPLO = [
        'protocolo' => 'PROT-0001',
        'paciente_cpf' => '123.456.789-09',
        'paciente_carteirinha' => '',
        'convenio' => 'Unimed',
        'medico' => 'Dra. Ana Souza',
        'cids' => 'F84.0',
        'solicitado_em' => '15/01/2026',
        'status' => '',
        'observacoes' => '',
        'especialidade' => 'Fonoaudiologia',
        'profissional' => 'João Terapeuta',
        'quantidade' => '10',
        'item_observacoes' => '',
    ];

    /**
     * Status aceitos direto da planilha — os demais (ready_for_automation,
     * guia_gerada, approved) dependem de guias reais vinculadas e nunca fazem
     * sentido vindo de uma planilha sem automação nenhuma por trás.
     *
     * dados_json guarda a CHAVE canônica (ex.: 'under_review'), não o texto
     * digitado — e normalizarLinha() roda de novo em cima do dados_json
     * salvo na hora de confirmar. Sem os identity-mappings abaixo, essa
     * segunda passada não reconheceria a própria chave que ela mesma gravou.
     */
    private const STATUS_ACEITOS = [
        '' => 'under_review',
        'em_analise' => 'under_review',
        'negado' => 'denied',
        'cancelado' => 'canceled',
        'vencido' => 'expired',
        'under_review' => 'under_review',
        'denied' => 'denied',
        'canceled' => 'canceled',
        'expired' => 'expired',
    ];

    public function gerarTemplate(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Solicitações');

        $coluna = 'A';
        foreach (self::COLUNAS as $chave => $rotulo) {
            $sheet->setCellValue("{$coluna}1", $rotulo);
            $sheet->getColumnDimension($coluna)->setAutoSize(true);
            $sheet->setCellValue("{$coluna}2", self::LINHA_EXEMPLO[$chave]);
            $coluna++;
        }
        $sheet->getStyle('A1:M1')->getFont()->setBold(true);

        $writer = new Xlsx($spreadsheet);

        return new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="modelo-importacao-solicitacoes.xlsx"',
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

        $obrigatorias = ['convenio', 'medico', 'cids', 'solicitado_em', 'especialidade', 'profissional'];
        if (count(array_intersect($obrigatorias, array_keys($colunas))) < count($obrigatorias)) {
            $colunas = $this->reforcarComIA($colunas, $this->lerCabecalhoBruto($sheet), $tenantId);
        }

        if (count(array_intersect($obrigatorias, array_keys($colunas))) < count($obrigatorias)) {
            throw new RuntimeException('A planilha precisa ter pelo menos as colunas Convênio, Médico, CID(s), Data da solicitação, Especialidade e Profissional.');
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

        $linhasProcessadas = $this->aplicarConsistenciaDeGrupo($linhasProcessadas, $tenantId);

        return $this->persistirLote($arquivo, $linhasProcessadas, $tenantId);
    }

    /**
     * @param array<int, int> $linhaIdsSelecionadas
     * @param array<int, array<string, mixed>> $edicoes chave = solicitacao_import_linha.id
     * @return array{lote: array<string, mixed>, linhas: array<int, array<string, mixed>>}
     */
    public function confirmar(
        SolicitacaoImportLote $lote,
        array $linhaIdsSelecionadas,
        array $edicoes,
        int $tenantId
    ): array {
        if ($lote->status !== 'previsualizado') {
            throw ValidationException::withMessages([
                'lote' => ['Este lote já foi confirmado (ou cancelado) e não pode ser reenviado.'],
            ]);
        }

        $refs = $this->carregarReferencias($tenantId);
        $selecionadas = array_flip($linhaIdsSelecionadas);

        return DB::transaction(function () use ($lote, $selecionadas, $edicoes, $tenantId, $refs) {
            $linhasDb = $lote->linhas()->orderBy('linha')->get();

            $recalculadas = $linhasDb->map(function (SolicitacaoImportLinha $linha) use ($edicoes, $tenantId, $refs) {
                $dados = array_merge($linha->dados_json, $edicoes[$linha->id] ?? []);

                return [
                    'linhaDb' => $linha,
                    ...$this->normalizarLinha($dados, $linha->linha, $tenantId, $refs),
                ];
            })->all();

            $recalculadas = $this->aplicarConsistenciaDeGrupo($recalculadas, $tenantId);

            $porGrupo = collect($recalculadas)->groupBy(fn ($item) => $item['dados']['grupo']);
            $totais = ['importados' => 0, 'atualizados' => 0, 'ignorados' => 0, 'invalidas' => 0];

            foreach ($porGrupo as $itensDoGrupo) {
                $itensSelecionados = collect($itensDoGrupo)->filter(
                    fn ($item) => isset($selecionadas[$item['linhaDb']->id])
                );

                if ($itensSelecionados->isEmpty()) {
                    foreach ($itensDoGrupo as $item) {
                        $novoStatus = $item['erros'] === [] ? 'ignorado' : 'erro';
                        $item['linhaDb']->update([
                            'status' => $novoStatus,
                            'dados_json' => $item['dados'],
                            'erros_json' => $item['erros'] ?: null,
                        ]);
                        $totais[$novoStatus === 'ignorado' ? 'ignorados' : 'invalidas']++;
                    }

                    continue;
                }

                $comErro = $itensSelecionados->filter(fn ($item) => $item['erros'] !== []);
                $semErro = $itensSelecionados->filter(fn ($item) => $item['erros'] === []);

                foreach ($comErro as $item) {
                    $item['linhaDb']->update([
                        'status' => 'erro',
                        'dados_json' => $item['dados'],
                        'erros_json' => $item['erros'],
                    ]);
                    $totais['invalidas']++;
                }

                if ($semErro->isEmpty()) {
                    continue;
                }

                $header = $semErro->first()['dados'];
                $matchedId = $header['matched_solicitacao_id'] ?? null;
                $existente = $matchedId ? Solicitacao::query()->find($matchedId) : null;

                $solicitacao = $this->gravarGrupo($header, $semErro->pluck('dados')->all(), $tenantId, $existente);

                foreach ($semErro as $item) {
                    $item['linhaDb']->update([
                        'status' => $existente ? 'atualizado' : 'importado',
                        'dados_json' => [...$item['dados'], 'matched_solicitacao_id' => $solicitacao->id],
                        'erros_json' => null,
                        'matched_solicitacao_id' => $solicitacao->id,
                    ]);
                }
                $totais[$existente ? 'atualizados' : 'importados'] += $semErro->count();

                foreach ($itensDoGrupo as $item) {
                    if (isset($selecionadas[$item['linhaDb']->id])) {
                        continue;
                    }

                    $novoStatus = $item['erros'] === [] ? 'ignorado' : 'erro';
                    $item['linhaDb']->update([
                        'status' => $novoStatus,
                        'dados_json' => $item['dados'],
                        'erros_json' => $item['erros'] ?: null,
                    ]);
                    $totais[$novoStatus === 'ignorado' ? 'ignorados' : 'invalidas']++;
                }
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
                acao: 'solicitacao_import.confirmado',
                entidade: 'solicitacao_import_lotes',
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
     * @return array{convenios: \Illuminate\Support\Collection, medicos: \Illuminate\Support\Collection, cids: \Illuminate\Support\Collection, especialidades: \Illuminate\Support\Collection, profissionais: \Illuminate\Support\Collection}
     */
    private function carregarReferencias(int $tenantId): array
    {
        return [
            'convenios' => Convenio::query()->where('tenant_id', $tenantId)->get(['id', 'nome']),
            'medicos' => Medico::query()->where('tenant_id', $tenantId)->get(['id', 'nome']),
            'cids' => Cid::query()->where('tenant_id', $tenantId)->get(['id', 'codigo']),
            'especialidades' => Especialidade::query()->where('tenant_id', $tenantId)->get(['id', 'nome']),
            'profissionais' => Profissional::query()->where('tenant_id', $tenantId)->get(['id', 'nome']),
        ];
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

    /**
     * @return array<string, string>
     */
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

    /**
     * @param array<string, string> $colunas
     * @return array<string, mixed>
     */
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
     * @return array{dados: array<string, mixed>, erros: array<string, string>}
     */
    private function normalizarLinha(array $bruta, int $numeroLinha, int $tenantId, array $refs): array
    {
        $erros = [];

        $protocolo = trim((string) ($bruta['protocolo'] ?? ''));
        $grupo = $protocolo !== '' ? "protocolo:{$protocolo}" : "linha:{$numeroLinha}";

        // --- paciente ---
        $cpf = preg_replace('/\D+/', '', (string) ($bruta['paciente_cpf'] ?? '')) ?: null;
        $carteirinha = trim((string) ($bruta['paciente_carteirinha'] ?? '')) ?: null;

        // --- convênio (resolvido antes do paciente: carteirinha só casa com convênio certo) ---
        $nomeConvenio = trim((string) ($bruta['convenio'] ?? ''));
        $convenio = $nomeConvenio === '' ? null : $refs['convenios']->first(
            fn ($item) => mb_strtolower($item->nome) === mb_strtolower($nomeConvenio)
        );
        if ($nomeConvenio === '') {
            $erros['convenio'] = 'Convênio é obrigatório.';
        } elseif (! $convenio) {
            $erros['convenio'] = "Convênio \"{$nomeConvenio}\" não encontrado.";
        }

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
            $erros['paciente_cpf'] = 'Paciente não encontrado — cadastre-o antes de importar (aba Pacientes).';
        }

        // --- médico ---
        $nomeMedico = trim((string) ($bruta['medico'] ?? ''));
        $medico = $nomeMedico === '' ? null : $refs['medicos']->first(
            fn ($item) => mb_strtolower($item->nome) === mb_strtolower($nomeMedico)
        );
        if ($nomeMedico === '') {
            $erros['medico'] = 'Médico é obrigatório.';
        } elseif (! $medico) {
            $erros['medico'] = "Médico \"{$nomeMedico}\" não encontrado.";
        }

        // --- CID(s) ---
        $textoCids = trim((string) ($bruta['cids'] ?? ''));
        $cidIds = [];
        if ($textoCids === '') {
            $erros['cids'] = 'Informe pelo menos um CID.';
        } else {
            $naoEncontrados = [];
            foreach (preg_split('/[,;]/', $textoCids) as $codigo) {
                $codigo = trim($codigo);
                if ($codigo === '') {
                    continue;
                }

                $cid = $refs['cids']->first(fn ($item) => mb_strtolower($item->codigo) === mb_strtolower($codigo));

                if ($cid) {
                    $cidIds[] = $cid->id;
                } else {
                    $naoEncontrados[] = $codigo;
                }
            }

            if ($naoEncontrados !== []) {
                $erros['cids'] = 'CID(s) não encontrado(s): '.implode(', ', $naoEncontrados).'.';
            }
        }

        // --- data ---
        $solicitadoEm = $this->normalizarData($bruta['solicitado_em'] ?? null);
        if (! $solicitadoEm) {
            $erros['solicitado_em'] = 'Data da solicitação inválida ou ausente.';
        }

        // --- status ---
        $statusTexto = $this->normalizarTexto((string) ($bruta['status'] ?? ''));
        $status = self::STATUS_ACEITOS[$statusTexto] ?? null;
        if ($status === null) {
            $erros['status'] = 'Status inválido. Use Em análise, Negado, Cancelado ou Vencido (ou deixe em branco).';
        }

        // --- especialidade / profissional (item) ---
        $nomeEspecialidade = trim((string) ($bruta['especialidade'] ?? ''));
        $especialidade = $nomeEspecialidade === '' ? null : $refs['especialidades']->first(
            fn ($item) => mb_strtolower($item->nome) === mb_strtolower($nomeEspecialidade)
        );
        if ($nomeEspecialidade === '') {
            $erros['especialidade'] = 'Especialidade é obrigatória.';
        } elseif (! $especialidade) {
            $erros['especialidade'] = "Especialidade \"{$nomeEspecialidade}\" não encontrada.";
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

        $quantidade = (int) preg_replace('/\D+/', '', (string) ($bruta['quantidade'] ?? '')) ?: 10;

        return [
            'dados' => [
                'linha' => $numeroLinha,
                'grupo' => $grupo,
                'protocolo' => $protocolo ?: null,
                'paciente_cpf' => $cpf,
                'paciente_carteirinha' => $carteirinha,
                'paciente_id' => $pacienteId,
                'convenio' => $nomeConvenio,
                'convenio_id' => $convenio?->id,
                'medico' => $nomeMedico,
                'medico_id' => $medico?->id,
                'cids' => $textoCids,
                'cid_ids' => $cidIds,
                'solicitado_em' => $solicitadoEm,
                'status' => $status,
                'observacoes' => trim((string) ($bruta['observacoes'] ?? '')) ?: null,
                'especialidade' => $nomeEspecialidade,
                'especialidade_id' => $especialidade?->id,
                'profissional' => $nomeProfissional,
                'profissional_id' => $profissional?->id,
                'quantidade' => $quantidade,
                'item_observacoes' => trim((string) ($bruta['item_observacoes'] ?? '')) ?: null,
                'matched_solicitacao_id' => null,
            ],
            'erros' => $erros,
        ];
    }

    /**
     * Resolve o match por protocolo (uma vez por grupo) e confere que todo
     * mundo no mesmo protocolo concorda nos campos de cabeçalho — planilha
     * mal preenchida (protocolo repetido com paciente diferente, por
     * exemplo) vira erro em vez de virar dado errado silenciosamente.
     *
     * @param array<int, array{dados: array<string, mixed>, erros: array<string, string>}> $linhas
     * @return array<int, array<string, mixed>>
     */
    private function aplicarConsistenciaDeGrupo(array $linhas, int $tenantId): array
    {
        $porGrupo = [];
        foreach ($linhas as $indice => $linha) {
            $porGrupo[$linha['dados']['grupo']][] = $indice;
        }

        foreach ($porGrupo as $grupo => $indices) {
            $protocolo = $linhas[$indices[0]]['dados']['protocolo'];
            $matchedId = null;

            if ($protocolo) {
                $matchedId = Solicitacao::query()
                    ->where('tenant_id', $tenantId)
                    ->where('protocolo_importacao', $protocolo)
                    ->value('id');
            }

            foreach ($indices as $i) {
                $linhas[$i]['dados']['matched_solicitacao_id'] = $matchedId;
            }

            if (count($indices) < 2) {
                continue;
            }

            $campos = ['paciente_id', 'convenio_id', 'medico_id', 'cid_ids', 'solicitado_em', 'status'];
            $referencia = $linhas[$indices[0]]['dados'];

            foreach ($indices as $i) {
                foreach ($campos as $campo) {
                    if ($linhas[$i]['dados'][$campo] !== $referencia[$campo]) {
                        $linhas[$i]['erros']['grupo'] = "Os dados desta solicitação não batem com as outras linhas do protocolo \"{$protocolo}\".";

                        break;
                    }
                }
            }
        }

        return $linhas;
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
     * @param array<int, array{dados: array<string, mixed>, erros: array<string, string>}> $linhasProcessadas
     * @return array{lote: array<string, mixed>, linhas: array<int, array<string, mixed>>}
     */
    private function persistirLote(UploadedFile $arquivo, array $linhasProcessadas, int $tenantId): array
    {
        return DB::transaction(function () use ($arquivo, $linhasProcessadas, $tenantId) {
            $arquivoPath = Storage::disk('local')->putFileAs('solicitacao-import', $arquivo, $arquivo->hashName());
            $validas = count(array_filter($linhasProcessadas, fn ($item) => $item['erros'] === []));

            $lote = SolicitacaoImportLote::query()->create([
                'tenant_id' => $tenantId,
                'arquivo_nome_original' => $arquivo->getClientOriginalName(),
                'arquivo_path' => $arquivoPath,
                'status' => 'previsualizado',
                'total_linhas' => count($linhasProcessadas),
                'total_validas' => $validas,
                'total_invalidas' => count($linhasProcessadas) - $validas,
            ]);

            foreach ($linhasProcessadas as $item) {
                SolicitacaoImportLinha::query()->create([
                    'tenant_id' => $tenantId,
                    'solicitacao_import_lote_id' => $lote->id,
                    'linha' => $item['dados']['linha'],
                    'grupo' => $item['dados']['grupo'],
                    'status' => $item['erros'] === [] ? 'valida' : 'erro',
                    'matched_solicitacao_id' => $item['dados']['matched_solicitacao_id'],
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
    private function serializarLote(SolicitacaoImportLote $lote): array
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
            'linhas' => $lote->linhas->map(fn (SolicitacaoImportLinha $linha) => [
                'id' => $linha->id,
                'linha' => $linha->linha,
                'grupo' => $linha->grupo,
                'status' => $linha->status,
                'matched_solicitacao_id' => $linha->matched_solicitacao_id,
                'dados' => $linha->dados_json,
                'erros' => $linha->erros_json ?? [],
            ])->all(),
        ];
    }

    /**
     * Cria ou atualiza a Solicitação do grupo — na atualização, substitui os
     * itens inteiros pelo que veio confirmado agora (mesmo padrão de
     * "regrava a lista inteira" já usado pra telefones de Paciente): a
     * planilha manda a lista completa, e casar item a item por planilha não
     * tem chave estável.
     *
     * @param array<string, mixed> $header
     * @param array<int, array<string, mixed>> $itens
     */
    private function gravarGrupo(array $header, array $itens, int $tenantId, ?Solicitacao $existente): Solicitacao
    {
        return DB::transaction(function () use ($header, $itens, $tenantId, $existente) {
            $atributos = [
                'tenant_id' => $tenantId,
                'paciente_id' => $header['paciente_id'],
                'convenio_id' => $header['convenio_id'],
                'medico_id' => $header['medico_id'],
                'profissional_id' => $itens[0]['profissional_id'],
                'especialidade_id' => $itens[0]['especialidade_id'],
                'status' => $header['status'],
                'solicitado_em' => $header['solicitado_em'],
                'observacoes' => $header['observacoes'],
                'protocolo_importacao' => $header['protocolo'],
            ];

            if ($existente) {
                $existente->fill($atributos);
                $existente->save();
                $solicitacao = $existente;
                $solicitacao->itens()->delete();
            } else {
                $solicitacao = Solicitacao::query()->create($atributos);
            }

            $solicitacao->cidCadastros()->sync($header['cid_ids']);

            foreach ($itens as $item) {
                $solicitacao->itens()->create([
                    'tenant_id' => $tenantId,
                    'especialidade_id' => $item['especialidade_id'],
                    'profissional_id' => $item['profissional_id'],
                    'quantidade' => $item['quantidade'],
                    'status_operacional' => 'pending',
                    'observacoes' => $item['item_observacoes'],
                ]);
            }

            return $solicitacao->refresh();
        });
    }
}
