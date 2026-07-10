<?php

namespace App\Http\Controllers;

use App\Http\Resources\ConvenioResource;
use App\Models\Convenio;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ConvenioController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return ConvenioResource::collection(
            Convenio::query()
                ->where('ativo', true)
                ->orderBy('nome')
                ->get()
        );
    }
}
