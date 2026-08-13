<?php

namespace App\Services;

use App\Models\AiOpenaiSetting;
use App\Models\AiPromptTemplate;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Leitura por IA do registro de sessões escaneado.
 *
 * Até aqui a importação exigia que alguém produzisse a transcrição fora do
 * sistema e colasse o texto — o campo se chamava "Transcrição OCR", mas não
 * havia OCR nenhum: quem lia era um parser de expressões regulares.
 *
 * Devolve exatamente o mesmo formato do LancamentoTranscricaoService
 * (`cabecalho` + `sessoes`), para a tela de revisão e a confirmação seguirem
 * iguais, venha o dado de foto ou de texto colado.
 */
class RegistroSessoesAiService
{
    public function analisar(int $tenantId, UploadedFile $arquivo, string $path): array
    {
        // Sem seeder em producao: o prompt de sistema nasce na primeira leitura.
        AiPromptTemplate::garantirPadroes($tenantId);

        $prompt = AiPromptTemplate::query()
            ->where('tenant_id', $tenantId)
            ->where('chave', 'ler_sessoes_escaneadas')
            ->where('ativo', true)
            ->first();

        $openai = AiOpenaiSetting::query()
            ->where('tenant_id', $tenantId)
            ->where('ativo', true)
            ->first();

        if (! $prompt || ! $openai || blank($openai->api_key)) {
            throw ValidationException::withMessages([
                'openai' => 'Configure a conexão OpenAI e o prompt ler_sessoes_escaneadas antes de ler registros de sessões.',
            ]);
        }

        $model = $prompt->model_id ?: ($openai->model_id ?: 'gpt-5.6-luna');
        $mime = $arquivo->getMimeType() ?: 'application/octet-stream';
        $base64 = base64_encode(Storage::disk('local')->get($path));
        $fileData = "data:{$mime};base64,{$base64}";

        $content = [
            [
                'type' => 'input_text',
                /*
                  O contrato de saída fica aqui, e não no prompt editável, pelo
                  mesmo motivo das outras leituras: o formato precisa casar com
                  o que a tela de revisão espera. Reescrever o prompt na tela
                  passaria a devolver campos vazios sem erro nenhum.
                */
                'text' => $prompt->user_prompt."\n\nRetorne somente JSON com as chaves: cabecalho (objeto com guia_numero, clinica, paciente, numero_cartao, profissional_executante, terapia_aplicada) e sessoes (array; cada item com data_sessao no formato YYYY-MM-DD, hora_inicio e hora_fim no formato HH:MM, acompanhante e resumo_atividades). Use null no campo que não conseguir ler; não invente valor. Mantenha a ordem em que as sessões aparecem no documento.",
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
            $request = Http::withToken($openai->api_key)->acceptJson()->timeout(90);

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
                        'content' => [['type' => 'input_text', 'text' => $prompt->system_prompt]],
                    ],
                    [
                        'role' => 'user',
                        'content' => $content,
                    ],
                ],
            ]);
        } catch (ConnectionException) {
            throw ValidationException::withMessages([
                'openai' => 'Não foi possível conectar à OpenAI para ler o registro de sessões.',
            ]);
        }

        if ($response->failed()) {
            throw ValidationException::withMessages([
                'openai' => 'A OpenAI recusou a leitura do registro de sessões. Verifique a configuração de IA.',
            ]);
        }

        $dados = $this->parseJson($this->extrairTexto($response->json()));

        return [
            'cabecalho' => [
                'guia_numero' => $this->texto($dados['cabecalho']['guia_numero'] ?? null),
                'clinica' => $this->texto($dados['cabecalho']['clinica'] ?? null),
                'paciente' => $this->texto($dados['cabecalho']['paciente'] ?? null),
                'numero_cartao' => $this->texto($dados['cabecalho']['numero_cartao'] ?? null),
                'profissional_executante' => $this->texto($dados['cabecalho']['profissional_executante'] ?? null),
                'terapia_aplicada' => $this->texto($dados['cabecalho']['terapia_aplicada'] ?? null),
            ],
            'sessoes' => collect($dados['sessoes'] ?? [])
                ->map(fn ($sessao) => [
                    'data_sessao' => $this->data($sessao['data_sessao'] ?? null),
                    'hora_inicio' => $this->hora($sessao['hora_inicio'] ?? null),
                    'hora_fim' => $this->hora($sessao['hora_fim'] ?? null),
                    'acompanhante' => $this->texto($sessao['acompanhante'] ?? null),
                    'resumo_atividades' => $this->texto($sessao['resumo_atividades'] ?? null),
                ])
                // Linha sem data e sem horário não é sessão: é ruído de leitura.
                ->reject(fn ($sessao) => ! $sessao['data_sessao'] && ! $sessao['hora_inicio'])
                ->values()
                ->all(),
        ];
    }

    private function texto(mixed $valor): ?string
    {
        $texto = trim((string) $valor);

        return $texto === '' || strtolower($texto) === 'null' ? null : $texto;
    }

    /** Data fora do formato pedido vira campo em branco, e não data errada. */
    private function data(mixed $valor): ?string
    {
        $texto = $this->texto($valor);

        return $texto !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $texto) ? $texto : null;
    }

    private function hora(mixed $valor): ?string
    {
        $texto = $this->texto($valor);

        return $texto !== null && preg_match('/^\d{2}:\d{2}$/', $texto) ? $texto : null;
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
        $limpo = preg_replace('/^```(?:json)?|```$/m', '', trim($texto)) ?? $texto;
        $decodificado = json_decode(trim($limpo), true);

        if (is_array($decodificado)) {
            return $decodificado;
        }

        if (preg_match('/\{.*\}/s', $limpo, $encontrado)) {
            $decodificado = json_decode($encontrado[0], true);
        }

        return is_array($decodificado) ? $decodificado : [];
    }
}
