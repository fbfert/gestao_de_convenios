<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

class LancamentoTranscricaoService
{
    public function extrair(string $transcricao): array
    {
        $texto = preg_replace("/\r\n?/", "\n", trim($transcricao));
        $texto = preg_replace('/[ \t]+/u', ' ', $texto);

        return [
            'cabecalho' => [
                'guia_numero' => $this->extrairCampo($texto, 'GUIA Nº') ?? $this->extrairCampo($texto, 'GUIA N') ?? null,
                'clinica' => $this->extrairCampo($texto, 'Clínica') ?? null,
                'paciente' => $this->extrairCampo($texto, 'Paciente') ?? null,
                'numero_cartao' => $this->extrairCampo($texto, 'Número Cartão') ?? null,
                'profissional_executante' => $this->extrairCampo($texto, 'Profissional Executante') ?? null,
                'terapia_aplicada' => $this->extrairCampo($texto, 'Terapia aplicada') ?? null,
            ],
            'sessoes' => $this->extrairSessoes($texto),
        ];
    }

    private function extrairCampo(string $texto, string $rotulo): ?string
    {
        if (! preg_match('/'.preg_quote($rotulo, '/').'\s*:\s*(.+?)(?:\n|$)/iu', $texto, $matches)) {
            return null;
        }

        $valor = trim($matches[1]);

        return $valor !== '' ? $valor : null;
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private function extrairSessoes(string $texto): array
    {
        $secao = $texto;

        if (preg_match('/Sessões\s*(.+)$/isu', $texto, $matches)) {
            $secao = trim($matches[1]);
        }

        if ($secao === '') {
            return [];
        }

        preg_match_all(
            '/(?:^|\s)(?<indice>\d{1,2})?\s*(?<data>\d{2}\/\d{2}\/\d{2,4})\s*(?<hora_inicio>\d{2}:\d{2})\s*(?<hora_fim>\d{2}:\d{2})\s*(?<resto>.*?)(?=(?:\s(?:\d{1,2}\s*)?\d{2}\/\d{2}\/\d{2,4}\s*\d{2}:\d{2}\s*\d{2}:\d{2})|$)/us',
            $secao,
            $matches,
            PREG_SET_ORDER
        );

        $sessao = [];

        foreach ($matches as $match) {
            $resto = trim((string) ($match['resto'] ?? ''));
            [$acompanhante, $resumo] = $this->separarAcompanhanteEResumo($resto);

            $sessao[] = [
                'data_sessao' => $this->normalizarData($match['data']),
                'hora_inicio' => $match['hora_inicio'],
                'hora_fim' => $match['hora_fim'],
                'acompanhante' => $acompanhante,
                'resumo_atividades' => $resumo,
            ];
        }

        return $sessao;
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function separarAcompanhanteEResumo(string $resto): array
    {
        if ($resto === '') {
            return [null, null];
        }

        $tokens = preg_split('/\s+/u', $resto, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($tokens) >= 3) {
            return [
                $tokens[0].' '.$tokens[1],
                implode(' ', array_slice($tokens, 2)),
            ];
        }

        if (count($tokens) === 2) {
            return [$tokens[0], $tokens[1]];
        }

        return [null, $resto];
    }

    private function normalizarData(string $data): string
    {
        $formato = strlen(explode('/', $data)[2] ?? '') === 4 ? 'd/m/Y' : 'd/m/y';
        $carbon = Carbon::createFromFormat($formato, $data);

        if (! $carbon) {
            throw new RuntimeException("Não foi possível interpretar a data {$data}.");
        }

        return $carbon->toDateString();
    }
}
