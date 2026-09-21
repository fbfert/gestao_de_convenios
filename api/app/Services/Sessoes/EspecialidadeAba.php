<?php

namespace App\Services\Sessoes;

/**
 * Decide se uma especialidade é terapia ABA olhando o nome dela.
 *
 * O limite diário de sessões depende disso (8/dia para ABA, 1/dia para as
 * demais) e não existe campo no cadastro dizendo qual é qual — a clínica
 * distingue pelo nome ("Terapia ABA", "Psicologia ABA", "Fonoaudiologia
 * ABA"). Ler o nome é, por isso, a única fonte disponível hoje.
 *
 * O risco conhecido é grafia: "Psicologia" e "Psicologia ABA" são
 * especialidades diferentes com limites diferentes, então errar aqui bloqueia
 * lançamento legítimo (ou libera oito onde cabe uma). Por isso a comparação
 * é tolerante ao que varia sem mudar o sentido — caixa, acento e os pontos de
 * "A.B.A." — e intolerante ao que mudaria: "ABA" precisa ser palavra inteira,
 * senão "Abagail" entraria.
 */
final class EspecialidadeAba
{
    public static function ehAba(?string $nome): bool
    {
        if ($nome === null || trim($nome) === '') {
            return false;
        }

        foreach (self::palavras($nome) as $palavra) {
            if ($palavra === 'ABA') {
                return true;
            }
        }

        return false;
    }

    /**
     * Quebra o nome em palavras comparáveis: sem acento, em caixa alta e sem
     * os pontos que "A.B.A." usa — que viram uma palavra só, "ABA", em vez de
     * três letras soltas.
     *
     * @return array<int, string>
     */
    private static function palavras(string $nome): array
    {
        $semAcento = self::semAcento($nome);
        $semPontoInterno = preg_replace('/(?<=\p{L})\.(?=\p{L}|\b)/u', '', $semAcento) ?? $semAcento;

        $partes = preg_split('/[^\p{L}\p{N}]+/u', $semPontoInterno, -1, PREG_SPLIT_NO_EMPTY);

        return array_map(
            static fn (string $parte): string => mb_strtoupper($parte, 'UTF-8'),
            $partes ?: [],
        );
    }

    private static function semAcento(string $valor): string
    {
        $convertido = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $valor);

        // iconv falha em alguns locales; sem ele o nome segue como veio, o que
        // só custa não casar um acento — nunca um falso positivo.
        return $convertido === false ? $valor : $convertido;
    }
}
