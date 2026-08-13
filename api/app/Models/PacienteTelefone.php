<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class PacienteTelefone extends Model
{
    use Auditable, BelongsToTenant;

    protected $table = 'paciente_telefones';

    protected $fillable = [
        'tenant_id', 'paciente_id', 'numero', 'rotulo', 'contato_nome', 'principal', 'ordem',
    ];

    protected $casts = [
        'principal' => 'boolean',
        'ordem' => 'integer',
    ];

    /** Rótulos aceitos. A tela mostra o texto; o banco guarda a chave. */
    public const ROTULOS = ['celular', 'fixo', 'whatsapp', 'recado'];

    public function paciente()
    {
        return $this->belongsTo(Paciente::class);
    }
}
