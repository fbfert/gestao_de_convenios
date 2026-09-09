<?php

namespace App\Http\Controllers;

use App\Models\NovidadeLeitura;
use App\Services\NovidadeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class NovidadeController extends Controller
{
    public function index(Request $request, NovidadeService $novidades): JsonResponse
    {
        $limite = $request->integer('limit');
        $lista = $limite > 0 ? $novidades->ultimas($limite) : $novidades->todas();

        $lidas = NovidadeLeitura::query()
            ->where('user_id', $request->user()->id)
            ->pluck('slug')
            ->all();

        $comLeitura = array_map(fn (array $n) => $n + ['lida' => in_array($n['slug'], $lidas, true)], $lista);

        return response()->json([
            'data' => $comLeitura,
            'meta' => [
                // Contado sobre TODAS, e nao sobre a pagina: o card pede cinco,
                // mas "2 nao lidas" precisa contar o que existe.
                'nao_lidas' => count(array_filter(
                    $novidades->todas(),
                    fn (array $n) => ! in_array($n['slug'], $lidas, true),
                )),
            ],
        ]);
    }

    public function marcarLida(Request $request, string $slug, NovidadeService $novidades): JsonResponse
    {
        $existe = collect($novidades->todas())->contains(fn (array $n) => $n['slug'] === $slug);

        if (! $existe) {
            throw new NotFoundHttpException('Novidade não encontrada.');
        }

        // `firstOrCreate` sobre o par único: marcar de novo não duplica.
        NovidadeLeitura::query()->firstOrCreate(
            ['user_id' => $request->user()->id, 'slug' => $slug],
            ['lido_em' => now()],
        );

        return response()->json(['data' => ['slug' => $slug, 'lida' => true]]);
    }
}
