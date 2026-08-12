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

        AiPromptTemplate::garantirPadroes($tenantId);

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
        // Só a conexão OpenAI. Os prompts têm CRUD próprio em
        // AiPromptTemplateController desde que ganharam chave livre.
        $tenantId = (int) $request->user()->tenant_id;
        $openaiPayload = $request->validated('openai');

        $openai = AiOpenaiSetting::query()->firstOrNew(['tenant_id' => $tenantId]);
        $apiKey = Arr::pull($openaiPayload, 'api_key');
        $openai->fill($openaiPayload);

        if (filled($apiKey)) {
            $openai->api_key = $apiKey;
        }

        $openai->save();

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
            // A mensagem da OpenAI vai crua para a tela. A versao anterior
            // dizia so "verifique a API key e o projeto", o que escondia o
            // motivo real: um `Invalid project ID 'gescon'` ficava
            // indistinguivel de uma chave revogada ou de cota estourada.
            $erro = $response->json('error') ?? [];
            $detalhe = $erro['message'] ?? $response->body();

            throw ValidationException::withMessages([
                'openai' => 'A OpenAI recusou a listagem de modelos (HTTP '
                    .$response->status().'): '.$detalhe,
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
}
