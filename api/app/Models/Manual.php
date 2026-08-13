<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Manual extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    protected $table = 'manuais';

    protected $fillable = ['tenant_id', 'tipo', 'conteudo_html', 'atualizado_por'];

    public function atualizadoPor()
    {
        return $this->belongsTo(User::class, 'atualizado_por');
    }
}
