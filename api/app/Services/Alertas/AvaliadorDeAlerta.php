<?php

namespace App\Services\Alertas;

use App\Models\AlertaRegra;

/**
 * Uma regra de alerta.
 *
 * O job nao conhece nenhuma implementacao: resolve pela chave (ver
 * ResolvedorDeRegras). Regra nova e uma classe e uma linha de seed — mesmo
 * raciocinio do ADR-02 para conectores de convenio.
 */
interface AvaliadorDeAlerta
{
    /**
     * O que DEVERIA estar aberto agora para este tenant.
     *
     * Devolve o conjunto completo, e nao o delta: e o que permite ao job
     * resolver automaticamente tudo que esta aberto e nao veio aqui. Devolver
     * delta faria o fechamento automatico impossivel.
     *
     * Cada item:
     *   nivel        verde|amarelo|vermelho
     *   titulo       linha curta que a tela mostra
     *   descricao    contexto opcional
     *   entidade     nome da tabela alvo, quando ha uma
     *   entidade_id  id do alvo, quando ha um
     *   dados        payload livre para a tela
     *
     * @return array<int, array{nivel: string, titulo: string, descricao?: string|null, entidade?: string|null, entidade_id?: int|null, dados?: array|null}>
     */
    public function avaliar(int $tenantId, AlertaRegra $regra): array;
}
