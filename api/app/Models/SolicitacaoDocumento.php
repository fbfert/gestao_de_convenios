<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Vínculo entre uma Solicitação (ou um item dela) e um documento da pasta do
 * paciente (PacienteArquivo). O arquivo em si mora em PacienteArquivo e pode
 * estar vinculado a mais de uma solicitação; remover este registro só desfaz
 * o vínculo — nunca apaga o arquivo.
 */
class SolicitacaoDocumento extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    protected $table = 'solicitacao_documentos';

    protected $fillable = [
        'tenant_id',
        'solicitacao_id',
        'solicitacao_item_id',
        'paciente_arquivo_id',
    ];

    public function solicitacao()
    {
        return $this->belongsTo(Solicitacao::class);
    }

    public function item()
    {
        return $this->belongsTo(SolicitacaoItem::class, 'solicitacao_item_id');
    }

    public function arquivo()
    {
        return $this->belongsTo(PacienteArquivo::class, 'paciente_arquivo_id');
    }

    /**
     * Depois que a Guia existe, o vínculo é evidência do que foi enviado à
     * operadora e não pode mais ser removido — mas isso trava só este
     * vínculo, não o arquivo (que pode estar servindo outra solicitação).
     */
    public function estaTravado(): bool
    {
        if ($this->solicitacao_item_id) {
            return (bool) $this->item?->guia()->exists();
        }

        return $this->solicitacao->itens()->whereHas('guia')->exists()
            || $this->solicitacao->guia()->exists();
    }
}
