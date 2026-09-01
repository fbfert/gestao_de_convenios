<?php

namespace App\Services;

use App\Models\AiOpenaiSetting;
use App\Models\AiPromptTemplate;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Reforço por IA pro mapeamento de cabeçalho das importações por planilha
 * (Pacientes, Solicitações, Guias, Sessões, Antecipações, Conciliações).
 *
 * Cada Import*Service tenta primeiro o casamento estrito de sempre (nomes de
 * coluna exatos do modelo baixável, sem acento/maiúscula) — rápido, grátis, e
 * já cobre quem preencheu o modelo. Só quando esse casamento não acha as
 * colunas obrigatórias é que entra aqui: a clínica mandou a própria planilha,
 * com os nomes de coluna dela. A IA olha só a LINHA DE CABEÇALHO (nunca os
 * dados das linhas) e diz a qual campo esperado cada coluna corresponde — o
 * resto do pipeline (validação linha a linha, casamento por chave, prévia
 * editável) continua exatamente igual, então um mapeamento errado da IA só
 * aparece como dado na coluna errada na prévia, revisável antes de confirmar.
 *
 * Sem conexão OpenAI configurada, ou qualquer falha na chamada, isto lança
 * RuntimeException — o chamador captura e cai de volta no erro de "faltam
 * colunas" que já existia, sem regressão pra quem não configurou IA.
 */
class ImportacaoHeaderMappingAiService
{
    private const CHAVE_PROMPT = 'mapear_cabecalho_importacao';

    /**
     * @param array<int, string> $cabecalhosBrutos textos literais das colunas achadas na planilha
     * @param array<string, string> $camposEsperados chave canônica => descrição curta
     * @return array<string, string> cabeçalho bruto (literal) => chave canônica
     */
    public function mapear(int $tenantId, array $cabecalhosBrutos, array $camposEsperados): array
    {
        // Autossuficiente: não depende de alguém já ter aberto a tela de
        // Prompts pra este tenant ganhar o registro padrão.
        AiPromptTemplate::garantirPadroes($tenantId);

        $prompt = AiPromptTemplate::query()
            ->where('tenant_id', $tenantId)
            ->where('chave', self::CHAVE_PROMPT)
            ->where('ativo', true)
            ->first();

        $openai = AiOpenaiSetting::query()
            ->where('tenant_id', $tenantId)
            ->where('ativo', true)
            ->first();

        if (! $prompt || ! $openai || blank($openai->api_key)) {
            throw new RuntimeException('Conexão OpenAI ou prompt de mapeamento de cabeçalho não configurados.');
        }

        $model = $prompt->model_id ?: ($openai->model_id ?: 'gpt-5.6-luna');

        $listaCabecalhos = implode("\n", array_map(fn ($h) => "- \"{$h}\"", $cabecalhosBrutos));
        $listaCampos = implode("\n", array_map(
            fn ($chave, $descricao) => "- {$chave}: {$descricao}",
            array_keys($camposEsperados),
            $camposEsperados,
        ));

        $textoUsuario = $prompt->user_prompt."\n\n"
            ."Cabeçalhos encontrados na planilha:\n{$listaCabecalhos}\n\n"
            ."Campos esperados pelo sistema:\n{$listaCampos}\n\n"
            .'Retorne somente JSON: um objeto onde cada chave é EXATAMENTE um dos cabeçalhos '
            .'encontrados na planilha (texto literal, sem alterar nada) e o valor é a chave do '
            .'campo esperado correspondente, ou null se nenhum campo combinar com aquela coluna. '
            .'Inclua todos os cabeçalhos encontrados, mesmo os que mapeiam para null.';

        try {
            $request = Http::withToken($openai->api_key)->acceptJson()->timeout(30);

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
                        'content' => [['type' => 'input_text', 'text' => $textoUsuario]],
                    ],
                ],
            ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Não foi possível conectar à OpenAI para mapear o cabeçalho.', previous: $exception);
        }

        if ($response->failed()) {
            throw new RuntimeException('A OpenAI recusou o mapeamento de cabeçalho.');
        }

        $mapeamento = $this->parseJson($this->extrairTexto($response->json()));

        // Só aceita entradas cujo cabeçalho bruto realmente veio da planilha e
        // cujo destino é um campo que o chamador de fato espera — a IA
        // inventar uma chave nova (alucinação) não deve virar coluna fantasma.
        $cabecalhosValidos = array_flip($cabecalhosBrutos);
        $camposValidos = array_flip(array_keys($camposEsperados));

        return array_filter(
            $mapeamento,
            fn ($destino, $cabecalho) => is_string($destino)
                && isset($cabecalhosValidos[$cabecalho])
                && isset($camposValidos[$destino]),
            ARRAY_FILTER_USE_BOTH,
        );
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

        return is_array($decoded) ? $decoded : [];
    }
}
