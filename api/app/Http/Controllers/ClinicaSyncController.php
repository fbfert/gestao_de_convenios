<?php

namespace App\Http\Controllers;

use App\Models\ClinicaConexaoConfig;
use App\Models\ClinicaPacientePendente;
use App\Models\ClinicaSyncExecucao;
use App\Models\Paciente;
use App\Services\ClinicaSync\ClinicaPacientePendenteService;
use App\Services\ClinicaSync\ClinicaSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ClinicaSyncController extends Controller
{
    public function show(): JsonResponse
    {
        $tenantId = (int) request()->user()->tenant_id;

        $config = ClinicaConexaoConfig::where('tenant_id', $tenantId)->first();
        $ultima = ClinicaSyncExecucao::where('tenant_id', $tenantId)->latest('iniciado_em')->first();

        return response()->json([
            'configurado' => $config !== null && $config->ativo,
            'base_url' => $config?->base_url,
            'ultima_execucao' => $ultima === null ? null : $this->serializar($ultima),
        ]);
    }

    /**
     * Roda SÍNCRONO (não enfileira): quem clicou "Sincronizar Agora" quer o resultado
     * na hora, não um status pra ficar checando. O agendamento de 5 em 5 minutos
     * (routes/console.php) é quem usa a fila.
     */
    public function sincronizar(ClinicaSyncService $service): JsonResponse
    {
        $tenantId = (int) request()->user()->tenant_id;
        $execucao = $service->executar($tenantId, 'manual');

        return response()->json($this->serializar($execucao), $execucao->status === 'ok' ? 200 : 502);
    }

    /** Pendências de vínculo abertas: paciente parecido, aguardando confirmação humana. */
    public function pendencias(): JsonResponse
    {
        $tenantId = (int) request()->user()->tenant_id;

        $pendencias = ClinicaPacientePendente::where('tenant_id', $tenantId)
            ->where('status', 'pendente')
            ->orderBy('created_at')
            ->get();

        $candidatoIds = $pendencias->flatMap(fn (ClinicaPacientePendente $p) => collect($p->candidatos_json ?? [])->pluck('id'))->unique();

        $candidatos = Paciente::with('convenio')
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $candidatoIds)
            ->get()
            ->keyBy('id');

        return response()->json($pendencias->map(function (ClinicaPacientePendente $p) use ($candidatos) {
            return [
                'id' => $p->id,
                'clinica_id' => $p->clinica_id,
                'nome_remoto' => $p->dados_remoto['nome'] ?? null,
                'cpf_remoto' => $p->dados_remoto['cpf'] ?? null,
                'candidatos' => collect($p->candidatos_json ?? [])
                    ->map(function (array $c) use ($candidatos) {
                        $paciente = $candidatos->get($c['id']);

                        return $paciente === null ? null : [
                            'id' => $paciente->id,
                            'nome' => $paciente->nome,
                            'cpf' => $paciente->cpf,
                            'carteirinha' => $paciente->carteirinha,
                            'convenio' => $paciente->convenio?->nome,
                            'similaridade' => $c['similaridade'],
                        ];
                    })
                    ->filter()
                    ->values(),
            ];
        }));
    }

    public function confirmarPendencia(Request $request, ClinicaPacientePendente $pendencia, ClinicaPacientePendenteService $service): JsonResponse
    {
        $dados = $request->validate(['paciente_id' => ['required', 'integer']]);

        try {
            $paciente = $service->confirmar($pendencia, (int) $dados['paciente_id']);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['paciente_id' => [$e->getMessage()]]);
        }

        return response()->json(['paciente_id' => $paciente->id]);
    }

    public function rejeitarPendencia(ClinicaPacientePendente $pendencia, ClinicaPacientePendenteService $service): JsonResponse
    {
        try {
            $service->rejeitar($pendencia);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['pendencia' => [$e->getMessage()]]);
        }

        return response()->json(['status' => 'rejeitado']);
    }

    private function serializar(ClinicaSyncExecucao $execucao): array
    {
        return [
            'origem' => $execucao->origem,
            'status' => $execucao->status,
            'iniciado_em' => $execucao->iniciado_em,
            'finalizado_em' => $execucao->finalizado_em,
            'resumo' => $execucao->resumo,
            'erro_mensagem' => $execucao->erro_mensagem,
        ];
    }
}
