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

        $model = $prompt->model_id ?: 'gpt-5.6-luna';
        $mime = $arquivo->getMimeType() ?: 'application/octet-stream';
        $base64 = base64_encode(Storage::disk('local')->get($path));
        $fileData = "data:{$mime};base64,{$base64}";

        $content = [
            [
                'type' => 'input_text',
                'text' => $prompt->user_prompt."\n\nRetorne somente JSON com as chaves: paciente_nome, medico_nome, especialidade_nome, solicitado_em no formato YYYY-MM-DD quando possível, observacoes para informações incertas ou não entendidas.",
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
                'especialidades' => $this->sugerirEspecialidades($tenantId, (string) ($dados['especialidade_nome'] ?? '')),
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

    private function sugerirEspecialidades(int $tenantId, string $nome): array
    {
        return $this->rankByNome(
            Especialidade::query()->where('tenant_id', $tenantId)->get(['id', 'nome']),
            $nome,
            fn ($especialidade) => [
                'id' => $especialidade->id,
                'nome' => $especialidade->nome,
            ],
        );
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
