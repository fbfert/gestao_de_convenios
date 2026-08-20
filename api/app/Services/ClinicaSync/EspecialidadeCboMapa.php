<?php

namespace App\Services\ClinicaSync;

/**
 * De-para entre o nome livre de especialidade do gescon e o código CBO do
 * clinica — os dois sistemas não compartilham uma chave natural aqui (mesmo
 * mapeamento usado na importação manual de profissionais em 20/08/2026).
 *
 * Novo par: acrescentar aqui. Especialidade sem entrada aqui não impede o
 * profissional de ser criado — só fica sem cbos_id do lado do clinica (campo
 * opcional lá), reportado como pendência no resumo da execução.
 */
class EspecialidadeCboMapa
{
    private const MAPA = [
        'Fisioterapia ABA' => '223605',
        'Fonoaudiologia ABA' => '223810',
        'Fonoaudiologia' => '223810',
        'Nutricionista' => '223710',
        'Psicologia ABA' => '251510',
        'Psicologia Convencional' => '251510',
        'Psicoterapia individual' => '251510',
        'Psicopedagogia ABA' => '2394-05',
        'Terapia ocupacional' => '223905',
        'Terapia Ocupacional' => '223905',
    ];

    public static function codigoCboDe(string $nomeEspecialidade): ?string
    {
        return self::MAPA[$nomeEspecialidade] ?? null;
    }

    /** Primeiro nome de especialidade do gescon cadastrado para o código CBO dado. */
    public static function especialidadeDoCbo(string $codigoCbo): ?string
    {
        $chave = array_search($codigoCbo, self::MAPA, true);

        return $chave === false ? null : $chave;
    }
}
