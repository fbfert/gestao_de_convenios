<?php

namespace App\Services;

use App\Models\ClinicaPacientePendente;
use App\Models\Guia;
use App\Models\Antecipacao;
use App\Models\Paciente;
use App\Models\PacienteArquivo;
use App\Models\PacienteDocumento;
use App\Models\PacienteImportLinha;
use App\Models\PacienteTelefone;
use App\Models\Solicitacao;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Unifica dois cadastros de paciente duplicados (ver PacienteDuplicadoService).
 * O perdedor nunca é apagado — fica ativo=false e marcado como mesclado no
 * vencedor. `paciente_id` está denormalizado em solicitacoes/guias/
 * antecipacoes de forma independente (não herda via solicitacao_id/guia_id),
 * por isso as três precisam ser repontadas separadamente. `lancamentos` não
 * tem paciente_id próprio (pendura em antecipacao_id) — segue sozinho.
 */
class PacienteMergeService
{
    /**
     * @return array{solicitacoes: int, guias: int, antecipacoes: int, telefones: int, documentos: int, arquivos: int}
     */
    public function preview(int $tenantId, int $vencedorId, int $perdedorId): array
    {
        [$vencedor, $perdedor] = $this->carregarPar($tenantId, $vencedorId, $perdedorId);

        return [
            'solicitacoes' => Solicitacao::where('tenant_id', $tenantId)->where('paciente_id', $perdedor->id)->count(),
            'guias' => Guia::where('tenant_id', $tenantId)->where('paciente_id', $perdedor->id)->count(),
            'antecipacoes' => Antecipacao::where('tenant_id', $tenantId)->where('paciente_id', $perdedor->id)->count(),
            'telefones' => PacienteTelefone::where('tenant_id', $tenantId)->where('paciente_id', $perdedor->id)->count(),
            'documentos' => PacienteDocumento::where('tenant_id', $tenantId)->where('paciente_id', $perdedor->id)->count(),
            'arquivos' => PacienteArquivo::where('tenant_id', $tenantId)->where('paciente_id', $perdedor->id)->count(),
            'conflito_clinica_id' => $this->conflitaClinicaId($vencedor, $perdedor),
        ];
    }

    public function mesclar(int $tenantId, int $vencedorId, int $perdedorId, ?int $clinicaIdEscolhido = null): Paciente
    {
        [$vencedor, $perdedor] = $this->carregarPar($tenantId, $vencedorId, $perdedorId);

        if ($this->conflitaClinicaId($vencedor, $perdedor) && $clinicaIdEscolhido === null) {
            throw new InvalidArgumentException('Os dois cadastros têm vínculos diferentes com a clinica — escolha qual manter.');
        }

        if ($clinicaIdEscolhido !== null && ! in_array($clinicaIdEscolhido, [$vencedor->clinica_id, $perdedor->clinica_id], true)) {
            throw new InvalidArgumentException('O vínculo escolhido não pertence a nenhum dos dois cadastros.');
        }

        DB::transaction(function () use ($tenantId, $vencedor, $perdedor, $clinicaIdEscolhido) {
            foreach ([Solicitacao::class, Guia::class, Antecipacao::class, PacienteTelefone::class, PacienteDocumento::class, PacienteArquivo::class] as $modelo) {
                $modelo::where('tenant_id', $tenantId)->where('paciente_id', $perdedor->id)
                    ->get()
                    ->each(fn ($registro) => $registro->update(['paciente_id' => $vencedor->id]));
            }

            PacienteImportLinha::where('tenant_id', $tenantId)->where('matched_paciente_id', $perdedor->id)
                ->get()
                ->each(fn ($linha) => $linha->update(['matched_paciente_id' => $vencedor->id]));

            ClinicaPacientePendente::where('tenant_id', $tenantId)->where('candidato_paciente_id', $perdedor->id)
                ->get()
                ->each(fn ($pendencia) => $pendencia->update(['candidato_paciente_id' => $vencedor->id]));

            $dadosVencedor = [];

            if (empty($vencedor->cpf) && ! empty($perdedor->cpf)) {
                $dadosVencedor['cpf'] = $perdedor->cpf;
            }
            if ($vencedor->data_nascimento === null && $perdedor->data_nascimento !== null) {
                $dadosVencedor['data_nascimento'] = $perdedor->data_nascimento;
            }
            if (! empty($perdedor->telefone)) {
                $dadosVencedor['telefone'] = $perdedor->telefone;
            }

            $dadosVencedor['clinica_id'] = $clinicaIdEscolhido
                ?? $vencedor->clinica_id
                ?? $perdedor->clinica_id;

            // Perdedor libera o clinica_id ANTES do vencedor assumi-lo: os dois
            // podem ter o mesmo valor até aqui, e a ordem inversa esbarra na
            // unique(tenant_id, clinica_id) mesmo sendo uma transição válida.
            $perdedor->forceFill([
                'ativo' => false,
                'clinica_id' => null,
                'mesclado_em_id' => $vencedor->id,
                'mesclado_em' => CarbonImmutable::now(),
            ])->save();

            $vencedor->forceFill($dadosVencedor)->save();
        });

        return $vencedor->fresh(['convenio', 'telefones']);
    }

    /**
     * @return array{0: Paciente, 1: Paciente}
     */
    private function carregarPar(int $tenantId, int $vencedorId, int $perdedorId): array
    {
        if ($vencedorId === $perdedorId) {
            throw new InvalidArgumentException('Os dois lados do par precisam ser cadastros diferentes.');
        }

        $vencedor = Paciente::where('tenant_id', $tenantId)->find($vencedorId);
        $perdedor = Paciente::where('tenant_id', $tenantId)->find($perdedorId);

        if ($vencedor === null || $perdedor === null) {
            throw new InvalidArgumentException('Paciente não encontrado neste tenant.');
        }

        if ($vencedor->mesclado_em_id !== null || $perdedor->mesclado_em_id !== null) {
            throw new InvalidArgumentException('Um dos dois cadastros já foi mesclado antes — não dá pra mesclar de novo.');
        }

        return [$vencedor, $perdedor];
    }

    private function conflitaClinicaId(Paciente $vencedor, Paciente $perdedor): bool
    {
        return $vencedor->clinica_id !== null
            && $perdedor->clinica_id !== null
            && $vencedor->clinica_id !== $perdedor->clinica_id;
    }
}
