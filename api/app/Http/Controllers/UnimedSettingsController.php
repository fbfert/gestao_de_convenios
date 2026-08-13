<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateUnimedSettingsRequest;
use App\Http\Resources\UnimedSettingsResource;
use App\Models\Convenio;
use App\Models\UnimedRdaCredential;
use App\Support\Auditoria;
use App\Services\Automation\UnimedWorkerClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class UnimedSettingsController extends Controller
{
    private const DRIVER = 'unimed_rda';

    public function show(): UnimedSettingsResource
    {
        $tenantId = (int) request()->user()->tenant_id;

        return $this->resource($tenantId);
    }

    public function update(UpdateUnimedSettingsRequest $request): UnimedSettingsResource
    {
        $tenantId = (int) $request->user()->tenant_id;
        $payload = $request->validated();

        DB::transaction(function () use ($payload, $request, $tenantId) {
            Convenio::query()
                ->where('tenant_id', $tenantId)
                ->where('connector_driver', self::DRIVER)
                ->update(['connector_driver' => null]);

            $convenioId = $payload['convenio_id'] ?? null;
            if ($convenioId) {
                Convenio::query()
                    ->where('tenant_id', $tenantId)
                    ->whereKey($convenioId)
                    ->update([
                        'connector_type' => 'scraping',
                        'connector_driver' => self::DRIVER,
                    ]);
            }

            $credentialPayload = $payload['credential'];
            $password = Arr::pull($credentialPayload, 'password');
            $credential = UnimedRdaCredential::query()->firstOrNew(['tenant_id' => $tenantId]);
            $credential->fill($credentialPayload);

            if (filled($password)) {
                $credential->password = $password;
            }

            // O evento explicito abaixo diz mais do que o diff cru do model
            // (ele amarra o convenio escolhido), entao o automatico e
            // suspenso para a mesma acao nao virar dois registros.
            Auditoria::semRegistroAutomatico(fn () => $credential->save());

            Auditoria::registrar(
                acao: 'unimed_rda_settings.updated',
                entidade: 'unimed_rda_credentials',
                entidadeId: (int) $credential->id,
                payload: array_filter([
                    'convenio_id' => $convenioId,
                    'login' => $credential->login,
                    'base_url' => $credential->base_url,
                    'ativo' => $credential->ativo,
                    // A senha nunca entra, nem a antiga nem a nova: fica
                    // registrado que mudou, e quem mudou vem do autor.
                    'campos_ocultos' => filled($password) ? ['password'] : null,
                ], fn ($valor) => $valor !== null),
                tenantId: $tenantId,
                userId: $request->user()?->id,
            );
        });

        return $this->resource($tenantId);
    }

    public function health(UnimedWorkerClient $worker): JsonResponse
    {
        try {
            return response()->json([
                'data' => [
                    'status' => 'available',
                    'worker' => $worker->health(),
                ],
            ]);
        } catch (Throwable) {
            return response()->json([
                'data' => [
                    'status' => 'unavailable',
                    'worker' => null,
                ],
            ]);
        }
    }

    public function reativar(): UnimedSettingsResource
    {
        $tenantId = (int) request()->user()->tenant_id;
        $credential = UnimedRdaCredential::query()->where('tenant_id', $tenantId)->firstOrFail();

        // Mesma razao do update: o evento explicito diz "automacao reativada",
        // que e o que o operador procura na trilha; o diff diria apenas
        // `ativo: false -> true`.
        Auditoria::semRegistroAutomatico(fn () => $credential->forceFill([
            'ativo' => true,
            'automation_paused_at' => null,
            'automation_paused_reason' => null,
        ])->save());

        Auditoria::registrar(
            acao: 'unimed_rda.automation_reactivated',
            entidade: 'unimed_rda_credentials',
            entidadeId: (int) $credential->id,
            payload: ['reativado' => true],
            tenantId: $tenantId,
        );

        return $this->resource($tenantId);
    }

    private function resource(int $tenantId): UnimedSettingsResource
    {
        $convenios = Convenio::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('nome')
            ->get();

        return new UnimedSettingsResource([
            'credential' => UnimedRdaCredential::query()->where('tenant_id', $tenantId)->first(),
            'convenio_id' => $convenios->firstWhere('connector_driver', self::DRIVER)?->id,
            'convenios' => $convenios,
        ]);
    }
}
