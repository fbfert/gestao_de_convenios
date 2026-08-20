<?php

namespace App\Services\ClinicaSync;

use App\Models\ClinicaConexaoConfig;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Cliente HTTP pro clinica.gestaonossa.com.br — mesmo padrão do
 * HttpUnimedWorkerClient (baseUrl + Bearer token via config do tenant).
 *
 * Autentica como conta de integração dedicada (Sanctum Personal Access Token,
 * não a stateful/cookie da SPA) — ver docs/clinica-sync.md.
 */
class ClinicaApiClient
{
    public function __construct(private readonly ClinicaConexaoConfig $config) {}

    public function listarProfissionais(): array
    {
        return $this->request()->get('/profissionais')->throw()->json();
    }

    public function criarProfissional(array $payload): array
    {
        return $this->request()->post('/profissionais', $payload)->throw()->json();
    }

    /** $updatedAtAtual: token de concorrência otimista (If-Match) — ver ConcorrenciaOtimista no clinica. */
    public function atualizarProfissional(int $clinicaId, array $payload, string $updatedAtAtual): array
    {
        return $this->request()
            ->withHeaders(['If-Match' => $updatedAtAtual])
            ->patch("/profissionais/{$clinicaId}", $payload)->throw()->json();
    }

    /** @return array{data: list<array<string, mixed>>, meta: array{last_page: int}} */
    public function listarPacientesPagina(int $pagina): array
    {
        return $this->request()->get('/pacientes', ['per_page' => 100, 'page' => $pagina])->throw()->json();
    }

    public function buscarPaciente(int $clinicaId): array
    {
        return $this->request()->get("/pacientes/{$clinicaId}")->throw()->json();
    }

    public function criarPaciente(array $payload): array
    {
        return $this->request()->post('/pacientes', $payload)->throw()->json();
    }

    public function atualizarPaciente(int $clinicaId, array $payload, string $updatedAtAtual): array
    {
        return $this->request()
            ->withHeaders(['If-Match' => $updatedAtAtual])
            ->patch("/pacientes/{$clinicaId}", $payload)->throw()->json();
    }

    public function listarCbos(): array
    {
        return $this->request()->get('/cbos')->throw()->json();
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim($this->config->base_url, '/').'/api')
            ->timeout(15)
            ->acceptJson()
            ->withToken($this->config->token);
    }

    /** 4xx de validação não é falha de infraestrutura — quem chama decide o que fazer. */
    public static function eValidacao(\Throwable $e): bool
    {
        return self::statusDoErro($e) === 422;
    }

    /** 409 = alguém editou no clinica depois do nosso último sync — não é bug, é a hora de recuar. */
    public static function eConflito(\Throwable $e): bool
    {
        return self::statusDoErro($e) === 409;
    }

    private static function statusDoErro(\Throwable $e): ?int
    {
        return $e instanceof \Illuminate\Http\Client\RequestException && $e->response instanceof Response
            ? $e->response->status()
            : null;
    }
}
