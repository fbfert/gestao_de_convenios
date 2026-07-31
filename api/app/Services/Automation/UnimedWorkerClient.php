<?php

namespace App\Services\Automation;

use App\Models\AutomacaoExecucao;

interface UnimedWorkerClient
{
    public function executar(AutomacaoExecucao $execucao, array $payload): array;

    public function health(): array;
}
