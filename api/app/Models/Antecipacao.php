<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Registro de acompanhamento de uma antecipação gerada manualmente — não
 * confundir com o antigo balde de cota do mesmo nome (removido na Fase 1).
 * Ver a nota na migration `create_antecipacoes_table` sobre o que fica fora
 * daqui (a fila "Elegíveis" é calculada ao vivo, não persistida).
 */
class Antecipacao extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    protected $table = 'antecipacoes';

    public const STATUS_PENDENTE = 'pendente';

    public const STATUS_GERADA = 'gerada';

    public const STATUS_IGNORADA = 'ignorada';

    protected $fillable = [
        'tenant_id', 'solicitacao_origem_id', 'status', 'data_alvo', 'itens_selecionados',
        'observacoes', 'criado_por_id', 'solicitacao_gerada_id', 'gerado_em', 'ignorado_em',
    ];

    protected $casts = [
        'data_alvo' => 'date',
        'itens_selecionados' => 'array',
        'gerado_em' => 'datetime',
        'ignorado_em' => 'datetime',
    ];

    public function solicitacaoOrigem()
    {
        return $this->belongsTo(Solicitacao::class, 'solicitacao_origem_id');
    }

    public function solicitacaoGerada()
    {
        return $this->belongsTo(Solicitacao::class, 'solicitacao_gerada_id');
    }

    public function criadoPor()
    {
        return $this->belongsTo(User::class, 'criado_por_id');
    }
}
