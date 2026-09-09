<?php

namespace App\Console\Commands;

use App\Support\GuardaDeBancoDestrutivo;
use Illuminate\Console\Command;

/**
 * Confere, ANTES da suite ponta a ponta, que o banco alvo pode ser apagado.
 *
 * A trava do GuardaDeBancoDestrutivo ja impediria o estrago, mas ela fala a
 * lingua do Laravel ("This command is prohibited from running"), que nao diz a
 * quem esta rodando o teste o que aconteceu nem o que fazer. Este comando existe
 * para a suite parar com uma mensagem que explica o problema e a solucao.
 *
 * E a mesma logica de "falhar cedo e falhar claro" da trava de status de guia:
 * quem trabalha as tres da manha nao deve precisar ler o codigo do framework
 * para entender por que a suite parou.
 */
class ConferirAlvoDeTeste extends Command
{
    protected $signature = 'db:conferir-alvo-de-teste';

    protected $description = 'Confere que a conexao aponta para um banco descartavel antes de a suite apagar qualquer coisa.';

    public function handle(): int
    {
        $banco = GuardaDeBancoDestrutivo::bancoAlvo();

        if (GuardaDeBancoDestrutivo::alvoEhDescartavel()) {
            $this->info("Alvo de teste conferido: {$banco}");

            return self::SUCCESS;
        }

        $this->error("PARADO: o banco alvo e '{$banco}', que NAO e descartavel.");
        $this->newLine();
        $this->line('A suite ponta a ponta roda `migrate:fresh`, que APAGA tudo do banco alvo.');
        $this->line('Este banco nao tem nome de banco de teste, entao a execucao foi interrompida');
        $this->line('antes de qualquer alteracao.');
        $this->newLine();
        $this->line('Causa provavel: `api/.env.testing` ausente ou apontando para o banco errado.');
        $this->line('Sem esse arquivo, `--env=testing` cai no `.env` normal — que e o banco de');
        $this->line('desenvolvimento, e em producao seria o de producao.');
        $this->newLine();
        $this->line('Como resolver:');
        $this->line('  1. Confira `api/.env.testing` e o `DB_DATABASE` dele.');
        $this->line('  2. O nome precisa terminar em _e2e, _test ou _testing.');
        $this->line('  3. Crie o banco, se ainda nao existir.');
        $this->newLine();
        $this->line('Se voce REALMENTE quer apagar este banco, defina PERMITIR_RESET_DO_BANCO=true');
        $this->line('no ambiente. Precisa ser digitado — e esse e o ponto.');

        return self::FAILURE;
    }
}
