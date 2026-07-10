<?php

namespace App\Services\Connectors;

use App\Models\Guia;

class ManualConnector implements ConnectorInterface
{
    public function checar(Guia $guia): array
    {
        return [
            'status' => 'pending_manual',
            'detalhes' => [
                'mensagem' => 'Conferência manual necessária.',
                'guia_id' => $guia->id,
                'convenio_id' => $guia->convenio_id,
            ],
        ];
    }
}
