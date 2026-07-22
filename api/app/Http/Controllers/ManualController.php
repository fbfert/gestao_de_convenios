<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateManualRequest;
use App\Http\Resources\ManualResource;
use App\Models\Manual;
use Illuminate\Http\Request;

class ManualController extends Controller
{
    private const TIPOS_PADRAO = [
        'manual' => 'default.html',
        'mapa-mental' => 'mapa-mental.html',
    ];

    public function show(Request $request, string $tipo = 'manual'): ManualResource
    {
        $manual = Manual::query()->where('tipo', $tipo)->with('atualizadoPor')->first();

        if (! $manual) {
            $manual = Manual::query()->create([
                'tenant_id' => $request->user()->tenant_id,
                'tipo' => $tipo,
                'conteudo_html' => file_get_contents(resource_path('manual/'.self::TIPOS_PADRAO[$tipo])),
            ]);
            $manual->wasRecentlyCreated = false;
        }

        return new ManualResource($manual);
    }

    public function update(UpdateManualRequest $request, string $tipo = 'manual'): ManualResource
    {
        $manual = Manual::query()->where('tipo', $tipo)->first()
            ?? new Manual(['tenant_id' => $request->user()->tenant_id, 'tipo' => $tipo]);

        $manual->conteudo_html = $request->validated()['conteudo_html'];
        $manual->atualizado_por = $request->user()->id;
        $manual->save();
        $manual->wasRecentlyCreated = false;

        return new ManualResource($manual->load('atualizadoPor'));
    }
}
