<?php

namespace App\Services;

use App\Models\AiOpenaiSetting;
use App\Models\AiPromptTemplate;
use App\Models\Especialidade;
use App\Models\Medico;
use App\Models\Paciente;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PedidoMedicoAiService
{
    /**
     * Semelhança mínima, em porcento, para tratar um cadastro como sendo a
     * mesma especialidade que o documento cita.
     *
     * O corte é alto de propósito. `similar_text` dá 75% entre
     * "Psicopedagogia" e "Psicologia", que são terapias diferentes: aplicar
     * esse palpite sozinho trocaria a especialidade do paciente em silêncio.
     * Abaixo do corte a sugestão continua aparecendo na tela, mas como
     * palpite, ao lado do convite para cadastrar o termo lido — a decisão
     * fica com o operador, que tem o documento na mão.
     */
    private const CONFIANCA_MINIMA = 90;


    public function analisar(int $tenantId, UploadedFile $arquivo, string $path): array
    {
        $prompt = AiPromptTemplate::query()
            ->where('tenant_id', $tenantId)
            ->where('chave', 'ler_solicitacao_medica')
            ->where('ativo', true)
            ->first();

        $openai = AiOpenaiSetting::query()
            ->where('tenant_id', $tenantId)
            ->where('ativo', true)
            ->first();

        if (! $prompt || ! $openai || blank($openai->api_key)) {
            throw ValidationException::withMessages([
                'openai' => 'Configure a conexão OpenAI e o prompt ler_solicitacao_medica antes de ler pedidos médicos.',
            ]);
        }

        // Prompt manda; sem ele, o modelo padrao da conexao; o literal fica
        // como ultimo recurso, para uma instalacao que nunca configurou nada
        // nao quebrar sozinha. Ver a migration 2026_08_12_190000.
        $model = $prompt->model_id ?: ($openai->model_id ?: 'gpt-5.6-luna');
        $mime = $arquivo->getMimeType() ?: 'application/octet-stream';
        $base64 = base64_encode(Storage::disk('local')->get($path));
        $fileData = "data:{$mime};base64,{$base64}";

        $content = [
            [
                'type' => 'input_text',
                /*
                  O contrato de saída fica aqui, e não no prompt editável, por
                  uma razão prática: parseJson() e sugerirEspecialidades()
                  dependem exatamente destas chaves. Se o operador reescrevesse
                  o prompt na tela de Prompts e trocasse os nomes, a leitura
                  passaria a devolver campos vazios sem erro nenhum.

                  `especialidades` é lista: um mesmo pedido costuma trazer
                  várias (fono, nutrição, psicologia...). Antes a chave era
                  `especialidade_nome`, no singular, e as demais especialidades
                  acabavam descritas em `observacoes` — visíveis para o
                  operador, mas fora dos campos do formulário.
                */
                'text' => $prompt->user_prompt."\n\nRetorne somente JSON com as chaves: paciente_nome, medico_nome, especialidades (array com o nome de cada especialidade citada no pedido, uma por item, mesmo que seja só uma), solicitado_em no formato YYYY-MM-DD quando possível, observacoes para informações incertas ou não entendidas. Não repita em observacoes as especialidades já listadas em especialidades.",
            ],
        ];

        if ($mime === 'application/pdf') {
            $content[] = [
                'type' => 'input_file',
                'filename' => $arquivo->getClientOriginalName(),
                'file_data' => $fileData,
            ];
        } else {
            $content[] = [
                'type' => 'input_image',
                'image_url' => $fileData,
            ];
        }

        try {
            $request = Http::withToken($openai->api_key)
                ->acceptJson()
                ->timeout(60);

            if (filled($openai->organization_id)) {
                $request = $request->withHeaders(['OpenAI-Organization' => $openai->organization_id]);
            }

            if (filled($openai->project_id)) {
                $request = $request->withHeaders(['OpenAI-Project' => $openai->project_id]);
            }

            $response = $request->post(rtrim($openai->base_url, '/').'/responses', [
                'model' => $model,
                'input' => [
                    [
                        'role' => 'system',
                        'content' => [
                            [
                                'type' => 'input_text',
                                'text' => $prompt->system_prompt,
                            ],
                        ],
                    ],
                    [
                        'role' => 'user',
                        'content' => $content,
                    ],
                ],
            ]);
        } catch (ConnectionException) {
            throw ValidationException::withMessages([
                'openai' => 'Não foi possível conectar à OpenAI para ler o pedido médico.',
            ]);
        }

        if ($response->failed()) {
            throw ValidationException::withMessages([
                'openai' => 'A OpenAI recusou a leitura do pedido médico. Verifique a configuração de IA.',
            ]);
        }

        $texto = $this->extrairTexto($response->json());
        $dados = $this->parseJson($texto);

        return [
            'model' => $model,
            'raw_text' => $texto,
            'dados' => $dados,
            'sugestoes' => [
                'pacientes' => $this->sugerirPacientes($tenantId, (string) ($dados['paciente_nome'] ?? '')),
                'medicos' => $this->sugerirMedicos($tenantId, (string) ($dados['medico_nome'] ?? '')),
                'especialidades' => $this->sugerirEspecialidadesLidas($tenantId, $dados),
            ],
        ];
    }

    private function extrairTexto(array $response): string
    {
        if (isset($response['output_text']) && is_string($response['output_text'])) {
            return $response['output_text'];
        }

        $parts = [];
        foreach ($response['output'] ?? [] as $output) {
            foreach ($output['content'] ?? [] as $content) {
                if (($content['type'] ?? null) === 'output_text' && isset($content['text'])) {
                    $parts[] = $content['text'];
                }
            }
        }

        return trim(implode("\n", $parts));
    }

    private function parseJson(string $texto): array
    {
        $clean = trim($texto);
        $clean = preg_replace('/^```json\s*|\s*```$/', '', $clean) ?? $clean;
        $decoded = json_decode($clean, true);

        if (! is_array($decoded)) {
            return [
                'observacoes' => $texto,
            ];
        }

        return $decoded;
    }

    private function sugerirPacientes(int $tenantId, string $nome): array
    {
        return $this->rankByNome(
            Paciente::query()->where('tenant_id', $tenantId)->get(['id', 'nome', 'carteirinha']),
            $nome,
            fn ($paciente) => [
                'id' => $paciente->id,
                'nome' => $paciente->nome,
                'carteirinha' => $paciente->carteirinha,
            ],
        );
    }

    private function sugerirMedicos(int $tenantId, string $nome): array
    {
        return $this->rankByNome(
            Medico::query()->where('tenant_id', $tenantId)->get(['id', 'nome', 'crm']),
            $nome,
            fn ($medico) => [
                'id' => $medico->id,
                'nome' => $medico->nome,
                'crm' => $medico->crm,
            ],
        );
    }

    /**
     * Uma entrada por especialidade lida, cada uma com os cadastros parecidos.
     *
     * A tela precisa saber a que termo do documento cada sugestão pertence:
     * com quatro especialidades no pedido, uma lista achatada não diria qual
     * casou com o quê, nem quais não casaram com nada e portanto são
     * candidatas a cadastro.
     *
     * @return array<int, array{termo: string, matches: array}>
     */
    private function sugerirEspecialidadesLidas(int $tenantId, array $dados): array
    {
        $cadastradas = Especialidade::query()->where('tenant_id', $tenantId)->get(['id', 'nome']);

        return collect($this->especialidadesLidas($dados))
            ->map(function (string $termo) use ($cadastradas) {
                $matches = $this->rankByNome(
                    $cadastradas,
                    $termo,
                    fn ($especialidade) => [
                        'id' => $especialidade->id,
                        'nome' => $especialidade->nome,
                    ],
                );

                return [
                    'termo' => $termo,
                    'matches' => $matches,
                    // Nenhum cadastro parecido o bastante para ser "o mesmo".
                    'sugere_cadastro' => ($matches[0]['similaridade'] ?? 0) < self::CONFIANCA_MINIMA,
                ];
            })
            ->all();
    }

    /**
     * Nomes de especialidade lidos do documento, sem repetição.
     *
     * Aceita `especialidade_nome` além de `especialidades` porque um prompt
     * antigo, ou um modelo que ignore a instrução, ainda pode devolver a chave
     * no singular.
     *
     * @return string[]
     */
    private function especialidadesLidas(array $dados): array
    {
        $brutos = $dados['especialidades'] ?? $dados['especialidade_nome'] ?? [];

        return collect(is_array($brutos) ? $brutos : [$brutos])
            ->map(fn ($nome) => trim((string) $nome))
            ->filter(fn (string $nome) => $nome !== '')
            ->unique(fn (string $nome) => mb_strtolower($nome))
            ->values()
            ->all();
    }

    private function rankByNome($items, string $nome, callable $mapper): array
    {
        $needle = mb_strtolower(trim($nome));

        return $items
            ->map(function ($item) use ($needle, $mapper) {
                $candidate = mb_strtolower($item->nome);
                similar_text($needle, $candidate, $percent);

                return $mapper($item) + ['similaridade' => round($percent, 2)];
            })
            ->filter(fn ($item) => $needle === '' || $item['similaridade'] > 20)
            ->sortByDesc('similaridade')
            ->take(5)
            ->values()
            ->all();
    }
}
