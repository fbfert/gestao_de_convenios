<?php

namespace App\Support;

use Closure;

/**
 * Ordenação de listagem pedida pelo cabeçalho da tabela.
 *
 * A coluna chega pela query string e vai direto para o `ORDER BY`, então a
 * lista de colunas aceitas é fechada e declarada por listagem: qualquer valor
 * fora dela cai no padrão, em vez de virar SQL.
 *
 * Cada entrada do mapa é o nome da coluna real ou uma função, para o caso em
 * que ordenar exige um `join` — ordenar guias por paciente, por exemplo, é
 * ordenar por `pacientes.nome`, não por `paciente_id`, que não diz nada a quem
 * lê a tabela.
 *
 * O desempate por uma coluna estável importa mais do que parece: sem ele, duas
 * páginas seguidas podem repetir ou pular registros que empatam na coluna
 * escolhida.
 */
class OrdenaListagem
{
    /**
     * @param  array<string, string|Closure>  $permitidas  chave da tela => coluna real ou closure
     * @param  array{ordenar_por?: string|null, direcao?: string|null}  $filtros
     */
    public static function aplicar(
        $query,
        array $filtros,
        array $permitidas,
        string $padrao,
        string $direcaoPadrao = 'asc',
        ?string $desempate = null,
    ): void {
        $coluna = (string) ($filtros['ordenar_por'] ?? '');
        $pedida = $permitidas[$coluna] ?? null;
        $direcao = ($filtros['direcao'] ?? '') === 'desc' ? 'desc' : 'asc';

        if ($pedida === null) {
            $query->orderBy($padrao, $direcaoPadrao);

            return;
        }

        if ($pedida instanceof Closure) {
            $pedida($query, $direcao);
        } else {
            $query->orderBy($pedida, $direcao);
        }

        if ($desempate) {
            $query->orderBy($desempate);
        }
    }
}
