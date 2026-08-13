<?php

namespace App\Http\Controllers;

use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Models\ConfiguracaoGlobal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $padrao = ConfiguracaoGlobal::doTenant((int) $request->user()->tenant_id)->itens_por_pagina ?? 25;
        $porPagina = (int) $request->integer('por_pagina', $padrao);

        return AuditLogResource::collection(
            $this->filtrada($request)
                ->with('user')
                ->latest('created_at')
                ->latest('id')
                ->paginate(min(max($porPagina, 5), 200))
                ->withQueryString()
        );
    }

    /**
     * Valores que existem de fato na trilha do tenant, para o filtro da tela
     * não oferecer entidade ou ação que nunca aconteceu aqui.
     */
    public function opcoes(): JsonResponse
    {
        return response()->json([
            'data' => [
                'entidades' => AuditLog::query()->distinct()->orderBy('entidade')->pluck('entidade'),
                'acoes' => AuditLog::query()->distinct()->orderBy('acao')->pluck('acao'),
                // Os autores saem da própria trilha, e não de GET /usuarios:
                // aquela rota exige `usuarios.manage`, que quem audita pode não
                // ter — e o 403 viraria evento só por abrir a tela. De quebra,
                // a lista só oferece quem de fato aparece na trilha.
                'usuarios' => AuditLog::query()
                    ->join('users', 'users.id', '=', 'audit_logs.user_id')
                    ->distinct()
                    ->orderBy('users.name')
                    ->get(['users.id', 'users.name'])
                    ->map(fn ($linha) => ['id' => $linha->id, 'nome' => $linha->name]),
            ],
        ]);
    }

    /**
     * Exporta exatamente o resultado dos filtros aplicados.
     *
     * Em streaming, e não montando tudo na memória: o recorte pode pegar um ano
     * inteiro, porque é só depois disso que o expurgo age.
     */
    public function exportar(Request $request): StreamedResponse
    {
        $consulta = $this->filtrada($request)->with('user')->latest('created_at')->latest('id');
        $nome = 'auditoria-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($consulta) {
            $saida = fopen('php://output', 'w');

            // BOM para o Excel em pt-BR abrir os acentos corretamente.
            fwrite($saida, "\xEF\xBB\xBF");
            fputcsv($saida, ['Data', 'Usuário', 'Ação', 'Entidade', 'Registro', 'Detalhes', 'IP']);

            $consulta->chunk(500, function ($registros) use ($saida) {
                foreach ($registros as $registro) {
                    fputcsv($saida, [
                        $registro->created_at?->format('d/m/Y H:i:s'),
                        $registro->user?->name ?? 'Sistema',
                        $registro->acao,
                        $registro->entidade,
                        $registro->entidade_id,
                        // O payload já vem sem valor de campo sensível: a
                        // censura acontece na escrita, não aqui.
                        json_encode($registro->payload, JSON_UNESCAPED_UNICODE),
                        $registro->ip,
                    ]);
                }
            });

            fclose($saida);
        }, $nome, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function filtrada(Request $request): Builder
    {
        return AuditLog::query()
            ->when($request->filled('de'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('de')))
            ->when($request->filled('ate'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('ate')))
            ->when($request->filled('usuario_id'), function ($q) use ($request) {
                // "sistema" não é um usuário: é a ausência de um.
                $request->string('usuario_id')->toString() === 'sistema'
                    ? $q->whereNull('user_id')
                    : $q->where('user_id', $request->integer('usuario_id'));
            })
            ->when($request->filled('entidade'), fn ($q) => $q->where('entidade', $request->string('entidade')->toString()))
            ->when($request->filled('acao'), fn ($q) => $q->where('acao', $request->string('acao')->toString()));
    }
}
