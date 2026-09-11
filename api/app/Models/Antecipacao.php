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
 *
 * Antecipar NÃO cria uma Solicitação nova: cria itens novos (renovação,
 * `SolicitacaoItem::renovacao_de_item_id`) na MESMA solicitação de origem —
 * ver App\Services\AntecipacaoService::criar(). `itens_selecionados` guarda,
 * depois de gerado, o par especialidade/profissional escolhido MAIS o id do
 * item e da guia que a geração criou (`item_gerado_id`/`guia_gerada_id`).
 */
class Antecipacao extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    protected $table = 'antecipacoes';

    public const STATUS_GERADA = 'gerada';

    public const STATUS_IGNORADA = 'ignorada';

    protected $fillable = [
        'tenant_id', 'solicitacao_origem_id', 'status', 'data_alvo', 'itens_selecionados',
        'observacoes', 'criado_por_id', 'gerado_em', 'ignorado_em',
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

    public function criadoPor()
    {
        return $this->belongsTo(User::class, 'criado_por_id');
    }
}
