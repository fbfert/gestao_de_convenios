<?php

namespace App\Http\Controllers;

use App\Models\AlertaDestinatario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * Destinatarios de alerta DO TENANT.
 *
 * Os destinatarios globais (o suporte da Xiax) nao passam por aqui de proposito:
 * sao infraestrutura da Xiax, e nao configuracao da clinica.
 */
class AlertaDestinatarioController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => AlertaDestinatario::query()->orderBy('email')->get()->map(
                fn (AlertaDestinatario $d) => $this->paraArray($d)
            )->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $dados = $this->validar($request);
        $dados['tenant_id'] = (int) $request->user()->tenant_id;

        $destinatario = AlertaDestinatario::query()->create($dados);

        return response()->json(['data' => $this->paraArray($destinatario)], 201);
    }

    public function update(Request $request, AlertaDestinatario $alertaDestinatario): JsonResponse
    {
        $alertaDestinatario->fill($this->validar($request, $alertaDestinatario->id));

        // Reativar zera o contador: senão o destinatário voltaria já na beira do
        // limite e seria desativado na primeira falha seguinte.
        if ($alertaDestinatario->isDirty('ativo') && $alertaDestinatario->ativo) {
            $alertaDestinatario->falhas_consecutivas = 0;
        }

        $alertaDestinatario->save();

        return response()->json(['data' => $this->paraArray($alertaDestinatario)]);
    }

    public function destroy(AlertaDestinatario $alertaDestinatario): Response
    {
        $alertaDestinatario->delete();

        return response()->noContent();
    }

    private function validar(Request $request, ?int $ignorar = null): array
    {
        return $request->validate([
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('alerta_destinatarios')
                    ->where('tenant_id', (int) $request->user()->tenant_id)
                    ->ignore($ignorar),
            ],
            'nome' => ['nullable', 'string', 'max:255'],
            'niveis' => ['required', 'array', 'min:1'],
            'niveis.*' => [Rule::in(['verde', 'amarelo', 'vermelho'])],
            // Nulo significa TODAS: é o padrão mais útil para quem acabou de
            // cadastrar e ainda não sabe quais chaves existem.
            'chaves' => ['nullable', 'array'],
            'chaves.*' => ['string', 'max:100'],
            'canal' => ['required', Rule::in(['digest', 'imediato', 'ambos'])],
            'horario_digest' => ['required', 'integer', 'min:0', 'max:23'],
            'ativo' => ['required', 'boolean'],
        ]);
    }

    private function paraArray(AlertaDestinatario $destinatario): array
    {
        return [
            'id' => $destinatario->id,
            'email' => $destinatario->email,
            'nome' => $destinatario->nome,
            'niveis' => $destinatario->niveis,
            'chaves' => $destinatario->chaves,
            'canal' => $destinatario->canal,
            'horario_digest' => $destinatario->horario_digest,
            'ativo' => $destinatario->ativo,
            'verificado_em' => $destinatario->verificado_em?->toIso8601String(),
            'falhas_consecutivas' => $destinatario->falhas_consecutivas,
        ];
    }
}
