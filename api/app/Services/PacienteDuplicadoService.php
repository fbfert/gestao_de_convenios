<?php

namespace App\Services;

use App\Models\Paciente;

/**
 * Relatório avulso de prováveis pacientes duplicados já cadastrados no
 * gescon (ex: "Abner dos Santos Beiger" em #518 e #30) — só leitura, quem
 * decide unir os cadastros é um humano fora do gescon-app por enquanto.
 */
class PacienteDuplicadoService
{
    /** Mesmo corte usado no match de sincronização (PacienteSyncService::CONFIANCA_MINIMA). */
    private const CONFIANCA_MINIMA = 90;

    /**
     * @return array<int, array{paciente_a: array, paciente_b: array, similaridade: float}>
     */
    public function buscar(int $tenantId): array
    {
        $pacientes = Paciente::with('convenio')
            ->where('tenant_id', $tenantId)
            ->where('ativo', true)
            ->get(['id', 'nome', 'cpf', 'carteirinha', 'convenio_id', 'clinica_id']);

        $pares = [];

        foreach ($pacientes as $i => $a) {
            $needle = mb_strtolower($this->semAcento($a->nome));

            foreach ($pacientes as $j => $b) {
                if ($j <= $i) {
                    continue;
                }

                similar_text($needle, mb_strtolower($this->semAcento($b->nome)), $percent);

                if ($percent < self::CONFIANCA_MINIMA) {
                    continue;
                }

                $pares[] = [
                    'paciente_a' => $this->resumir($a),
                    'paciente_b' => $this->resumir($b),
                    'similaridade' => round($percent, 1),
                ];
            }
        }

        usort($pares, fn ($x, $y) => $y['similaridade'] <=> $x['similaridade']);

        return $pares;
    }

    private function resumir(Paciente $paciente): array
    {
        return [
            'id' => $paciente->id,
            'nome' => $paciente->nome,
            'cpf' => $paciente->cpf,
            'carteirinha' => $paciente->carteirinha,
            'convenio' => $paciente->convenio?->nome,
            'vinculado_clinica' => $paciente->clinica_id !== null,
        ];
    }

    private function semAcento(string $valor): string
    {
        return iconv('UTF-8', 'ASCII//TRANSLIT', $valor) ?: $valor;
    }
}
