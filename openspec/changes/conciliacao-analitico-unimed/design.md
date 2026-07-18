## Context

O sistema já possui leitura do analítico da Unimed em Excel, geração de conciliações por guia e cálculo de repasse por profissional. O ponto faltante é operacionalizar esse retorno como um lote rastreável, para que a clínica consiga importar, revisar, reprocessar e fechar o financeiro com menos dependência de conferências manuais em planilhas soltas.

## Goals / Non-Goals

**Goals:**

- Persistir o analítico importado como um lote operacional rastreável.
- Normalizar linhas de analítico e glosa para conferência posterior.
- Manter a distinção entre profissional informado ao plano e profissional executor.
- Reusar o cálculo de repasse por sessão/pagamento já adotado no domínio.
- Preservar a pré-visualização da importação como etapa de conferência.

**Non-Goals:**

- Reescrever o parser atual do Excel do zero.
- Substituir o modelo de conciliação por um ERP financeiro completo.
- Mudar regras de percentual de repasse ou cascata de valores.
- Automatizar a validação contábil final sem revisão humana.

## Decisions

- **Persistir importação em lote em vez de apenas pré-visualizar.** O retorno da Unimed é um artefato operacional e precisa ser rastreável para reprocessamento. Alternativa considerada: manter só preview em memória; descartada por não suportar conferência posterior.
- **Separar importação do analítico e fechamento da conciliação.** A leitura do Excel deve preparar dados; a conferência/pagamento fecha o financeiro em outro passo. Alternativa considerada: abrir e pagar tudo em uma ação; descartada por risco operacional e perda de revisão.
- **Manter a linha da Unimed como unidade de conferência.** Cada linha do Excel continua sendo a referência para pagamento ou glosa. Alternativa considerada: agrupar tudo somente por guia; descartada porque o analítico vem com granularidade por atendimento.
- **Preservar profissional informado e executor.** O nome que vai ao convênio nem sempre é o executante real, então ambos precisam ser persistidos e exibidos. Alternativa considerada: unificar em um único profissional; descartada porque quebra o repasse correto.
- **Reaproveitar os percentuais configuráveis do profissional.** O cálculo de repasse já é parte do domínio e não deve ser duplicado no importador. Alternativa considerada: calcular repasse na própria tela; descartada por duplicar regra de negócio.

## Risks / Trade-offs

- [Variações do Excel da Unimed podem mudar o mapeamento de colunas] -> manter parser tolerante e cobrir com testes de fixture.
- [Persistir lotes e linhas aumenta o volume de dados] -> limitar campos salvos ao necessário para auditoria e reconciliação.
- [Fluxo mais completo pode exigir revisão manual em mais etapas] -> conservar pré-visualização e ações explícitas de confirmar/conferir/pagar.
- [O fechamento financeiro depende de dados já gerados em sessões e guias] -> validar dependências antes de marcar o lote como fechado.

## Migration Plan

1. Criar persistência para lote importado e linhas normalizadas do analítico.
2. Ajustar o importador para salvar o lote e expor seu estado operacional.
3. Evoluir a tela de lançamento/importação para mostrar lote, linhas e diferenças de glosa/pago.
4. Integrar a conciliação financeira ao lote importado e ao repasse por profissional.
5. Validar com testes de API, build do frontend e fluxo navegável.

Rollback: se a persistência de lote gerar regressão, manter o parser e a pré-visualização funcionando e desativar apenas a gravação dos novos registros até corrigir o modelo.

## Open Questions

- O lote importado precisa ser versionado por data e arquivo original?
- A clínica quer reimportar o mesmo Excel e substituir um lote anterior, ou sempre gerar uma nova importação?
- As linhas de glosa precisam aparecer na conciliação como registros separados ou apenas como resumo?
