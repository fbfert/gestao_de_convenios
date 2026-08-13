<?php

namespace App\Concerns;

use App\Support\Auditoria;

/**
 * Registra na trilha de auditoria a criação, a alteração e a exclusão do
 * modelo, sem precisar de chamada em controller nenhum.
 *
 * Um modelo pode declarar campos sensíveis próprios:
 *
 *     protected array $auditOcultos = ['api_key'];
 *
 * A lista é somada aos padrões de nome do App\Support\Auditoria — declarar é
 * para o caso em que o nome do campo não denuncia o conteúdo.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            if (! Auditoria::automaticoLigado()) {
                return;
            }

            [, $depois, $ocultos] = Auditoria::diff([], $model->getAttributes(), $model->camposOcultosDaAuditoria());

            Auditoria::registrarModelo($model, 'created', [], $depois, $ocultos);
        });

        static::updated(function ($model) {
            if (! Auditoria::automaticoLigado()) {
                return;
            }

            [$antes, $depois, $ocultos] = Auditoria::diff(
                $model->getOriginal(),
                $model->getChanges(),
                $model->camposOcultosDaAuditoria(),
            );

            // Gravação que não mudou campo nenhum não vira evento: `save()` sem
            // alteração e toque de timestamp encheriam a trilha de ruído.
            if ($antes === [] && $depois === [] && $ocultos === []) {
                return;
            }

            Auditoria::registrarModelo($model, 'updated', $antes, $depois, $ocultos);
        });

        static::deleted(function ($model) {
            if (! Auditoria::automaticoLigado()) {
                return;
            }

            // No delete o "antes" é o registro inteiro, e não um diff: sem isso
            // o evento diria que algo sumiu, sem dizer o quê.
            $declarados = $model->camposOcultosDaAuditoria();
            $antes = [];
            $ocultos = [];

            foreach ($model->getAttributes() as $campo => $valor) {
                if (Auditoria::ehSensivel($campo, $declarados)) {
                    $ocultos[] = $campo;

                    continue;
                }

                $antes[$campo] = $valor;
            }

            Auditoria::registrarModelo($model, 'deleted', $antes, [], $ocultos);
        });
    }

    /** @return string[] */
    public function camposOcultosDaAuditoria(): array
    {
        return property_exists($this, 'auditOcultos') ? $this->auditOcultos : [];
    }
}
