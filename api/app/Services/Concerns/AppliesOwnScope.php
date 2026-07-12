<?php

namespace App\Services\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait AppliesOwnScope
{
    protected function aplicarEscopoOwn(
        Builder $query,
        string $permissionView,
        string $permissionViewOwn,
        callable $ownFilter
    ): Builder {
        $user = auth()->user();

        if (! $user) {
            abort(403);
        }

        if ($user->can($permissionView)) {
            return $query;
        }

        if ($user->can($permissionViewOwn)) {
            return $ownFilter($query, $user);
        }

        abort(403);
    }
}
