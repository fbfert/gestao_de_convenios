<?php

namespace App\Services\Relatorios;

use InvalidArgumentException;

/**
 * As quatro abas, a permissão de cada uma e o serviço que a calcula.
 *
 * O mapa vive num lugar só porque três pontos precisam concordar: a rota (que
 * declara o `permission:`), o controller (que escolhe o serviço) e a tela (que
 * decide quais abas montar). Enquanto isso fosse string solta em cada um, uma
 * aba nova entraria no controller e ficaria sem permissão na rota — que é
 * exatamente o defeito que `ExigeAutorizacaoDeclarada` existe para impedir.
 */
final class RelatorioAba
{
    public const OPERACAO = 'operacao';

    public const FINANCEIRO = 'financeiro';

    public const AUTOMACOES = 'automacoes';

    public const USO = 'uso';

    public const TODAS = [self::OPERACAO, self::FINANCEIRO, self::AUTOMACOES, self::USO];

    private const SERVICOS = [
        self::OPERACAO => RelatorioOperacaoService::class,
        self::FINANCEIRO => RelatorioFinanceiroService::class,
        self::AUTOMACOES => RelatorioAutomacoesService::class,
        self::USO => RelatorioUsoService::class,
    ];

    public static function permissaoDe(string $aba): string
    {
        self::exigirConhecida($aba);

        return "relatorios.{$aba}";
    }

    /** @return string[] */
    public static function permissoes(): array
    {
        return array_map(self::permissaoDe(...), self::TODAS);
    }

    public static function servico(string $aba): RelatorioService
    {
        self::exigirConhecida($aba);

        return app(self::SERVICOS[$aba]);
    }

    private static function exigirConhecida(string $aba): void
    {
        if (! in_array($aba, self::TODAS, true)) {
            throw new InvalidArgumentException("Aba de relatório desconhecida: {$aba}.");
        }
    }
}
