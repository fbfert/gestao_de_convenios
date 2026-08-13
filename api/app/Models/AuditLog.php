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
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
