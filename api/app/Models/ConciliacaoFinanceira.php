<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Services\ConciliacaoService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConciliacaoFinanceira extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'conciliacoes_financeiras';

    protected $fillable = [
        'tenant_id', 'guia_id', 'profissional_id', 'quantidade',
        'valor_unitario', 'valor_total', 'referencia_analitico_convenio',
        'status', 'conferido_em',
    ];

    protected $casts = [
        'quantidade' => 'integer',
        'valor_unitario' => 'decimal:2',
        'valor_total' => 'decimal:2',
        'conferido_em' => 'datetime',
    ];

    public function guia()
    {
        return $this->belongsTo(Guia::class);
    }

    public function profissional()
    {
        return $this->belongsTo(Profissional::class);
    }

    public function movimentosFinanceiros()
    {
        return $this->hasMany(MovimentoFinanceiro::class);
    }

    public function getEntradaTotalAttribute(): string
    {
        return number_format((float) $this->valor_total, 2, '.', '');
    }

    public function getSaidaTotalAttribute(): string
    {
        $movimentos = $this->relationLoaded('movimentosFinanceiros')
            ? $this->movimentosFinanceiros
            : $this->movimentosFinanceiros()->get();

        return number_format(
            (float) $movimentos->where('tipo', 'saida')->sum(fn (MovimentoFinanceiro $movimento) => (float) $movimento->valor_total),
            2,
            '.',
            ''
        );
    }

    public function getSaldoTotalAttribute(): string
    {
        return number_format((float) $this->entrada_total - (float) $this->saida_total, 2, '.', '');
    }

    public function getPercentualRepasseProfissionalAttribute(): ?string
    {
        return $this->repasseContext()['percentual_repasse_profissional'] ?? null;
    }

    public function getPercentualRetencaoClinicaAttribute(): ?string
    {
        return $this->repasseContext()['percentual_retencao_clinica'] ?? null;
    }

    public function getValorRepasseUnitarioAttribute(): ?string
    {
        return $this->repasseContext()['valor_repasse_unitario'] ?? null;
    }

    public function getValorRepasseTotalAttribute(): ?string
    {
        return $this->repasseContext()['valor_repasse_total'] ?? null;
    }

    public function getValorRetencaoUnitarioAttribute(): ?string
    {
        return $this->repasseContext()['valor_retencao_unitario'] ?? null;
    }

    public function getValorRetencaoTotalAttribute(): ?string
    {
        return $this->repasseContext()['valor_retencao_total'] ?? null;
    }

    /**
     * @return array{percentual_repasse_profissional: string, percentual_retencao_clinica: string, valor_repasse_unitario: string, valor_repasse_total: string, valor_retencao_unitario: string, valor_retencao_total: string}
     */
    private function repasseContext(): array
    {
        return app(ConciliacaoService::class)->calcularRepasse(
            $this->guia,
            $this->profissional_id,
            $this->quantidade
        );
    }
}
