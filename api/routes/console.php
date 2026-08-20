<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\EnfileirarConsultasUnimedDueJob;
use App\Jobs\ExpurgarAuditoriaJob;
use App\Jobs\ExpurgarCarteirinhasJob;
use App\Jobs\SincronizarClinicaJob;
use App\Jobs\VerificarGuiasDiarioJob;
use App\Models\AutomacaoEvento;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Artisan::command('automacao:limpar-evidencias {--dry-run} {--days=30}', function () {
    $cutoff = now()->subDays((int) $this->option('days'));
    $dryRun = (bool) $this->option('dry-run');
    $candidates = [];

    AutomacaoEvento::query()
        ->whereNotNull('evidencias')
        ->get()
        ->each(function (AutomacaoEvento $evento) use (&$candidates, $cutoff) {
            foreach (new RecursiveIteratorIterator(new RecursiveArrayIterator($evento->evidencias ?? [])) as $value) {
                if (! is_string($value) || ! str_starts_with($value, 'automacoes/evidencias/')) {
                    continue;
                }

                if (Storage::disk('local')->exists($value)
                    && Storage::disk('local')->lastModified($value) <= $cutoff->getTimestamp()) {
                    $candidates[] = $value;
                }
            }
        });

    $candidates = array_values(array_unique($candidates));

    if (! $dryRun) {
        foreach ($candidates as $path) {
            Storage::disk('local')->delete($path);
        }
    }

    $this->line(json_encode([
        'dry_run' => $dryRun,
        'candidates' => $candidates,
        'deleted' => $dryRun ? [] : $candidates,
    ], JSON_PRETTY_PRINT));
})->purpose('Limpa evidencias tecnicas antigas sem remover documentos medicos.');

Schedule::job(new VerificarGuiasDiarioJob)->dailyAt('02:00');
Schedule::job(new EnfileirarConsultasUnimedDueJob)->everyThirtyMinutes()->withoutOverlapping();

// Depois da verificacao de guias e antes do movimento do dia: o expurgo varre a
// trilha inteira do tenant e nao deve concorrer com o pico de uso.
Schedule::job(new ExpurgarAuditoriaJob)->dailyAt('03:30')->withoutOverlapping();

// Imagem de documento pessoal nao fica no servidor alem do prazo da clinica.
Schedule::job(new ExpurgarCarteirinhasJob)->dailyAt('03:45')->withoutOverlapping();

// Sync bidirecional de profissionais/pacientes com clinica.gestaonossa.com.br
// (intervalo decidido em 20/08/2026). O botao "Sincronizar Agora" na tela de
// configuracoes despacha o mesmo job com origem=manual, fora deste agendamento.
Schedule::job(new SincronizarClinicaJob)->everyFiveMinutes()->withoutOverlapping();
