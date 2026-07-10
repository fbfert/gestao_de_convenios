<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lancamento extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'antecipacao_id', 'profissional_id',
        'data_sessao', 'status', 'observacoes',
    ];

    protected $casts = [
        'data_sessao' => 'date',
    ];

    public function antecipacao()
    {
        return $this->belongsTo(Antecipacao::class);
    }

    public function profissional()
    {
        return $this->belongsTo(Profissional::class);
    }
}
