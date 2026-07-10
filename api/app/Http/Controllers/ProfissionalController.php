<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProfissionalResource;
use App\Models\Profissional;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProfissionalController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $busca = trim((string) $request->string('busca'));
        $especialidadeId = $request->integer('especialidade_id');

        return ProfissionalResource::collection(
            Profissional::query()
                ->with('especialidade')
                ->when($especialidadeId, fn ($query) => $query->where('especialidade_id', $especialidadeId))
                ->when($busca !== '', function ($query) use ($busca) {
                    $query->where('nome', 'like', "%{$busca}%");
                })
                ->orderBy('nome')
                ->get()
        );
    }
}
