<?php

namespace App\Services\ClinicaSync;

use App\Models\ClinicaPacientePendente;
use App\Models\Paciente;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Resolve as pendências abertas por PacienteSyncService::interceptarSemMatchExato
 * — um humano decide se o paciente vindo da clinica é a mesma pessoa que um
 * candidato já cadastrado no gescon, ou se é gente diferente.
 */
class ClinicaPacientePendenteService
{
    private const TIPOS_TELEFONE = ['telefone', 'celular', 'whatsapp'];

    /**
     * Vincula: só preenche o que estiver vazio no gescon (cpf, nascimento);
     * telefone (contato) é atualizado mesmo se já preenchido; carteirinha,
     * nome e ativo NUNCA são tocados aqui — o gescon é a fonte de billing e o
     * match já validou a pessoa por similaridade, não por dado oficial.
     */
    public function confirmar(ClinicaPacientePendente $pendencia, int $pacienteId): Paciente
    {
        if ($pendencia->status !== 'pendente') {
            throw new InvalidArgumentException('Pendência já foi resolvida.');
        }

        $paciente = Paciente::where('tenant_id', $pendencia->tenant_id)->where('id', $pacienteId)->first();

        if ($paciente === null) {
            throw new InvalidArgumentException('Paciente não encontrado neste tenant.');
        }

        if ($paciente->clinica_id !== null) {
            throw new InvalidArgumentException('Este paciente já está vinculado a outro registro da clinica.');
        }

        $remoto = $pendencia->dados_remoto;
        $remotoAtualizadoEm = $pendencia->remoto_atualizado_em;

        $dados = [];

        if (empty($paciente->cpf) && ! empty($remoto['cpf'])) {
            $dados['cpf'] = $this->somenteDigitos($remoto['cpf']);
        }

        if ($paciente->data_nascimento === null && ! empty($remoto['nascimento'])) {
            $dados['data_nascimento'] = $remoto['nascimento'];
        }

        $telefone = $this->extrairTelefone($remoto['contatos_json'] ?? []);
        if (! empty($telefone)) {
            $dados['telefone'] = $telefone;
        }

        $paciente->timestamps = false;
        $paciente->forceFill([
            ...$dados,
            'clinica_id' => $pendencia->clinica_id,
            'updated_at' => $remotoAtualizadoEm,
            'sincronizado_em' => $remotoAtualizadoEm,
            'clinica_status' => null,
        ])->save();

        $pendencia->forceFill([
            'status' => 'confirmado',
            'candidato_paciente_id' => $paciente->id,
            'resolvido_por' => request()->user()?->id,
            'resolvido_em' => CarbonImmutable::now(),
        ])->save();

        return $paciente->fresh();
    }

    /** Rejeita: são pessoas diferentes — a próxima sincronização cria um paciente novo normalmente. */
    public function rejeitar(ClinicaPacientePendente $pendencia): void
    {
        if ($pendencia->status !== 'pendente') {
            throw new InvalidArgumentException('Pendência já foi resolvida.');
        }

        $pendencia->forceFill([
            'status' => 'rejeitado',
            'resolvido_por' => request()->user()?->id,
            'resolvido_em' => CarbonImmutable::now(),
        ])->save();
    }

    private function extrairTelefone(array $contatosJson): ?string
    {
        foreach ($contatosJson as $contato) {
            if (in_array($contato['tipo'] ?? null, self::TIPOS_TELEFONE, true)) {
                return $contato['valor'] ?? null;
            }
        }

        return null;
    }

    private function somenteDigitos(string $valor): string
    {
        return preg_replace('/\D/', '', $valor) ?? '';
    }
}
