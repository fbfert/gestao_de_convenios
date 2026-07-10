<?php

namespace App\Services\Connectors;

use App\Models\Convenio;
use InvalidArgumentException;

class ConnectorResolver
{
    public function resolver(Convenio $convenio): ConnectorInterface
    {
        return match ($convenio->connector_type) {
            'manual' => app(ManualConnector::class),
            default => throw new InvalidArgumentException("Connector type não suportado: {$convenio->connector_type}"),
        };
    }
}
