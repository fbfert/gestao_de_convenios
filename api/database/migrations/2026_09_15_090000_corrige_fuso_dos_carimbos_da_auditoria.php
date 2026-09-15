<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Traz `audit_logs.created_at` para o fuso do aplicativo.
 *
 * A coluna tem `default current_timestamp()` e o model usava
 * `$timestamps = false`, então quem preenchia era o banco — com a hora DELE. O
 * servidor roda em UTC e o aplicativo em America/Sao_Paulo: cada linha ficou
 * três horas à frente do instante real, e o Eloquent a lia de volta como se
 * fosse horário local. A trilha exibia e exportava tudo adiantado, o filtro por
 * período devolvia o dia inteiro entre 21h e meia-noite, e o expurgo media o
 * corte pela régua errada.
 *
 * O model passou a gravar o carimbo (ver `AuditLog::booted()`); esta migration
 * acerta o que já estava gravado, para não ficar metade em cada convenção — que
 * seria pior do que o defeito original.
 *
 * O instante registrado não muda: muda a forma de escrevê-lo. Um evento
 * gravado como `22:38` passa a `19:38`, que é a hora em que de fato aconteceu.
 *
 * ── Por que é seguro rodar no deploy ──────────────────────────────────────────
 *
 * `deploy/entrypoint.sh` executa `migrate --force` ANTES de subir o supervisord,
 * e php-fpm, nginx, `queue:work` e `schedule:work` vivem todos nele. O container
 * antigo já foi derrubado pelo `up -d`. Ou seja: enquanto isto roda, ninguém
 * escreve em `audit_logs`, e não há risco de uma linha nova (já no fuso certo,
 * gravada pelo model) ser convertida junto e ficar três horas atrasada.
 *
 * O limite por `id`, ainda assim, existe como cinto de segurança para quem
 * rodar `migrate` à mão com a aplicação no ar: só converte o que já existia
 * quando a migration começou.
 *
 * ── Por que em lote ──────────────────────────────────────────────────────────
 *
 * Linha a linha são ~19 mil UPDATEs, medidos em ~34s sobre as 15.587 linhas do
 * dump — tempo em que o nginx ainda não subiu. Um UPDATE por lote com CASE faz
 * o mesmo trabalho em poucos segundos.
 *
 * A conversão continua sendo calculada em PHP, e não com `CONVERT_TZ`: aquela
 * função depende das tabelas de fuso carregadas no servidor, que costumam não
 * estar, e falha devolvendo NULL — o que apagaria o carimbo em vez de
 * corrigi-lo. Calcular por linha também mantém o resultado correto caso os
 * dados um dia atravessem uma era de horário de verão (o Brasil não tem desde
 * 2019, e todas as linhas atuais são de 2026).
 */
return new class extends Migration
{
    private const LOTE = 1000;

    public function up(): void
    {
        $this->converter(fn (Carbon $valor) => $valor
            ->shiftTimezone('UTC')
            ->setTimezone(config('app.timezone')));
    }

    public function down(): void
    {
        $this->converter(fn (Carbon $valor) => $valor
            ->shiftTimezone(config('app.timezone'))
            ->setTimezone('UTC'));
    }

    private function converter(callable $transforma): void
    {
        $ultimoId = (int) (DB::table('audit_logs')->max('id') ?? 0);

        if ($ultimoId === 0) {
            return;
        }

        DB::table('audit_logs')
            ->where('id', '<=', $ultimoId)
            ->whereNotNull('created_at')
            ->select(['id', 'created_at'])
            ->orderBy('id')
            ->chunk(self::LOTE, function ($linhas) use ($transforma) {
                $novos = [];

                foreach ($linhas as $linha) {
                    $novos[(int) $linha->id] = $transforma(Carbon::parse($linha->created_at))
                        ->format('Y-m-d H:i:s');
                }

                if ($novos === []) {
                    return;
                }

                $this->atualizarEmLote($novos);
            });
    }

    /**
     * Um UPDATE por lote, com `CASE id`.
     *
     * @param  array<int, string>  $novos  id => carimbo já convertido
     */
    private function atualizarEmLote(array $novos): void
    {
        $casos = '';
        $bindings = [];

        foreach ($novos as $id => $carimbo) {
            $casos .= ' WHEN ? THEN ?';
            $bindings[] = $id;
            $bindings[] = $carimbo;
        }

        $ids = implode(',', array_map('intval', array_keys($novos)));

        DB::update(
            "UPDATE audit_logs SET created_at = CASE id{$casos} END WHERE id IN ({$ids})",
            $bindings,
        );
    }
};
