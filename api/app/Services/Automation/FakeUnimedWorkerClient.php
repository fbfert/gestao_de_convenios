<?php

namespace App\Services\Automation;

use App\Models\AutomacaoExecucao;

class FakeUnimedWorkerClient implements UnimedWorkerClient
{
    public array $calls = [];

    public function __construct(private readonly array $result = ['status' => 'succeeded'])
    {
    }

    public function executar(AutomacaoExecucao $execucao, array $payload): array
    {
        $this->calls[] = [
            'execucao_id' => $execucao->id,
            'operacao' => $execucao->operacao,
            'payload' => $payload,
        ];

        return $this->result;
    }

    public function health(): array
    {
        return ['status' => 'available'];
    }
}
