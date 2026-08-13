<?php

namespace App\Http\Resources;

use App\Support\PermissionCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PermissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'label' => PermissionCatalog::rotuloDe($this->name),
            'domain' => explode('.', $this->name)[0] ?? null,
        ];
    }
}
