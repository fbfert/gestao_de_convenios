<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAiSettingsRequest;
use App\Http\Resources\AiSettingsResource;
use App\Models\AiOpenaiSetting;
use App\Models\AiPromptTemplate;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class AiSettingsController extends Controller
{
    public function show(): AiSettingsResource
    {
        $tenantId = (int) request()->user()->tenant_id;

        $this->seedDefaultPrompts($tenantId);

        return new AiSettingsResource([
            'openai' => AiOpenaiSetting::query()->where('tenant_id', $tenantId)->first(),
            'prompts' => AiPromptTemplate::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('nome')
                ->get(),
        ]);
    }

    public function update(UpdateAiSettingsRequest $request): AiSettingsResource
    {
        $tenantId = (int) $request->user()->tenant_id;
        $openaiPayload = $request->validated('openai');
        $promptsPayload = $request->validated('prompts');

        $openai = AiOpenaiSetting::query()->firstOrNew(['tenant_id' => $tenantId]);
        $apiKey = Arr::pull($openaiPayload, 'api_key');
        $openai->fill($openaiPayload);

        if (filled($apiKey)) {
            $openai->api_key = $apiKey;
        }

        $openai->save();

        foreach ($promptsPayload as $promptPayload) {
            AiPromptTemplate::query()->updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'chave' => $promptPayload['chave'],
                ],
                $promptPayload
            );
        }

        return $this->show();
    }

    public function models()
    {
        $tenantId = (int) request()->user()->tenant_id;
        $openai = AiOpenaiSetting::query()
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $openai || blank($openai->api_key)) {
            throw ValidationException::withMessages([
                'openai' => 'Configure a API key da OpenAI antes de listar modelos.',
            ]);
        }

        try {
            $request = Http::withToken($openai->api_key)
                ->acceptJson()
                ->timeout(15);

            if (filled($openai->organization_id)) {
                $request = $request->withHeaders(['OpenAI-Organization' => $openai->organization_id]);
            }

            if (filled($openai->project_id)) {
                $request = $request->withHeaders(['OpenAI-Project' => $openai->project_id]);
            }

            $response = $request->get(rtrim($openai->base_url, '/').'/models');
        } catch (ConnectionException) {
            throw ValidationException::withMessages([
                'openai' => 'Não foi possível conectar à OpenAI para listar modelos.',
            ]);
        }

        if ($response->failed()) {
            throw ValidationException::withMessages([
                'openai' => 'A OpenAI recusou a listagem de modelos. Verifique a API key e o projeto.',
            ]);
        }

        $models = collect($response->json('data', []))
            ->map(fn ($model) => [
                'id' => $model['id'] ?? '',
                'owned_by' => $model['owned_by'] ?? null,
            ])
            ->filter(fn ($model) => $model['id'] !== '')
            ->sortBy('id')
            ->values();

        return response()->json(['data' => $models]);
    }

    private function seedDefaultPrompts(int $tenantId): void
    {
        $defaults = [
            [
                'chave' => 'ler_solicitacao_medica',
                'nome' => 'Ler solicitação médica',
                'descricao' => 'Extrai dados de solicitações médicas para criar Solicitações.',
                'model_id' => null,
                'system_prompt' => 'Você extrai dados de documentos médicos para um sistema de convênios. Responda somente em JSON válido.',
                'user_prompt' => 'Leia a solicitação médica escaneada e retorne paciente, médico, convênio, especialidade, data solicitada e observações relevantes.',
                'ativo' => true,
            ],
            [
                'chave' => 'ler_sessoes_escaneadas',
                'nome' => 'Ler sessões escaneadas',
                'descricao' => 'Extrai sessões escaneadas para criar lançamentos no banco.',
                'model_id' => null,
                'system_prompt' => 'Você extrai registros de sessões terapêuticas de documentos escaneados. Responda somente em JSON válido.',
                'user_prompt' => 'Leia o registro de sessões escaneado e retorne data, hora início, hora fim, acompanhante, profissional e resumo das atividades de cada sessão.',
                'ativo' => true,
            ],
        ];

        foreach ($defaults as $default) {
            AiPromptTemplate::query()->firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'chave' => $default['chave'],
                ],
                $default
            );
        }
    }
}
