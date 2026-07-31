<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateUnimedSettingsRequest;
use App\Http\Resources\UnimedSettingsResource;
use App\Models\AuditLog;
use App\Models\Convenio;
use App\Models\UnimedRdaCredential;
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

            $credential->save();

            AuditLog::query()->create([
                'tenant_id' => $tenantId,
                'user_id' => $request->user()?->id,
                'acao' => 'unimed_rda_settings.updated',
                'entidade' => 'unimed_rda_credentials',
                'entidade_id' => $credential->id,
                'payload' => [
                    'convenio_id' => $convenioId,
                    'login' => $credential->login,
                    'base_url' => $credential->base_url,
                    'ativo' => $credential->ativo,
                    'senha_atualizada' => filled($password),
                ],
            ]);
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

        $credential->forceFill([
            'ativo' => true,
            'automation_paused_at' => null,
            'automation_paused_reason' => null,
        ])->save();

        AuditLog::query()->create([
            'tenant_id' => $tenantId,
            'user_id' => request()->user()?->id,
            'acao' => 'unimed_rda.automation_reactivated',
            'entidade' => 'unimed_rda_credentials',
            'entidade_id' => $credential->id,
            'payload' => ['reativado' => true],
        ]);

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
