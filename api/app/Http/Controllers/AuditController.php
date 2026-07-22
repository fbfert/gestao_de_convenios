<?php

namespace App\Http\Controllers;

use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AuditController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return AuditLogResource::collection(
            AuditLog::query()
                ->with('user')
                ->latest('created_at')
                ->limit(50)
                ->get()
        );
    }
}
