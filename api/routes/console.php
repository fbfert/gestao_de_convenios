<?php

use App\Http\Controllers\HealthController;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\AvaliarAlertasJob;
use App\Jobs\EnfileirarConsultasUnimedDueJob;
use App\Jobs\EnviarDigestAlertasJob;
use App\Jobs\ExpurgarAuditoriaJob;
use App\Jobs\ExpurgarCarteirinhasJob;
use App\Jobs\SincronizarClinicaJob;
use App\Jobs\VerificarGuiasDiarioJob;
use App\Models\AutomacaoEvento;
use App\Models\Medico;
use App\Services\SaudeService;
use App\Models\SaudeComponente;
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

Artisan::command('medicos:normalizar-nomes', function () {
    $atualizados = 0;

    Medico::query()->chunkById(200, function ($medicos) use (&$atualizados) {
        foreach ($medicos as $medico) {
            $medico->nome = $medico->nome; // dispara o mutator, que remove o prefixo se houver
            if ($medico->isDirty('nome')) {
                $medico->save();
                $atualizados++;
            }
        }
    });

    $this->info("Nomes normalizados: {$atualizados}");
})->purpose('Remove prefixos "Dr./Dra." (e variacoes) do nome de medicos ja cadastrados.');

// Prova de vida do cron do sistema, lida pelo GET /api/health. Sem este carimbo
// nao ha como distinguir "a API responde" de "a API responde mas nada agendado
// roda ha horas" — que e o caso em que o worker cai de madrugada e ninguem sabe.
// O cache serve de armazenamento porque persiste entre processos nos drivers em
// uso aqui (`database` e `file`); a justificativa completa esta no
// HealthController::schedulerUltimaRodada().
Schedule::call(function () {
    Cache::forever(HealthController::CHAVE_SCHEDULER, now());

    // Mesmo sinal, dois consumidores: o carimbo no cache responde ao monitor
    // externo, e o heartbeat alimenta o card de saude dentro do produto. Sem
    // tenant explicito de proposito — o agendador e um processo so servindo
    // todos, entao o heartbeat vale para a linha de cada tenant.
    app(SaudeService::class)->registrarHeartbeat(SaudeComponente::CHAVE_SCHEDULER);

    // E a varredura que enxerga a QUEDA. O heartbeat so acontece com o
    // componente vivo, entao ele registra a volta ao ar e nunca a saida: um
    // worker que morre para de mandar sinal, e ausencia de sinal nao chama
    // codigo nenhum. Sem esta linha, o historico de saude teria as recuperacoes
    // e nenhuma das quedas — e o relatorio de Automacoes responderia "sempre no
    // ar" para um periodo em que ninguem estava.
    app(SaudeService::class)->sincronizarEstados();
})->everyMinute()->name('carimbo-scheduler')->withoutOverlapping();

// A central de alertas so vale se for reavaliada sozinha: 15 minutos e curto o
// bastante para o operador nao trabalhar sobre alerta velho, e longo o bastante
// para nao concorrer com o pico de uso.
Schedule::job(new AvaliarAlertasJob)->everyFifteenMinutes()->withoutOverlapping();

// De hora em hora, e nao 24 agendamentos fixos: o job olha quem tem
// `horario_digest` naquela hora.
Schedule::job(new EnviarDigestAlertasJob)->hourly()->withoutOverlapping();

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
