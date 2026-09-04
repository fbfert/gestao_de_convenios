<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Paginação opcional das listagens de referência (pacientes, médicos,
 * profissionais).
 *
 * Sem `page` na query string, mantém o comportamento de sempre e devolve a
 * tabela inteira — é o que as telas de CRUD e os selects existentes esperam.
 * Com `page`, pagina: é o que o modal de busca usa, para não trazer o tenant
 * inteiro numa lista que só mostra uma tela por vez.
 */
class PaginaListagem
{
    private const POR_PAGINA_PADRAO = 20;

    private const POR_PAGINA_MAXIMO = 50;

    public static function aplicar($query, Request $request)
    {
        if (! $request->filled('page')) {
            return $query->get();
        }

        $porPagina = min(max($request->integer('per_page', self::POR_PAGINA_PADRAO), 1), self::POR_PAGINA_MAXIMO);

        return $query->paginate($porPagina)->withQueryString();
    }
}
