<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PacienteImportLinha extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'paciente_import_linhas';

    protected $fillable = [
        'tenant_id',
        'paciente_import_lote_id',
        'linha',
        'status',
        'matched_paciente_id',
        'dados_json',
        'erros_json',
    ];

    protected $casts = [
        'linha' => 'integer',
        'dados_json' => 'array',
        'erros_json' => 'array',
    ];

    public function lote()
    {
        return $this->belongsTo(PacienteImportLote::class, 'paciente_import_lote_id');
    }

    public function matchedPaciente()
    {
        return $this->belongsTo(Paciente::class, 'matched_paciente_id');
    }
}
