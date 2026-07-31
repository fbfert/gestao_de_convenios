<?php

namespace App\Services\Automation;

use App\Models\AutomacaoExecucao;
use Illuminate\Support\Facades\Http;

class HttpUnimedWorkerClient implements UnimedWorkerClient
{
    public function executar(AutomacaoExecucao $execucao, array $payload): array
    {
        $response = $this->request()
            ->post('/operations/'.$execucao->operacao, [
                'execution_id' => $execucao->id,
                'idempotency_key' => $execucao->idempotency_key,
                'payload' => $payload,
            ])
            ->throw();

        return $response->json();
    }

    public function health(): array
    {
        return $this->request()->get('/health')->throw()->json();
    }

    private function request()
    {
        return Http::baseUrl(rtrim((string) config('services.unimed_worker.base_url'), '/'))
            ->timeout((int) config('services.unimed_worker.timeout', 20))
            ->acceptJson()
            ->withToken((string) config('services.unimed_worker.token'));
    }
}
