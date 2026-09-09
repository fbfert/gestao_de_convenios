<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SolicitacaoItem extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    protected $table = 'solicitacao_itens';

    protected $fillable = [
        'tenant_id',
        'solicitacao_id',
        'especialidade_id',
        'profissional_id',
        'renovacao_de_item_id',
        'quantidade',
        'status_operacional',
        'observacoes',
        'unimed_verificacao_next_check_at',
    ];

    protected $casts = [
        'quantidade' => 'integer',
        'unimed_verificacao_next_check_at' => 'datetime',
    ];

    public function solicitacao()
    {
        return $this->belongsTo(Solicitacao::class);
    }

    public function especialidade()
    {
        return $this->belongsTo(Especialidade::class);
    }

    public function profissional()
    {
        return $this->belongsTo(Profissional::class);
    }

    public function documentos()
    {
        return $this->hasMany(SolicitacaoDocumento::class);
    }

    public function guia()
    {
        return $this->hasOne(Guia::class);
    }

    public function automacaoExecucoes()
    {
        return $this->hasMany(AutomacaoExecucao::class);
    }

    /**
     * O item de ORIGEM da cadeia de renovação — nulo quando este é a origem.
     *
     * Nunca aponta para o item anterior: com todos apontando para o primeiro,
     * somar as sessões já pedidas no ciclo é uma query; em árvore, seria
     * recursão. Quem normaliza isso é o servidor, em
     * `SolicitacaoService::adicionarItem()`.
     */
    public function renovacaoDe()
    {
        return $this->belongsTo(self::class, 'renovacao_de_item_id');
    }

    /** As renovações que apontam para este item como origem. */
    public function renovacoes()
    {
        return $this->hasMany(self::class, 'renovacao_de_item_id');
    }

    /**
     * O primeiro item da cadeia: ele mesmo, quando não é renovação.
     *
     * O `?? $this` cobre a origem apagada — a FK é `nullOnDelete`, então o
     * vínculo pode existir apontando para o vazio, e nesse caso este item
     * voltou a ser o começo do que sobrou.
     */
    public function origemDaCadeia(): self
    {
        return $this->renovacao_de_item_id ? ($this->renovacaoDe ?? $this) : $this;
    }
}
