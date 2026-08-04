## 1. Preparacao

- [x] 1.1 Confirmar que as Etapas 1 a 4 estao comitadas no HEAD atual.
- [x] 1.2 Validar o change OpenSpec da homologacao real.
- [x] 1.3 Criar o relatorio `docs/automacao-unimed/v2-05-homologacao-real.md` com roteiro, criterios e campos de evidencia.
- [ ] 1.4 Confirmar responsavel presente e credencial configurada fora do chat antes de acessar o portal real.

## 2. Execucao Assistida

- [ ] 2.1 Executar caso 1: guia aprovada diretamente.
- [ ] 2.2 Executar caso 2: guia em analise.
- [ ] 2.3 Executar caso 3: guia negada.
- [ ] 2.4 Executar caso 4: restricao ou pendencia administrativa.
- [ ] 2.5 Executar caso 5: beneficiario exigindo atualizacao cadastral.
- [ ] 2.6 Executar casos 6 a 8: selecao de medico por CRM, por nome e fallback nao cooperado.
- [ ] 2.7 Executar casos 9 e 10: procedimento com campos genericos e upload de documentos.
- [ ] 2.8 Executar casos 11 e 12: Script 2 atualizando status e Script 3 capturando senha/validade.
- [ ] 2.9 Executar casos 13 a 15: timeout simulado, resultado incerto idempotente e mudanca de estrutura pausando conector.

## 3. Fechamento

- [ ] 3.1 Registrar esperado, obtido, evidencia sanitizada, pendencias e status de cada caso.
- [ ] 3.2 Produzir decisao GO/NO-GO e plano de rollback.
- [ ] 3.3 Executar validacoes OpenSpec finais.
- [ ] 3.4 Comitar somente se os casos criticos estiverem aprovados ou as pendencias estiverem claramente documentadas.
