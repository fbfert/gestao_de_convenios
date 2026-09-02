<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Support\SuperAdminAcesso;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use Auditable, HasApiTokens, HasFactory, Notifiable, HasRoles {
        // hasPermissionTo() é sobrescrito abaixo pro bypass de acesso de
        // super admin — precisa do original sob outro nome, porque o método
        // veio de uma trait (via HasRoles -> HasPermissions), não de uma
        // superclasse: `parent::` não enxerga método de trait.
        HasRoles::hasPermissionTo as hasPermissionToOriginal;
    }

    /**
     * IMPORTANTE: User não usa o trait BelongsToTenant/TenantScope.
     * O login busca por e-mail antes de existir tenant no contexto da
     * requisição (chicken-and-egg) — pressupõe e-mail único globalmente
     * entre tenants. É o tenant_id do usuário autenticado que alimenta o
     * TenantContext (ver App\Http\Middleware\ResolveTenant), nunca o
     * contrário. Se e-mail duplicado entre clínicas virar requisito, esse
     * desenho precisa ser revisto (ex: login por slug+e-mail).
     */
    /** Nunca entra na auditoria: fica registrado que mudou, nunca o valor. */
    protected array $auditOcultos = ['password'];

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
        'super_admin' => 'boolean',
    ];

    /**
     * Administra tenants. `super_admin` fica fora do $fillable de proposito:
     * a flag nunca pode ser atribuida em massa a partir de um request. Ver a
     * migration 2026_08_12_180000 para o motivo de nao ser uma permissao do
     * PermissionCatalog.
     */
    public function ehSuperAdmin(): bool
    {
        return (bool) $this->super_admin;
    }

    /**
     * Tenant-alvo de um "acesso de super admin" em andamento nesta mesma
     * requisição (ver SuperAdminAcesso e TenantController::acessar), ou
     * null fora desse modo. Só considera o token DESTA instância — outra
     * linha de User carregada de uma listagem qualquer nunca tem
     * currentAccessToken() preenchido, então não é afetada.
     */
    protected function tenantAcessoAlvo(): ?int
    {
        if (! $this->ehSuperAdmin()) {
            return null;
        }

        $token = $this->currentAccessToken();

        return $token instanceof PersonalAccessToken
            ? SuperAdminAcesso::tenantIdDoToken($token)
            : null;
    }

    /**
     * `tenant_id` passa a refletir o acesso de super admin quando ativo —
     * de propósito, e não um TenantContext separado: o resto do sistema
     * inteiro (dezenas de controllers) lê `$request->user()->tenant_id`
     * direto pra gravar novo registro, sem passar por TenantContext. Fazer
     * a correção aqui, na fonte, propaga pra tudo isso automaticamente —
     * caçar e trocar cada um dos lugares seria um raio de mudança enorme e
     * fácil de deixar algo escapar (um registro criado "dentro" da clínica
     * visitada acabaria gravado, em silêncio, na clínica do super admin).
     *
     * Some com App\Concerns\BelongsToTenant (global scope + creating() nos
     * outros models) e com App\Http\Middleware\ResolveTenant, que só lê
     * este mesmo valor — nenhum dos dois precisa saber que o acesso existe.
     */
    public function getTenantIdAttribute($value)
    {
        return $this->tenantAcessoAlvo() ?? $value;
    }

    /**
     * Sob acesso de super admin a outro tenant, libera qualquer permissão —
     * o super admin não tem papel atribuído no tenant alheio, e sem esse
     * bypass o acesso concedido pela tela de Clínicas seria inútil (veria
     * tudo bloqueado). É o único ponto de bypass: tanto o middleware
     * `permission:` do Spatie quanto `$user->can()` passam por aqui (ver
     * PermissionRegistrar::registerPermissions, que registra um Gate::before
     * chamando checkPermissionTo -> hasPermissionTo).
     */
    public function hasPermissionTo($permission, $guardName = null): bool
    {
        if ($this->tenantAcessoAlvo() !== null) {
            return true;
        }

        return $this->hasPermissionToOriginal($permission, $guardName);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function profissional()
    {
        return $this->belongsTo(Profissional::class);
    }
}
