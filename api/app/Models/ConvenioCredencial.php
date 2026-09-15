<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToTenant;
use App\Support\ConvenioDriverCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Credencial de automação de um convênio.
 *
 * Substitui `UnimedRdaCredential`, que era única por tenant. Aqui a chave é
 * `tenant_id + convenio_id`: um tenant pode ter credenciais de convênios
 * diferentes ao mesmo tempo, e a pausa do disjuntor alcança só o convênio em
 * que a falha aconteceu.
 *
 * Ter credencial NÃO liga automação. Quem decide se uma Solicitação ou Guia
 * entra em fluxo automatizado continua sendo `convenios.connector_driver` — o
 * `driver` daqui só diz quais campos esta credencial tem.
 */
class ConvenioCredencial extends Model
{
    use Auditable, BelongsToTenant;

    protected $table = 'convenio_credenciais';

    /**
     * O campo inteiro fica fora da auditoria.
     *
     * Cobrir `credenciais` de uma vez, e não campo a campo, é o que mantém a
     * proteção válida quando um driver novo trouxer um segredo com outro nome:
     * fica registrado que a credencial mudou, nunca o valor.
     */
    protected array $auditOcultos = ['credenciais'];

    protected $fillable = [
        'tenant_id',
        'convenio_id',
        'driver',
        'credenciais',
        'ativo',
        'automation_paused_at',
        'automation_paused_reason',
    ];

    protected $casts = [
        'credenciais' => 'encrypted:array',
        'ativo' => 'boolean',
        'automation_paused_at' => 'datetime',
    ];

    public function convenio(): BelongsTo
    {
        return $this->belongsTo(Convenio::class);
    }

    /** Valor de um campo do driver, ou null quando não foi preenchido. */
    public function campo(string $chave): ?string
    {
        $valor = ($this->credenciais ?? [])[$chave] ?? null;

        return filled($valor) ? (string) $valor : null;
    }

    /**
     * A credencial está utilizável?
     *
     * Ativa não basta: falta campo obrigatório e a automação falharia no portal
     * em vez de recusar aqui. Driver sem campos — o caso do `scsaude` enquanto a
     * autenticação não é definida — nunca está pronto.
     */
    public function pronta(): bool
    {
        if (! $this->ativo) {
            return false;
        }

        $obrigatorios = ConvenioDriverCatalog::chavesObrigatorias($this->driver);

        if ($obrigatorios === []) {
            return false;
        }

        foreach ($obrigatorios as $chave) {
            if ($this->campo($chave) === null) {
                return false;
            }
        }

        return true;
    }
}
