<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * IMPORTANTE: User não usa o trait BelongsToTenant/TenantScope.
     * O login busca por e-mail antes de existir tenant no contexto da
     * requisição (chicken-and-egg) — pressupõe e-mail único globalmente
     * entre tenants. É o tenant_id do usuário autenticado que alimenta o
     * TenantContext (ver App\Http\Middleware\ResolveTenant), nunca o
     * contrário. Se e-mail duplicado entre clínicas virar requisito, esse
     * desenho precisa ser revisto (ex: login por slug+e-mail).
     */
    protected $fillable = [
        'tenant_id', 'name', 'email', 'password', 'profissional_id', 'ativo',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'ativo' => 'boolean',
        'profissional_id' => 'integer',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function profissional()
    {
        return $this->belongsTo(Profissional::class);
    }
}
