<?php

namespace App\Http\Controllers;

use App\Http\Resources\RoleResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return RoleResource::collection(
            Role::query()
                ->where('tenant_id', request()->user()?->tenant_id)
                ->where('guard_name', 'web')
                ->orderBy('name')
                ->get()
        );
    }
}
