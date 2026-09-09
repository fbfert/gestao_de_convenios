<?php

namespace App\Support;

use Illuminate\Database\Console\Migrations\FreshCommand;
use Illuminate\Database\Console\Migrations\RefreshCommand;
use Illuminate\Database\Console\Migrations\ResetCommand;
use Illuminate\Database\Console\Migrations\RollbackCommand;
use Illuminate\Database\Console\WipeCommand;

/**
 * Impede que um comando destrutivo apague um banco que nao seja de teste.
 *
 * POR QUE ISTO EXISTE, em 08/09/2026: o script `npm run test:e2e` roda
 *
 *     php artisan migrate:fresh --seed --env=testing --force
 *
 * e o Laravel so honra `--env=testing` se `api/.env.testing` existir. O arquivo
 * NAO existia no repositorio (o `.gitignore` do projeto exclui `.env.*` em
 * qualquer diretorio), entao o comando caia no `.env` normal. Numa maquina de
 * desenvolvimento com a copia da producao restaurada, uma unica execucao da
 * suite ponta a ponta teria apagado tudo. Em producao, o mesmo.
 *
 * A DEFESA NAO E O AMBIENTE, E O ALVO. Conferir `APP_ENV` nao bastaria: o modo
 * de falha original e justamente o ambiente NAO ser o que o comando pediu. Esta
 * guarda olha para qual banco a conexao aponta de fato, entao ela reprova:
 *
 *   - `.env.testing` ausente e o comando caindo no banco de desenvolvimento;
 *   - `.env.testing` existindo mas apontando para o banco errado;
 *   - `migrate:fresh` digitado a mao em producao;
 *   - CI com variavel de ambiente trocada.
 *
 * Sao liberados apenas tres alvos, e cada um por um motivo:
 *
 *   - SQLite em memoria: e o que o `RefreshDatabase` da suite unitaria usa, e
 *     morre junto com o processo.
 *   - Nome de banco terminado em `_e2e`, `_test` ou `_testing`: o alvo declarado
 *     como descartavel. `gestao_convenios_e2e` passa; `gestao_convenios` nao.
 *   - `PERMITIR_RESET_DO_BANCO=true` no ambiente: a valvula de escape explicita,
 *     para quem realmente quer recriar um banco de rascunho. Precisa ser
 *     digitada, que e o ponto — a decisao deixa de ser acidente.
 */
class GuardaDeBancoDestrutivo
{
    /** Sufixos que declaram um banco como descartavel. */
    private const SUFIXOS_DE_TESTE = ['_e2e', '_test', '_testing'];

    /** @var class-string[] */
    private const COMANDOS_DESTRUTIVOS = [
        FreshCommand::class,
        RefreshCommand::class,
        ResetCommand::class,
        RollbackCommand::class,
        WipeCommand::class,
    ];

    public static function aplicar(): void
    {
        $liberado = self::alvoEhDescartavel();

        foreach (self::COMANDOS_DESTRUTIVOS as $comando) {
            $comando::prohibit(! $liberado);
        }
    }

    /** O banco para o qual a conexao padrao aponta pode ser apagado? */
    public static function alvoEhDescartavel(): bool
    {
        if (filter_var(env('PERMITIR_RESET_DO_BANCO', false), FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }

        $conexao = config('database.default');
        $banco = (string) config("database.connections.{$conexao}.database");

        // SQLite em memoria: some quando o processo termina, nao ha o que perder.
        if ($banco === ':memory:') {
            return true;
        }

        // Arquivo SQLite de teste tambem vale pelo sufixo do nome.
        $nome = str_contains($banco, DIRECTORY_SEPARATOR) || str_contains($banco, '/')
            ? pathinfo($banco, PATHINFO_FILENAME)
            : $banco;

        foreach (self::SUFIXOS_DE_TESTE as $sufixo) {
            if (str_ends_with(strtolower($nome), $sufixo)) {
                return true;
            }
        }

        return false;
    }

    /** Nome do banco alvo, para as mensagens de erro. */
    public static function bancoAlvo(): string
    {
        $conexao = config('database.default');

        return (string) config("database.connections.{$conexao}.database");
    }
}
