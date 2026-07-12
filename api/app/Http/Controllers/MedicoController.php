<?php

namespace App\Http\Controllers;

use App\Http\Resources\MedicoResource;
use App\Models\Medico;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MedicoController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $busca = trim((string) $request->string('busca'));

        return MedicoResource::collection(
            Medico::query()
                ->where('ativo', true)
                ->when($busca !== '', function ($query) use ($busca) {
                    $query->where(function ($nested) use ($busca) {
                        $nested->where('nome', 'like', "%{$busca}%")
                            ->orWhere('crm', 'like', "%{$busca}%")
                            ->orWhere('especialidade_medica', 'like', "%{$busca}%");
                    });
                })
                ->orderBy('nome')
                ->get()
        );
    }
}
