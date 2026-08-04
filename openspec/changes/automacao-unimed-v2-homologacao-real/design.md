## Context

As automacoes Unimed v2 foram desenvolvidas em etapas separadas: fundacao de dados, worker real para gerar guia, worker real para consulta de status/senha e robustez do conector. Ate aqui, o portal real nao foi acessado durante desenvolvimento; os testes automatizados usam fixtures locais.

A Etapa 5 e uma homologacao operacional, nao uma entrega funcional nova. Ela precisa validar o comportamento contra `rda.unimedsc.com.br` com um responsavel presente, sem vazar credenciais, dados sensiveis ou screenshots com informacao protegida.

## Goals / Non-Goals

**Goals:**
- Validar os fluxos reais de gerar guia, consultar status e capturar senha/validade no portal Unimed RDA.
- Registrar evidencia suficiente para decidir GO/NO-GO.
- Documentar riscos residuais, pendencias e plano de rollback.
- Pausar caso especifico ao encontrar mudanca de estrutura, resultado incerto ou comportamento inesperado do portal.

**Non-Goals:**
- Implementar funcionalidade nova.
- Alterar modelo de dados, APIs ou telas fora de correcoes pontuais de defeitos ja cobertos pelas Etapas 1 a 4.
- Automatizar testes contra o portal real em pipeline.
- Registrar ou versionar login, senha, carteirinha completa, screenshots sensiveis ou documentos de pacientes.

## Decisions

1. Homologacao assistida em vez de automacao cega contra producao.
   - Racional: o portal real pode gerar guias, alterar status e consumir documentos reais. A presenca humana reduz risco operacional e permite interromper casos ambigueis.
   - Alternativa considerada: rodar todos os casos automaticamente. Rejeitada porque os casos envolvem efeitos externos e possiveis dados sensiveis.

2. Evidencia redigida e sanitizada.
   - Racional: o relatorio precisa comprovar comportamento sem vazar segredo ou dado sensivel.
   - Alternativa considerada: anexar screenshots completas. Rejeitada por risco de expor credenciais, carteirinhas ou dados clinicos.

3. Correcoes apenas dentro do escopo implementado.
   - Racional: a Etapa 5 deve validar as entregas anteriores. Qualquer nova regra funcional exige novo change/spec.
   - Alternativa considerada: ajustar livremente o fluxo durante a homologacao. Rejeitada por contrariar a disciplina OpenSpec do projeto.

4. GO/NO-GO explicito.
   - Racional: a habilitacao produtiva do botao manual "Enviar para Unimed" deve depender de criterios objetivos e pendencias aceitas.
   - Alternativa considerada: considerar homologado ao final da execucao. Rejeitada porque casos podem ficar pendentes ou parcialmente aprovados.

## Risks / Trade-offs

- Portal indisponivel ou instavel durante a sessao -> registrar como pendencia operacional e reagendar a homologacao.
- Credencial invalida, bloqueada ou sem perfil necessario -> interromper execucao real e registrar NO-GO por bloqueio de acesso.
- Mudanca de seletor/estrutura do portal -> pausar conector conforme Etapa 4, registrar evidencia sanitizada e nao insistir no caso.
- Caso real indisponivel para determinado resultado, como guia negada ou atualizacao cadastral -> registrar pendencia especifica, risco residual e criterio para nova janela de homologacao.
- Dados sensiveis em tela -> coletar somente evidencia textual sanitizada no relatorio.
