## Context

A geração de guia Unimed v2 já usa worker local com Playwright para `gerar_guia`. A consulta de status e a captura de senha/validade ainda precisam ser operações reais e separadas, sem acesso ao portal real durante desenvolvimento. O Laravel permanece fonte de verdade para credenciais, elegibilidade, filas e persistência; o worker recebe payloads efêmeros e não acessa banco.

## Goals / Non-Goals

**Goals:**

- Implementar `consult_status_batch` para consultar situação de guias elegíveis pelo fluxo de beneficiário e localizar guia.
- Implementar `capture_authorization_data_batch` para percorrer "Exames em aberto", localizar guias aprovadas e capturar senha/validade.
- Separar services Laravel, payloads, aplicação de resultados e scheduler por operação.
- Manter idempotência, lock por tenant e continuação do lote após erro individual.
- Cobrir o worker por fixtures HTML locais e documentar os estados.

**Non-Goals:**

- Não alterar o fluxo de geração de guia, exceto integrações inevitáveis de operação/contrato.
- Não acessar `rda.unimedsc.com.br` nesta etapa.
- Não implementar circuit breaker avançado ou limpeza do job legado, que ficam para a Etapa 4.

## Decisions

- **Operações separadas no worker e backend.** `consult_status_batch` atualiza status e `capture_authorization_data_batch` atualiza apenas senha/validade. Alternativa considerada: manter uma operação única de consulta. Foi rejeitada porque as elegibilidades e os efeitos persistidos são diferentes.
- **Payload batch com resultados por item.** O worker fará login uma vez por lote e retornará outcomes por guia. Isso reduz custo de navegação e permite continuar após falhas individuais.
- **Atualização conservadora de senha/validade.** A captura não sobrescreve valores existentes com vazio. Alternativa: espelhar integralmente o portal. Foi rejeitada para evitar perda de autorização por leitura incompleta.
- **Paginação por links reais.** A varredura de "Exames em aberto" usará links presentes no DOM, sem dynaHash hardcoded ou número fixo de páginas.
- **`unimed_last_checked_at` somente em consulta conclusiva.** Tentativas falhas não devem parecer verificação válida.

## Risks / Trade-offs

- Mudança de estrutura do portal nas telas de consulta -> fixtures podem não capturar a diferença; mitigação: Etapa 5 de homologação real e Etapa 4 para ampliar circuit breaker.
- Batch longo com muitas guias -> operação pode demorar; mitigação: manter limites de batch existentes ou configurar limite por job se já houver padrão local.
- Guia homônima ou carteirinha divergente em "Exames em aberto" -> usar `numero_guia` como chave exata e nome/carteirinha apenas como validação auxiliar.

## Migration Plan

1. Adicionar operações no worker e testes por fixtures.
2. Separar services Laravel e ajustar job/scheduler/manual actions.
3. Atualizar UI de Guias e documentação.
4. Validar com testes automatizados, build/lint relevantes e `openspec validate`.

Rollback: reverter o commit da Etapa 3 mantém a Etapa 2 funcionando; guias já geradas permanecem persistidas e a captura de senha/status volta ao comportamento anterior.
