<?php

namespace App\Services;

use App\Models\AiOpenaiSetting;
use App\Models\AiPromptTemplate;
use App\Models\Convenio;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Leitura da carteirinha do convênio por IA.
 *
 * Mesmo caminho do PedidoMedicoAiService — prompt editável, conexão OpenAI por
 * clínica, resposta em JSON —, com uma diferença importante: aqui nada é
 * gravado no cadastro. O serviço devolve o que leu, e quem decide é o operador
 * com a carteirinha na mão.
 */
class CarteirinhaAiService
{
    /**
     * Semelhança mínima, em porcento, para dar por certo que o convênio lido é
     * um convênio cadastrado.
     *
     * O corte é alto porque errar aqui é grave: o convênio manda no formato da
     * carteirinha, nas regras de autorização e no valor pago. Abaixo do corte
     * o nome lido volta como aviso, e o campo fica em branco.
     */
    private const CONFIANCA_MINIMA = 85;

    public function analisar(int $tenantId, UploadedFile $arquivo, string $path): array
    {
        $prompt = AiPromptTemplate::query()
            ->where('tenant_id', $tenantId)
            ->where('chave', 'ler_carteirinha')
            ->where('ativo', true)
            ->first();

        $openai = AiOpenaiSetting::query()
            ->where('tenant_id', $tenantId)
            ->where('ativo', true)
            ->first();

        if (! $prompt || ! $openai || blank($openai->api_key)) {
            throw ValidationException::withMessages([
                'openai' => 'Configure a conexão OpenAI e o prompt ler_carteirinha antes de ler carteirinhas.',
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
                  O contrato de saída fica aqui, e não no prompt editável, pela
                  mesma razão do pedido médico: `parseJson()` e o casamento de
                  convênio dependem exatamente destas chaves. Reescrever o
                  prompt na tela passaria a devolver campos vazios sem erro.
                */
                'text' => $prompt->user_prompt."\n\nRetorne somente JSON com as chaves: carteirinha (somente os dígitos do número do beneficiário, sem espaços nem pontuação), nome (nome completo do beneficiário como impresso), convenio (nome da operadora impressa no cartão), cpf (somente dígitos, ou null), data_nascimento no formato YYYY-MM-DD ou null, validade_carteirinha no formato YYYY-MM-DD ou null, observacoes para o que não conseguir ler. Use null no campo que não estiver no cartão; não invente valor.",
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
            $request = Http::withToken($openai->api_key)->acceptJson()->timeout(60);

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
                'openai' => 'Não foi possível conectar à OpenAI para ler a carteirinha.',
            ]);
        }

        if ($response->failed()) {
            throw ValidationException::withMessages([
                'openai' => 'A OpenAI recusou a leitura da carteirinha. Verifique a configuração de IA.',
            ]);
        }

        $texto = $this->extrairTexto($response->json());
        $dados = $this->parseJson($texto);
        $convenio = $this->casarConvenio($tenantId, (string) ($dados['convenio'] ?? ''));

        return [
            'model' => $model,
            'raw_text' => $texto,
            'dados' => [
                'carteirinha' => $this->somenteDigitos($dados['carteirinha'] ?? null),
                'nome' => $this->texto($dados['nome'] ?? null),
                'cpf' => $this->somenteDigitos($dados['cpf'] ?? null),
                'data_nascimento' => $this->data($dados['data_nascimento'] ?? null),
                'validade_carteirinha' => $this->data($dados['validade_carteirinha'] ?? null),
                'observacoes' => $this->texto($dados['observacoes'] ?? null),
            ],
            'convenio' => [
                'lido' => $this->texto($dados['convenio'] ?? null),
                // Só preenche o campo quando tem certeza. Convênio errado
                // contamina formato de carteirinha, regra e valor.
                'id' => $convenio?->id,
                'nome' => $convenio?->nome,
            ],
        ];
    }

    private function casarConvenio(int $tenantId, string $lido): ?Convenio
    {
        $lido = trim($lido);

        if ($lido === '') {
            return null;
        }

        $melhor = null;
        $melhorNota = 0.0;

        foreach (Convenio::query()->where('tenant_id', $tenantId)->where('ativo', true)->get() as $convenio) {
            similar_text(
                mb_strtolower($this->semAcento($lido)),
                mb_strtolower($this->semAcento($convenio->nome)),
                $nota,
            );

            if ($nota > $melhorNota) {
                $melhorNota = $nota;
                $melhor = $convenio;
            }
        }

        return $melhorNota >= self::CONFIANCA_MINIMA ? $melhor : null;
    }

    private function semAcento(string $valor): string
    {
        return iconv('UTF-8', 'ASCII//TRANSLIT', $valor) ?: $valor;
    }

    private function somenteDigitos(mixed $valor): ?string
    {
        $digitos = preg_replace('/\D+/', '', (string) $valor);

        return $digitos === '' ? null : $digitos;
    }

    private function texto(mixed $valor): ?string
    {
        $texto = trim((string) $valor);

        return $texto === '' || strtolower($texto) === 'null' ? null : $texto;
    }

    private function data(mixed $valor): ?string
    {
        $texto = $this->texto($valor);

        if ($texto === null) {
            return null;
        }

        // Só aceita o formato pedido. Data em formato inesperado vira campo em
        // branco, e não uma data errada preenchida com confiança.
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $texto) ? $texto : null;
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
        $limpo = trim($texto);
        $limpo = preg_replace('/^```(?:json)?|```$/m', '', $limpo) ?? $limpo;
        $decodificado = json_decode(trim($limpo), true);

        if (is_array($decodificado)) {
            return $decodificado;
        }

        // Modelo que responde com texto em volta do JSON: aproveita o primeiro
        // objeto encontrado em vez de descartar a leitura inteira.
        if (preg_match('/\{.*\}/s', $limpo, $encontrado)) {
            $decodificado = json_decode($encontrado[0], true);
        }

        return is_array($decodificado) ? $decodificado : [];
    }
}
