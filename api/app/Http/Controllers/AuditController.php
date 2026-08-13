<?php

namespace App\Http\Controllers;

use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Models\ConfiguracaoGlobal;
use App\Support\AuditoriaCatalogo;
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
        $acoes = AuditLog::query()->distinct()->orderBy('acao')->pluck('acao');

        return response()->json([
            'data' => [
                // Só entidades e ações que de fato ocorreram nesta clínica: o
                // filtro não oferece recorte que devolveria vazio.
                'entidades' => AuditLog::query()
                    ->distinct()
                    ->orderBy('entidade')
                    ->pluck('entidade')
                    ->map(fn ($entidade) => [
                        'valor' => $entidade,
                        'rotulo' => AuditoriaCatalogo::rotuloEntidade($entidade),
                    ])
                    ->sortBy('rotulo')
                    ->values(),
                'acoes' => $acoes
                    ->map(fn ($acao) => [
                        'valor' => $acao,
                        'rotulo' => AuditoriaCatalogo::rotuloAcao($acao),
                        'tipo' => AuditoriaCatalogo::tipoDe($acao),
                    ])
                    ->sortBy('rotulo')
                    ->values(),
                'tipos' => collect(AuditoriaCatalogo::TIPOS)
                    ->map(fn ($rotulo, $valor) => ['valor' => $valor, 'rotulo' => $rotulo])
                    ->values(),
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
            fputcsv($saida, ['Data', 'Usuário', 'Tipo', 'Ação', 'Entidade', 'Registro', 'Detalhes', 'IP']);

            $consulta->chunk(500, function ($registros) use ($saida) {
                foreach ($registros as $registro) {
                    fputcsv($saida, [
                        $registro->created_at?->format('d/m/Y H:i:s'),
                        $registro->user?->name ?? 'Sistema',
                        AuditoriaCatalogo::TIPOS[AuditoriaCatalogo::tipoDe($registro->acao)] ?? '',
                        AuditoriaCatalogo::rotuloAcao($registro->acao),
                        AuditoriaCatalogo::rotuloEntidade($registro->entidade),
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
            ->when($request->filled('usuario'), function ($q) use ($request) {
                // Busca pelo nome da pessoa. O LIKE cai sobre `users`, que é
                // uma tabela pequena, e não sobre a trilha inteira.
                $termo = $request->string('usuario')->trim()->toString();

                $q->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$termo}%"));
            })
            ->when($request->filled('autor'), function ($q) use ($request) {
                // "sistema" não é um usuário: é a ausência de um. É assim que
                // se isola o que job, worker e expurgo fizeram sozinhos.
                $request->string('autor')->toString() === 'sistema'
                    ? $q->whereNull('user_id')
                    : $q->whereNotNull('user_id');
            })
            ->when($request->filled('tipo'), function ($q) use ($request) {
                // O tipo vira lista de ações em PHP, em vez de padrões
                // repetidos em SQL: assim o que o seletor mostra e o que a
                // consulta filtra saem da mesma regra.
                $tipo = $request->string('tipo')->toString();

                $acoes = AuditLog::query()
                    ->distinct()
                    ->pluck('acao')
                    ->filter(fn ($acao) => AuditoriaCatalogo::tipoDe($acao) === $tipo)
                    ->values()
                    ->all();

                $q->whereIn('acao', $acoes ?: ['']);
            })
            ->when($request->filled('entidade'), fn ($q) => $q->where('entidade', $request->string('entidade')->toString()))
            ->when($request->filled('acao'), fn ($q) => $q->where('acao', $request->string('acao')->toString()));
    }
}
