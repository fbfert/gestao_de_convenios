<?php

namespace App\Http\Controllers;

use App\Http\Resources\ConvenioResource;
use App\Http\Requests\UpsertConvenioRequest;
use App\Http\Requests\StoreConvenioRegraRequest;
use App\Models\ConvenioRegra;
use App\Services\ConvenioRegraService;
use App\Services\TabelaValoresService;
use App\Http\Requests\StoreTabelaValorRequest;
use App\Models\TabelaValor;
use Illuminate\Http\Request;
use App\Models\Convenio;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\JsonResponse;

class ConvenioController extends Controller
{
    public function __construct(private readonly ConvenioRegraService $regras, private readonly TabelaValoresService $valores) {}
    public function index(): AnonymousResourceCollection
    {
        return ConvenioResource::collection(
            Convenio::query()
                ->where('ativo', true)
                ->orderBy('nome')
                ->get()
        );
    }

    public function store(UpsertConvenioRequest $request): JsonResponse
    {
        $convenio = Convenio::query()->create([...$request->validated(), 'tenant_id' => $request->user()->tenant_id]);
        return (new ConvenioResource($convenio))->response()->setStatusCode(201);
    }

    public function update(UpsertConvenioRequest $request, Convenio $convenio): ConvenioResource
    {
        $convenio->update($request->validated());
        return new ConvenioResource($convenio);
    }

    public function regras(Convenio $convenio): AnonymousResourceCollection { return ConvenioRegra::query()->where('convenio_id', $convenio->id)->orderByDesc('vigente_desde')->get()->toResourceCollection(); }
    public function storeRegra(StoreConvenioRegraRequest $request, Convenio $convenio): JsonResponse { return response()->json(['data' => $this->regras->criar($convenio, $request->validated())], 201); }
    public function encerrarRegra(Request $request, Convenio $convenio, ConvenioRegra $regra): JsonResponse { abort_unless($regra->convenio_id === $convenio->id, 404); return response()->json(['data' => $this->regras->encerrar($regra, $request->input('vigente_ate'))]); }
    public function valores(Convenio $convenio): JsonResponse { return response()->json(['data' => TabelaValor::query()->where('convenio_id', $convenio->id)->orderByDesc('vigente_desde')->get()]); }
    public function storeValor(StoreTabelaValorRequest $request, Convenio $convenio): JsonResponse { return response()->json(['data' => $this->valores->criar($convenio, $request->validated())], 201); }
    public function encerrarValor(Request $request, Convenio $convenio, TabelaValor $valor): JsonResponse { abort_unless($valor->convenio_id === $convenio->id, 404); return response()->json(['data' => $this->valores->encerrar($valor, $request->input('vigente_ate'))]); }
}
