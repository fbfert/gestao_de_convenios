<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Copia as credenciais Unimed para a estrutura por convênio.
 *
 * COPIA, não move: `unimed_rda_credentials` fica no lugar, com os dados, até um
 * change de limpeza posterior. Enquanto a tabela antiga existir e as rotas
 * `/configuracoes/unimed*` responderem, reverter é voltar o deploy do front —
 * é esse o plano de rollback.
 *
 * O convênio é resolvido pelo `connector_driver = 'unimed_rda'` do tenant, que
 * é como o sistema descobre o convênio da automação hoje. Tenant com credencial
 * e sem convênio correspondente não é migrado: fica registrado no log e a
 * migration segue. Falhar aqui pararia o deploy inteiro por causa de uma linha
 * órfã, e órfã ela já era antes da migration.
 *
 * Ler e reescrever os segredos passando pelo `Crypt` é proposital: os dois
 * formatos são diferentes (lá, `password` cifrado sozinho; aqui, o array
 * inteiro), então não há como copiar o texto cifrado de um para o outro.
 */
return new class extends Migration
{
    private const DRIVER = 'unimed_rda';

    public function up(): void
    {
        $antigas = DB::table('unimed_rda_credentials')->get();

        if ($antigas->isEmpty()) {
            return;
        }

        $agora = now();
        $semConvenio = [];

        foreach ($antigas as $antiga) {
            $convenioId = DB::table('convenios')
                ->where('tenant_id', $antiga->tenant_id)
                ->where('connector_driver', self::DRIVER)
                ->value('id');

            if (! $convenioId) {
                $semConvenio[] = (int) $antiga->tenant_id;

                continue;
            }

            // Já migrado: a migration precisa ser idempotente porque o
            // `unique(tenant_id, convenio_id)` abortaria uma segunda passagem.
            $jaExiste = DB::table('convenio_credenciais')
                ->where('tenant_id', $antiga->tenant_id)
                ->where('convenio_id', $convenioId)
                ->exists();

            if ($jaExiste) {
                continue;
            }

            DB::table('convenio_credenciais')->insert([
                'tenant_id' => $antiga->tenant_id,
                'convenio_id' => $convenioId,
                'driver' => self::DRIVER,
                'credenciais' => Crypt::encryptString(json_encode(array_filter([
                    'login' => $antiga->login,
                    'password' => $this->decifrar($antiga->password),
                    'base_url' => $antiga->base_url,
                    'nome_contratado' => $antiga->nome_contratado ?? null,
                ], fn ($valor) => $valor !== null && $valor !== ''))),
                'ativo' => $antiga->ativo,
                'automation_paused_at' => $antiga->automation_paused_at ?? null,
                'automation_paused_reason' => $antiga->automation_paused_reason ?? null,
                'created_at' => $antiga->created_at ?? $agora,
                'updated_at' => $agora,
            ]);
        }

        if ($semConvenio !== []) {
            Log::warning(
                'credenciais-por-convenio: credencial Unimed sem convênio de connector_driver=unimed_rda; não migrada.',
                ['tenant_ids' => $semConvenio],
            );
        }
    }

    /**
     * A tabela antiga guarda `password` com o cast `encrypted` do model. Aqui a
     * leitura é por query builder, que não aplica cast — daí o decrypt na mão.
     * Valor que não decifra (chave trocada, dado corrompido) vira null em vez de
     * derrubar a migration: melhor a credencial pedir novo cadastro do que o
     * deploy parar.
     */
    private function decifrar(?string $valor): ?string
    {
        if (blank($valor)) {
            return null;
        }

        try {
            return Crypt::decryptString($valor);
        } catch (Throwable $excecao) {
            Log::warning('credenciais-por-convenio: senha não pôde ser decifrada na migração.', [
                'erro' => $excecao->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Só esvazia a tabela nova. `unimed_rda_credentials` não é tocada na ida
     * nem na volta, então o rollback não tem o que restaurar.
     */
    public function down(): void
    {
        DB::table('convenio_credenciais')->where('driver', self::DRIVER)->delete();
    }
};
