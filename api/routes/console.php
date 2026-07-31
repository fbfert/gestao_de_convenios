<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\EnfileirarConsultasUnimedDueJob;
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
