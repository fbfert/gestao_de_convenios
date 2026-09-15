<?php

namespace App\Http\Resources;

use App\Models\Convenio;
use App\Models\ConvenioCredencial;
use App\Support\ConvenioDriverCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * O estado da credencial de um convênio, sem os segredos.
 *
 * Campo do tipo `password` volta como `preenchido: true|false`, nunca o valor —
 * é o que `UnimedSettingsResource` já fazia com `senha_configurada`,
 * generalizado para qualquer campo secreto de qualquer driver. Um driver novo
 * herda a proteção sem ninguém precisar lembrar dela.
 *
 * @property array{convenio: Convenio, credencial: ?ConvenioCredencial, driver: ?string} $resource
 */
class ConvenioCredencialResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Convenio $convenio */
        $convenio = $this->resource['convenio'];
        /** @var ConvenioCredencial|null $credencial */
        $credencial = $this->resource['credencial'];
        $driver = $this->resource['driver'];

        return [
            'convenio' => [
                'id' => $convenio->id,
                'nome' => $convenio->nome,
                'connector_type' => $convenio->connector_type,
                // Exposto para a tela poder dizer se a automação está ligada —
                // informação diferente de "tem credencial", e é essa distinção
                // que a change existe para deixar clara.
                'connector_driver' => $convenio->connector_driver,
                'ativo' => $convenio->ativo,
            ],
            'driver' => $driver,
            'catalogo' => ConvenioDriverCatalog::paraResposta($driver),
            'credencial' => $credencial ? [
                'id' => $credencial->id,
                'driver' => $credencial->driver,
                'ativo' => $credencial->ativo,
                'pronta' => $credencial->pronta(),
                'campos' => $this->campos($credencial),
                'automation_paused_at' => $credencial->automation_paused_at?->toISOString(),
                'automation_paused_reason' => $credencial->automation_paused_reason,
                'updated_at' => $credencial->updated_at?->toISOString(),
            ] : null,
        ];
    }

    /**
     * Valor dos campos não secretos; para os secretos, só se estão preenchidos.
     *
     * @return array<string, mixed>
     */
    private function campos(ConvenioCredencial $credencial): array
    {
        $secretas = ConvenioDriverCatalog::chavesSecretas($credencial->driver);
        $campos = [];

        foreach (ConvenioDriverCatalog::chaves($credencial->driver) as $chave) {
            $campos[$chave] = in_array($chave, $secretas, true)
                ? ['preenchido' => $credencial->campo($chave) !== null]
                : ['valor' => $credencial->campo($chave)];
        }

        return $campos;
    }
}
