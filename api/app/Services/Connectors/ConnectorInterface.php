<?php

namespace App\Services\Connectors;

use App\Models\Guia;

interface ConnectorInterface
{
    public function checar(Guia $guia): array;
}
