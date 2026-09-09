<?php

namespace App\Http\Controllers;

use App\Models\SaudeComponente;
use App\Services\SaudeService;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Throwable;

/**
 * Endpoint publico de saude, batido a cada 2 minutos por um monitor externo.
 *
 * Duas restricoes moldam tudo aqui:
 *
 * 1. E publico. Nada no corpo pode identificar tenant, usuario ou volume de
 *    dados — quem le isto nao passou por autenticacao nenhuma.
 * 2. E barato. A cada 2 minutos, para sempre: uma consulta trivial por item e
 *    nenhum count() em tabela de negocio.
 *
 * Quem decide se ha problema e o STATUS HTTP, nao o corpo: o monitor externo
 * so entende 200 contra qualquer outra coisa.
 */
class HealthController extends Controller
{
    /**
     * Chave do carimbo que o scheduler escreve a cada minuto
     * (ver o Schedule::call em routes/console.php).
     */
    public const CHAVE_SCHEDULER = 'scheduler.ultima_rodada';

    /**
     * Folga aceita desde a ultima rodada do scheduler. O carimbo e escrito a
     * cada minuto, entao 10 minutos absorvem uma rodada atrasada por carga sem
     * esconder um cron que morreu de vez.
     */
    private const SCHEDULER_TOLERANCIA_MINUTOS = 10;

    public function __invoke(SaudeService $saude): JsonResponse
    {
        $db = $this->verificarBanco();
        $fila = $this->verificarFila();
        $ultimaRodada = $this->schedulerUltimaRodada();
        $componentes = $this->componentes($saude, $db);

        // Carimbo ausente conta como atraso, e nao como "ainda nao sabemos":
        // um scheduler que nunca rodou e exatamente a falha que este endpoint
        // existe para pegar. Logo apos um deploy isto devolve 503 ate o cron
        // provar que esta vivo — que e a resposta honesta nesse momento.
        $schedulerAtrasado = $ultimaRodada === null
            || $ultimaRodada->lt(now()->subMinutes(self::SCHEDULER_TOLERANCIA_MINUTOS));

        $algumComponenteFora = collect($componentes)
            ->contains(fn (array $c) => $c['estado'] === SaudeComponente::ESTADO_FORA);

        $saudavel = $db === 'ok'
            && $fila === 'ok'
            && ! $schedulerAtrasado
            && ! $algumComponenteFora;

        return response()->json([
            'db' => $db,
            'fila' => $fila,
            'scheduler_ultima_rodada' => $ultimaRodada?->toIso8601String(),
            'componentes' => $componentes,
        ], $saudavel ? 200 : 503);
    }

    /**
     * Componentes agregados por chave, com o pior estado de cada uma.
     *
     * Depende do banco, entao so consulta quando o banco respondeu: com o banco
     * fora, tentar isto trocaria um 503 correto por uma excecao de conexao.
     *
     * @return array<int, array{chave: string, estado: string}>
     */
    private function componentes(SaudeService $saude, string $db): array
    {
        if ($db !== 'ok') {
            return [];
        }

        try {
            return $saude->resumoPublico();
        } catch (Throwable) {
            return [];
        }
    }

    private function verificarBanco(): string
    {
        try {
            DB::select('select 1');

            return 'ok';
        } catch (Throwable) {
            return 'erro';
        }
    }

    /**
     * size() e a operacao mais barata que de fato fala com o backend da fila —
     * resolver a conexao sozinho nao prova nada, porque o driver so conecta no
     * primeiro uso. Na fila `database` isto e um count na tabela `jobs`, que e
     * curta por natureza (fila vazia e o estado normal); nao e um count em
     * tabela de negocio.
     */
    private function verificarFila(): string
    {
        try {
            Queue::connection()->size();

            return 'ok';
        } catch (Throwable) {
            return 'erro';
        }
    }

    /**
     * O carimbo vive no cache porque, neste projeto, o cache persiste entre
     * processos: config/cache.php usa env('CACHE_STORE', 'database'), e os dois
     * drivers em uso — `database` em producao e `file` no ambiente local —
     * gravam fora da memoria do processo. Isso importa porque quem escreve e o
     * processo do cron e quem le e o processo do PHP-FPM. O unico driver que
     * nao serviria e `array`, restrito ao phpunit.xml.
     */
    private function schedulerUltimaRodada(): ?Carbon
    {
        try {
            $valor = Cache::get(self::CHAVE_SCHEDULER);
        } catch (Throwable) {
            // Cache quebrado nao pode derrubar a leitura dos outros itens: sem
            // carimbo, o proprio criterio de atraso ja devolve 503.
            return null;
        }

        if ($valor === null) {
            return null;
        }

        // O driver define o que volta: `array` devolve o objeto tal como foi
        // guardado, os que serializam devolvem a data reconstruida ou a string.
        return $valor instanceof CarbonInterface
            ? Carbon::instance($valor)
            : Carbon::parse($valor);
    }
}
