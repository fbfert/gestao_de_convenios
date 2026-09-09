<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Peca do sistema que pode estar viva ou morta: worker de automacao, agendador,
 * fila, envio de e-mail, conector.
 *
 * O estado NAO mora aqui — e derivado do carimbo pelo SaudeService. Ver
 * openspec/changes/saude-componentes/design.md.
 *
 * Sobre o trait Auditable: o heartbeat grava a cada minuto, e registrar isso na
 * trilha significaria 1.440 linhas por dia por componente. O SaudeService
 * suspende o registro automatico so na escrita do heartbeat, com
 * Auditoria::semRegistroAutomatico() — mudanca de configuracao feita por gente
 * (nome, intervalo, ativo) continua auditada normalmente.
 */
class SaudeComponente extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    /**
     * Chaves dos componentes que o proprio codigo carimba.
     *
     * Moram no model, e nao no seeder, porque quem registra heartbeat e codigo
     * de aplicacao (agendador, job da automacao, envio de e-mail) e nao pode
     * depender de uma classe de carga inicial. O seeder consome estas mesmas
     * constantes. Componente de conector novo nao precisa de constante nenhuma:
     * entra por linha na tabela, com a chave que a linha disser.
     */
    public const CHAVE_SCHEDULER = 'scheduler';

    public const CHAVE_FILA = 'queue';

    public const CHAVE_SMTP = 'smtp';

    public const CHAVE_WORKER_UNIMED = 'automacao.unimed';

    /** Componente respondendo dentro do intervalo esperado. */
    public const ESTADO_SAUDAVEL = 'healthy';

    /** Atrasado, mas ainda dentro de tres vezes o intervalo. */
    public const ESTADO_ATENCAO = 'warning';

    /** Alem de tres vezes o intervalo, ou sem heartbeat nenhum. */
    public const ESTADO_FORA = 'down';

    /**
     * Multiplicador que separa atenção de fora. Uma rodada perdida por carga e
     * ruido; tres seguidas sao um padrao.
     */
    public const MULTIPLICADOR_FORA = 3;

    protected $table = 'saude_componentes';

    protected $fillable = [
        'tenant_id',
        'chave',
        'nome',
        'tipo',
        'convenio_id',
        'ultimo_heartbeat_em',
        'ultimo_status',
        'ultima_mensagem',
        'intervalo_esperado_segundos',
        'ativo',
    ];

    protected $casts = [
        'ultimo_heartbeat_em' => 'datetime',
        'intervalo_esperado_segundos' => 'integer',
        'ativo' => 'boolean',
    ];

    public function convenio()
    {
        return $this->belongsTo(Convenio::class);
    }
}
