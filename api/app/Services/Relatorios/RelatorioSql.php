<?php

namespace App\Services\Relatorios;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * As expressões SQL que mudam de fabricante.
 *
 * Este arquivo existe por um defeito específico, e é o mais fácil de deixar
 * passar nesta change: a suíte roda em SQLite e a produção em MariaDB. Um
 * `strftime` cravado no `selectRaw` faz os testes passarem e a produção
 * quebrar; um `DATE_FORMAT` faz o contrário — que é pior, porque ninguém vê até
 * alguém abrir a tela.
 *
 * Só truncamento de data e hora do dia moram aqui. Agregação (`SUM`, `COUNT`,
 * `AVG`) é ANSI e vale nos dois sem tradução.
 *
 * Todas as expressões devolvem TEXTO no formato `YYYY-MM-DD` (ou inteiro, no
 * caso da hora), e não data nativa: o ponto da série vai para JSON como string,
 * e converter de um lado só evita que SQLite e MariaDB divirjam no formato.
 */
final class RelatorioSql
{
    /**
     * Começo do balde a que a coluna pertence: o próprio dia, a segunda-feira
     * da semana, ou o dia 1º do mês.
     *
     * Semana começa na SEGUNDA, como a semana ISO e como o calendário que a
     * clínica usa. Nem `strftime('%W')` nem `WEEK()` servem aqui: os dois
     * devolvem o NÚMERO da semana, que não ordena entre anos e não dá para
     * exibir como data no eixo.
     */
    public static function truncarData(string $coluna, string $granularidade, ?ConnectionInterface $conexao = null): string
    {
        $driver = self::driver($conexao);

        return match ($driver) {
            'sqlite' => match ($granularidade) {
                RelatorioPeriodo::DIA => "strftime('%Y-%m-%d', {$coluna})",
                // 'weekday 0' anda até o próximo domingo (ou fica, se já for
                // domingo); seis dias atrás disso é a segunda-feira da semana.
                RelatorioPeriodo::SEMANA => "date({$coluna}, 'weekday 0', '-6 days')",
                RelatorioPeriodo::MES => "strftime('%Y-%m-01', {$coluna})",
                default => throw self::granularidadeDesconhecida($granularidade),
            },
            'mysql', 'mariadb' => match ($granularidade) {
                RelatorioPeriodo::DIA => "DATE_FORMAT({$coluna}, '%Y-%m-%d')",
                // WEEKDAY() é 0 na segunda — ao contrário de DAYOFWEEK(), que é
                // 1 no domingo. Subtrair WEEKDAY() leva a data para a segunda.
                RelatorioPeriodo::SEMANA => "DATE_FORMAT(DATE_SUB({$coluna}, INTERVAL WEEKDAY({$coluna}) DAY), '%Y-%m-%d')",
                RelatorioPeriodo::MES => "DATE_FORMAT({$coluna}, '%Y-%m-01')",
                default => throw self::granularidadeDesconhecida($granularidade),
            },
            default => throw new RuntimeException(
                "Relatórios não sabem truncar data no driver '{$driver}'. ".
                'Acrescente a expressão em RelatorioSql antes de usar este banco.'
            ),
        };
    }

    /** A hora do dia (0–23) como inteiro, para o gráfico de uso por horário. */
    public static function horaDoDia(string $coluna, ?ConnectionInterface $conexao = null): string
    {
        $driver = self::driver($conexao);

        return match ($driver) {
            'sqlite' => "CAST(strftime('%H', {$coluna}) AS INTEGER)",
            'mysql', 'mariadb' => "HOUR({$coluna})",
            default => throw new RuntimeException(
                "Relatórios não sabem extrair a hora no driver '{$driver}'."
            ),
        };
    }

    /**
     * Diferença em SEGUNDOS entre dois instantes.
     *
     * Em minutos ou horas seria arredondado pelo banco antes de virar média —
     * e uma automação de 40 segundos apareceria como zero.
     */
    public static function diferencaEmSegundos(string $inicio, string $fim, ?ConnectionInterface $conexao = null): string
    {
        $driver = self::driver($conexao);

        return match ($driver) {
            // julianday devolve dias fracionários; 86400 os traz para segundos.
            'sqlite' => "(julianday({$fim}) - julianday({$inicio})) * 86400",
            'mysql', 'mariadb' => "TIMESTAMPDIFF(SECOND, {$inicio}, {$fim})",
            default => throw new RuntimeException(
                "Relatórios não sabem calcular duração no driver '{$driver}'."
            ),
        };
    }

    /**
     * Diferença em DIAS inteiros entre duas datas.
     *
     * Existe para a janela de "senha vencendo", que é um número de dias por
     * clínica (`configuracoes_globais.senha_alerta_dias`). Comparar contra uma
     * data-limite calculada em PHP não serve na visão de todas as clínicas: o
     * prazo é diferente em cada uma, e o limite teria que ser uma consulta por
     * tenant. Comparando a diferença contra a coluna, uma consulta responde.
     */
    public static function diferencaEmDias(string $de, string $ate, ?ConnectionInterface $conexao = null): string
    {
        $driver = self::driver($conexao);

        return match ($driver) {
            'sqlite' => "CAST(julianday(date({$ate})) - julianday(date({$de})) AS INTEGER)",
            'mysql', 'mariadb' => "DATEDIFF({$ate}, {$de})",
            default => throw new RuntimeException(
                "Relatórios não sabem calcular diferença em dias no driver '{$driver}'."
            ),
        };
    }

    private static function driver(?ConnectionInterface $conexao): string
    {
        return ($conexao ?? DB::connection())->getDriverName();
    }

    private static function granularidadeDesconhecida(string $granularidade): RuntimeException
    {
        return new RuntimeException("Granularidade desconhecida no SQL: {$granularidade}.");
    }
}
