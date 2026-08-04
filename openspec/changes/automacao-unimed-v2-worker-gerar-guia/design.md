## Context

O worker Unimed atual é um servidor HTTP Node mínimo que retorna `{status: "succeeded", mock: true}` para qualquer operação. No Laravel, `ExecutarAutomacaoUnimedJob` já resolve a operação `gerar_guia`, chama `UnimedWorkerClient`, aplica circuit breaker e delega a persistência para `GerarGuiaUnimedService`.

A Etapa 1 adicionou os campos que faltavam para preencher o portal: CID, carteirinha normalizada, mapeamento de procedimento e mapeamento de profissional executante. Esta etapa deve trocar o mock por uma automação Playwright testável por fixtures locais, sem acessar `rda.unimedsc.com.br`.

## Goals / Non-Goals

**Goals:**
- Implementar `operation=gerar_guia` no worker usando Playwright/Chromium headless.
- Cobrir o fluxo de login, beneficiário, SP/SADT, prestador solicitante, procedimento, anexos, profissional executante, finalização e leitura do resultado.
- Retornar um contrato estável para o Laravel persistir `numero_guia`, `protocolo_operadora`, `sessoes_solicitadas`, `sessoes_autorizadas`, `senha`, `guia_status` e `unimed_status`.
- Criar testes locais baseados em fixtures HTML que não dependem de rede externa.
- Tratar resultado incerto após o submit final sem retry automático.

**Non-Goals:**
- Implementar consulta de status ou captura de senha/validade em lote.
- Acessar o portal real em desenvolvimento ou testes automatizados.
- Persistir credenciais ou acessar o banco a partir do worker.
- Criar novo scheduler ou alterar a política avançada de circuit breaker.

## Decisions

1. **Worker em módulos pequenos mantendo servidor HTTP simples**
   - O `src/server.js` continuará expondo `/health` e `/operations/:operation`, mas delegará `gerar_guia` para módulos específicos.
   - Alternativa considerada: trocar para Express. Rejeitada nesta etapa para evitar dependência desnecessária; o servidor atual atende ao contrato.

2. **Playwright com fixture router nos testes**
   - A automação será escrita contra `Page`/`BrowserContext` e os testes usarão HTML local via rotas ou arquivos fixtures.
   - Alternativa considerada: testar apenas funções de parser. Rejeitada porque os riscos principais estão na navegação e nos estados do DOM.

3. **Sem retry depois de `Finalizar e Gerar guia`**
   - Antes do submit, waits e retries de navegação/elementos podem ser usados com limites; depois do submit final, timeout ou resposta ambígua retorna `status: "uncertain"` e erro `UNCERTAIN_AFTER_SUBMIT`.
   - Isso preserva idempotência operacional e evita duplicar guia no portal.

4. **Códigos de erro explícitos por categoria**
   - Erros de negócio por item retornam `status: "succeeded"` quando produzem guia local verificável, como `needs_verification`, ou `status: "failed"` com `error_code` quando não devem criar guia.
   - Erros estruturais usam códigos estáveis para o circuit breaker futuro: `LOGIN_ERROR`, `PORTAL_STRUCTURE_CHANGED`, `PORTAL_UNAVAILABLE`, `WORKER_INTERNAL_FATAL`.

5. **Payload sem segredo em log**
   - O worker recebe credenciais em memória por requisição, nunca grava em fixture, snapshot, log ou resposta.
   - Logs/eventos devem usar redatores existentes no Laravel e mensagens sem carteirinha completa.

## Risks / Trade-offs

- [Portal real divergir das fixtures] -> A etapa 5 fará homologação assistida; os seletores devem ser centralizados para ajuste pontual.
- [Playwright aumentar instalação do worker] -> Dependência limitada ao `worker-unimed`, com script de teste/check local.
- [HTML de fixture ficar simplificado demais] -> Os fixtures devem representar estados e seletores citados no roteiro, inclusive mensagens de processamento, restrição, resultados de prestador e resultado final.
- [Timeout pós-submit mascarar sucesso real] -> Retornar `uncertain` e não criar/regerar guia automaticamente; operador deve confirmar antes de nova tentativa.

## Migration Plan

1. Adicionar Playwright e scripts do worker.
2. Implementar módulos de automação e fixtures/testes locais.
3. Ajustar payload/aplicação de resultado Laravel apenas onde o contrato exigir.
4. Rodar testes do worker, checks Node, testes Laravel focados, lint/build frontend se houver impacto.
5. Rollback: voltar o worker para o mock anterior e manter a Etapa 1, pois as alterações de dados são compatíveis.

## Open Questions

- Os seletores reais podem precisar de refinamento na Etapa 5, pois esta etapa não acessa o portal real.
- O armazenamento físico dos anexos já existe no Laravel, mas o worker só deve receber caminhos/URLs locais se o contrato atual permitir leitura segura; se faltar esse dado, esta etapa deve ajustar o payload sem expor segredo.
