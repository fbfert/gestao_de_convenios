<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'audit_logs';

    public $timestamps = false; // só created_at, ver migration

    protected $fillable = [
        'tenant_id', 'user_id', 'acao', 'entidade', 'entidade_id', 'payload', 'ip', 'user_agent',
        'created_at',
    ];

    /**
     * O carimbo passa a ser do aplicativo, e não do banco.
     *
     * A coluna tem `default current_timestamp()`, e com `$timestamps = false`
     * era o MySQL quem a preenchia — com a hora DELE. O servidor roda em UTC e
     * o aplicativo em America/Sao_Paulo, então toda linha nascia três horas à
     * frente do instante real, enquanto o Eloquent a lia de volta como se fosse
     * horário local.
     *
     * O efeito não era só cosmético: a trilha exibia e exportava todo evento
     * adiantado, o filtro por período comparava data local contra carimbo de
     * servidor (e passava a devolver tudo entre 21h e meia-noite), e o expurgo
     * por retenção media o corte pela régua errada.
     *
     * Escrever aqui alinha `audit_logs` com todas as outras tabelas, onde o
     * Eloquent já grava no fuso do aplicativo — e o default da coluna deixa de
     * ser usado por este caminho.
     */
    protected static function booted(): void
    {
        static::creating(function (self $log) {
            $log->created_at ??= now();
        });
    }

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
