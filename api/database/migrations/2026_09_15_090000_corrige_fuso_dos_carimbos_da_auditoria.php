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
 * gravado como `22:38 UTC` passa a `19:38`, que é a hora em que de fato
 * aconteceu.
 *
 * Conversão em PHP, e não com `CONVERT_TZ` do MySQL: a função depende das
 * tabelas de fuso carregadas no servidor, que costumam não estar, e falha
 * devolvendo NULL — o que aqui apagaria o carimbo em vez de corrigi-lo.
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
        DB::table('audit_logs')
            ->select(['id', 'created_at'])
            ->orderBy('id')
            ->chunk(self::LOTE, function ($linhas) use ($transforma) {
                foreach ($linhas as $linha) {
                    if (! $linha->created_at) {
                        continue;
                    }

                    DB::table('audit_logs')
                        ->where('id', $linha->id)
                        ->update([
                            'created_at' => $transforma(Carbon::parse($linha->created_at))
                                ->format('Y-m-d H:i:s'),
                        ]);
                }
            });
    }
};
