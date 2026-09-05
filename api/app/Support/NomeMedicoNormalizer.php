<?php

namespace App\Support;

use Illuminate\Support\Str;

class NomeMedicoNormalizer
{
    private const CONECTORES = ['DE', 'DA', 'DO', 'DOS', 'DAS', 'E'];

    /**
     * Remove o prefixo "Dr./Dr/Dra./Dra" (e variações de caixa) do início do
     * nome. O portal da Unimed cadastra os cooperados sem esse prefixo, e ele
     * também não deve aparecer no cadastro de médico nem no nome lido de um
     * pedido médico — mantê-lo só atrapalha a busca por nome.
     *
     * Exige espaço (ou ponto seguido de espaço) depois do prefixo pra não
     * cortar nomes que por acaso comecem com essas letras (ex. "Drica").
     */
    public static function semPrefixo(string $nome): string
    {
        $nome = trim($nome);
        $semPrefixo = preg_replace('/^(dr|dra)\.?\s+/iu', '', $nome);

        return trim($semPrefixo ?? $nome);
    }

    /**
     * Similaridade 0-100 entre um nome lido (possivelmente abreviado, ex.
     * "Edison T. F. A. Westarb") e um nome candidato completo, tolerando
     * iniciais no meio do nome.
     *
     * Mesma lógica do worker-unimed (`compararNomes` em
     * worker-unimed/src/portal.js) — mantenha as duas em sincronia.
     *
     * Primeiro e último token (nome e sobrenome) precisam bater exatamente;
     * sem isso o score é 0 — nunca um "quase certo" com sobrenome diferente.
     * Tokens do meio contam como batidos se forem iguais ou uma inicial de
     * um token do candidato (ignorando conectores como "de", "da", "dos").
     */
    public static function similaridadeAproximada(string $nomeLido, string $nomeCandidato): float
    {
        $tokensLido = self::tokenizar($nomeLido);
        $tokensCandidato = self::tokenizar($nomeCandidato);

        if ($tokensLido === [] || $tokensCandidato === []) {
            return 0.0;
        }

        $primeiroOk = $tokensLido[0] === $tokensCandidato[0];
        $ultimoOk = end($tokensLido) === end($tokensCandidato);

        if (! $primeiroOk || ! $ultimoOk) {
            return 0.0;
        }

        $meioLido = array_slice($tokensLido, 1, -1);
        $meioCandidato = array_values(array_diff(array_slice($tokensCandidato, 1, -1), self::CONECTORES));

        if ($meioLido === []) {
            return 100.0;
        }

        $indice = 0;
        $casados = 0;

        foreach ($meioLido as $token) {
            $encontrado = null;

            for ($i = $indice; $i < count($meioCandidato); $i++) {
                $candidatoToken = $meioCandidato[$i];
                $iniciaCom = mb_strlen($token) === 1 && str_starts_with($candidatoToken, $token);

                if ($candidatoToken === $token || $iniciaCom) {
                    $encontrado = $i;
                    break;
                }
            }

            if ($encontrado !== null) {
                $casados++;
                $indice = $encontrado + 1;
            }
        }

        return round(60 + (40 * $casados / count($meioLido)), 2);
    }

    /** @return string[] */
    private static function tokenizar(string $nome): array
    {
        $upper = mb_strtoupper(trim(Str::ascii(self::semPrefixo($nome))));

        if ($upper === '') {
            return [];
        }

        $tokens = preg_split('/\s+/u', $upper) ?: [];

        return array_values(array_filter(array_map(
            fn (string $token) => rtrim($token, '.'),
            $tokens,
        )));
    }
}
