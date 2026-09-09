<?php

namespace App\Services\Alertas;

use App\Models\AlertaRegra;
use App\Services\Alertas\Regras\AutomacaoFalhasEmSerie;
use App\Services\Alertas\Regras\ComponenteFora;
use App\Services\Alertas\Regras\GuiaNegada;
use App\Services\Alertas\Regras\SenhaVencendo;

/**
 * Chave da regra -> classe que a avalia.
 *
 * E o unico lugar que precisa mudar quando entra regra nova; o job nao conhece
 * implementacao nenhuma. Mesmo raciocinio do ADR-02 para conectores.
 *
 * Chave sem implementacao registrada nao explode: e ignorada. Assim, uma linha
 * de `alerta_regras` que sobrou de um deploy anterior nao derruba a avaliacao
 * das outras regras.
 */
class ResolvedorDeRegras
{
    private const MAPA = [
        AlertaRegra::CHAVE_SENHA_VENCENDO => SenhaVencendo::class,
        AlertaRegra::CHAVE_GUIA_NEGADA => GuiaNegada::class,
        AlertaRegra::CHAVE_AUTOMACAO_FALHAS => AutomacaoFalhasEmSerie::class,
        AlertaRegra::CHAVE_COMPONENTE_FORA => ComponenteFora::class,
    ];

    public function para(string $chave): ?AvaliadorDeAlerta
    {
        $classe = self::MAPA[$chave] ?? null;

        return $classe ? app($classe) : null;
    }

    /** @return string[] */
    public static function chaves(): array
    {
        return array_keys(self::MAPA);
    }
}
