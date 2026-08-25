<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cid extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    protected $table = 'cids';

    protected $fillable = ['tenant_id', 'codigo', 'descricao', 'ativo'];

    protected $casts = [
        'ativo' => 'boolean',
    ];

    public function solicitacoes()
    {
        return $this->belongsToMany(Solicitacao::class, 'cid_solicitacao');
    }
}
