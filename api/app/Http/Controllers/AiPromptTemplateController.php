<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAiPromptTemplateRequest;
use App\Http\Requests\UpdateAiPromptTemplateRequest;
use App\Models\AiPromptTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * CRUD dos prompts operacionais. A conexao OpenAI continua em
 * AiSettingsController: sao coisas de ciclo de vida diferente — a credencial e
 * uma so por tenant, os prompts sao muitos e mudam com o uso.
 */
class AiPromptTemplateController extends Controller
{
    public function index(): JsonResponse
    {
        AiPromptTemplate::garantirPadroes((int) request()->user()->tenant_id);

        $prompts = AiPromptTemplate::query()
            ->orderBy('nome')
            ->get()
            ->map(fn (AiPromptTemplate $prompt) => $this->paraArray($prompt));

        return response()->json(['data' => $prompts]);
    }

    public function store(StoreAiPromptTemplateRequest $request): JsonResponse
    {
        $prompt = AiPromptTemplate::query()->create($request->validated());

        return response()->json(['data' => $this->paraArray($prompt)], 201);
    }

    public function update(
        UpdateAiPromptTemplateRequest $request,
        AiPromptTemplate $aiPromptTemplate,
    ): JsonResponse {
        $aiPromptTemplate->update($request->validated());

        return response()->json(['data' => $this->paraArray($aiPromptTemplate->refresh())]);
    }

    public function destroy(AiPromptTemplate $aiPromptTemplate): JsonResponse
    {
        if ($aiPromptTemplate->ehDeSistema()) {
            // 422 e nao 403: nao e falta de permissao, e um pedido invalido.
            // Nenhum papel pode apagar um prompt que o codigo procura por chave.
            throw ValidationException::withMessages([
                'chave' => 'Este prompt é usado pelo sistema e não pode ser excluído. Se não quiser usá-lo, desative-o.',
            ]);
        }

        $aiPromptTemplate->delete();

        return response()->json(null, 204);
    }

    private function paraArray(AiPromptTemplate $prompt): array
    {
        return [
            'id' => $prompt->id,
            'chave' => $prompt->chave,
            'nome' => $prompt->nome,
            'descricao' => $prompt->descricao,
            'model_id' => $prompt->model_id,
            'system_prompt' => $prompt->system_prompt,
            'user_prompt' => $prompt->user_prompt,
            'ativo' => $prompt->ativo,
            // A tela usa isto para travar a chave e esconder o botao de excluir.
            'sistema' => $prompt->ehDeSistema(),
        ];
    }
}
