<?php

namespace App\Http\Controllers;

use App\Http\Resources\EspecialidadeResource;
use App\Models\Especialidade;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EspecialidadeController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return EspecialidadeResource::collection(
            Especialidade::query()
                ->where('ativo', true)
                ->orderBy('nome')
                ->get()
        );
    }
}
