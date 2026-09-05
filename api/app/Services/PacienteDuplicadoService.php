<?php

namespace App\Services;

use App\Models\Paciente;

/**
 * Detecta prováveis pacientes duplicados já cadastrados no gescon (ex:
 * "Abner dos Santos Beiger" em #518 e #30), por similaridade de nome — quem
 * decide qual vira o vencedor é um humano, via PacienteMergeService.
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
        // SEM filtro de ativo de propósito: é comum a duplicata já ter sido
        // "resolvida" desativando um dos dois lados sem migrar o histórico
        // (foi exatamente o que aconteceu com Abner #518/#30) — se filtrasse
        // por ativo=true o caso que motivou este relatório nunca apareceria.
        // whereNull(mesclado_em_id): par já resolvido por PacienteMergeService
        // não deve voltar a aparecer — o perdedor continua na base (ativo=false)
        // mas o caso já está encerrado.
        $pacientes = Paciente::with('convenio')
            ->where('tenant_id', $tenantId)
            ->whereNull('mesclado_em_id')
            ->get(['id', 'nome', 'cpf', 'carteirinha', 'convenio_id', 'clinica_id', 'ativo']);

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
            'clinica_id' => $paciente->clinica_id,
            'vinculado_clinica' => $paciente->clinica_id !== null,
            'ativo' => $paciente->ativo,
        ];
    }

    private function semAcento(string $valor): string
    {
        return iconv('UTF-8', 'ASCII//TRANSLIT', $valor) ?: $valor;
    }
}
