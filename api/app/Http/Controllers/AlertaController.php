<?php

namespace App\Http\Controllers;

use App\Models\Alerta;
use App\Models\AlertaRegra;
use App\Services\Alertas\ResolvedorDeRegras;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AlertaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'nivel' => ['nullable', Rule::in([Alerta::NIVEL_VERDE, Alerta::NIVEL_AMARELO, Alerta::NIVEL_VERMELHO])],
            'chave' => ['nullable', 'string'],
            'situacao' => ['nullable', Rule::in(['aberto', 'resolvido', 'silenciado'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Alerta::query()->with('reconhecidoPor');

        // Padrão é o que exige ação: sem isto a tela abre mostrando o histórico
        // inteiro, e o que importa some no meio.
        match ($filtros['situacao'] ?? 'aberto') {
            'resolvido' => $query->resolvido(),
            'silenciado' => $query->aberto()->whereNotNull('silenciado_ate')->where('silenciado_ate', '>', now()),
            default => $query->pendente(),
        };

        if (! empty($filtros['nivel'])) {
            $query->where('nivel', $filtros['nivel']);
        }

        if (! empty($filtros['chave'])) {
            $query->where('chave', $filtros['chave']);
        }

        $pagina = $query->orderByDesc('aberto_em')->paginate($filtros['per_page'] ?? 25);

        return response()->json([
            'data' => collect($pagina->items())->map(fn (Alerta $a) => $this->paraArray($a))->all(),
            'meta' => [
                'total' => $pagina->total(),
                'current_page' => $pagina->currentPage(),
                'last_page' => $pagina->lastPage(),
            ],
        ]);
    }

    public function reconhecer(Request $request, Alerta $alerta): JsonResponse
    {
        $alerta->forceFill([
            'reconhecido_por' => $request->user()->id,
            'reconhecido_em' => now(),
        ])->save();

        return response()->json(['data' => $this->paraArray($alerta->fresh('reconhecidoPor'))]);
    }

    public function silenciar(Request $request, Alerta $alerta): JsonResponse
    {
        $dados = $request->validate([
            'ate' => ['required', 'date', 'after:now'],
        ]);

        $alerta->forceFill(['silenciado_ate' => $dados['ate']])->save();

        return response()->json(['data' => $this->paraArray($alerta->fresh('reconhecidoPor'))]);
    }

    /** Regras do tenant, para a tela de configuração. */
    public function regras(): JsonResponse
    {
        $regras = AlertaRegra::query()->orderBy('chave')->get();

        return response()->json([
            'data' => $regras->map(fn (AlertaRegra $r) => [
                'id' => $r->id,
                'chave' => $r->chave,
                'ativo' => $r->ativo,
                'nivel_base' => $r->nivel_base,
                'limiar_amarelo' => $r->limiar_amarelo,
                'limiar_vermelho' => $r->limiar_vermelho,
                'critica' => $r->critica,
                // Regra sem implementação registrada continua listada, mas a
                // tela precisa poder avisar que ela não avalia nada.
                'implementada' => in_array($r->chave, ResolvedorDeRegras::chaves(), true),
            ])->all(),
        ]);
    }

    public function atualizarRegra(Request $request, AlertaRegra $alertaRegra): JsonResponse
    {
        $dados = $request->validate([
            'ativo' => ['required', 'boolean'],
            'nivel_base' => ['required', Rule::in([Alerta::NIVEL_VERDE, Alerta::NIVEL_AMARELO, Alerta::NIVEL_VERMELHO])],
            'limiar_amarelo' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'limiar_vermelho' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'critica' => ['required', 'boolean'],
        ]);

        $alertaRegra->fill($dados)->save();

        return response()->json(['data' => $alertaRegra->fresh()]);
    }

    private function paraArray(Alerta $alerta): array
    {
        return [
            'id' => $alerta->id,
            'chave' => $alerta->chave,
            'nivel' => $alerta->nivel,
            'titulo' => $alerta->titulo,
            'descricao' => $alerta->descricao,
            'entidade' => $alerta->entidade,
            'entidade_id' => $alerta->entidade_id,
            'dados' => $alerta->dados,
            'aberto_em' => $alerta->aberto_em?->toIso8601String(),
            'resolvido_em' => $alerta->resolvido_em?->toIso8601String(),
            'reconhecido_por' => $alerta->reconhecidoPor?->name,
            'reconhecido_em' => $alerta->reconhecido_em?->toIso8601String(),
            'silenciado_ate' => $alerta->silenciado_ate?->toIso8601String(),
        ];
    }
}
