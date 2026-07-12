<?php

namespace App\Http\Controllers;

use App\Http\Resources\PermissionResource;
use App\Support\PermissionCatalog;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $permissions = Permission::query()
            ->whereIn('name', PermissionCatalog::all())
            ->orderBy('name')
            ->get();

        return PermissionResource::collection($permissions);
    }
}
