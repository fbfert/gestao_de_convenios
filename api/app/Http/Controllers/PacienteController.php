<?php

namespace App\Http\Controllers;

use App\Http\Resources\PacienteResource;
use App\Models\Paciente;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PacienteController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $busca = trim((string) $request->string('busca'));
        $convenioId = $request->integer('convenio_id');

        return PacienteResource::collection(
            Paciente::query()
                ->with('convenio')
                ->when($convenioId, fn ($query) => $query->where('convenio_id', $convenioId))
                ->when($busca !== '', function ($query) use ($busca) {
                    $query->where(function ($nested) use ($busca) {
                        $nested->where('nome', 'like', "%{$busca}%")
                            ->orWhere('carteirinha', 'like', "%{$busca}%");
                    });
                })
                ->orderBy('nome')
                ->get()
        );
    }
}
