## 1. Worker Playwright

- [x] 1.1 Adicionar Playwright e scripts de check/test ao `worker-unimed`.
- [x] 1.2 Reestruturar o servidor para manter `/health` e delegar `gerar_guia` para módulo próprio.
- [x] 1.3 Implementar fluxo de login, beneficiário, atualização cadastral, restrição administrativa e SP/SADT com waits por estado do DOM.
- [x] 1.4 Implementar seleção de prestador solicitante com fallback CRM, nome e "nao cooperado" ativo.
- [x] 1.5 Implementar preenchimento de procedimento, campos genéricos, anexos e profissional executante.
- [x] 1.6 Implementar finalização sem retry pós-submit, parser de resultado e mapeamento de status.

## 2. Fixtures And Tests

- [x] 2.1 Criar fixtures HTML locais para login, cartão/beneficiário, SP/SADT, busca de prestador, anexos e resultado final.
- [x] 2.2 Criar testes do worker para sucesso ponta a ponta, restrição administrativa, atualização cadastral e fallbacks de médico.
- [x] 2.3 Criar testes do worker para campos genéricos, falha de upload obrigatório e resultado incerto pós-submit.

## 3. Laravel Integration And Docs

- [x] 3.1 Ajustar payload Laravel para incluir todos os anexos necessários e dados normalizados sem expor segredo.
- [x] 3.2 Ajustar aplicação de resultado para status/erro da Automação 1, incluindo `needs_verification` sem número e `uncertain` sem guia fictícia.
- [x] 3.3 Adicionar ou atualizar testes Laravel focados no contrato do worker e na persistência da guia.
- [x] 3.4 Criar `docs/automacao-unimed/v2-02-worker-gerar-guia.md`.

## 4. Validation

- [x] 4.1 Executar testes/checks do worker e corrigir regressões.
- [x] 4.2 Executar testes backend pertinentes e corrigir regressões.
- [x] 4.3 Executar lint/build frontend se houver impacto e corrigir regressões.
- [x] 4.4 Executar `openspec validate automacao-unimed-v2-worker-gerar-guia --strict`.
- [x] 4.5 Se validações relevantes passarem, comitar com `feat(unimed-v2): etapa 2 - worker real gerar guia`.
