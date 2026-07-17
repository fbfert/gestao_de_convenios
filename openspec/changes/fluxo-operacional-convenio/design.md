## Context

O sistema já possui a base de guias, antecipações, lançamentos e conciliações, mas o fluxo operacional real da clínica é mais amplo: o processo começa no acolhimento do paciente, passa pela solicitação médica, pela autorização inicial, pela continuidade por antecipações, pelo registro de sessões e termina no retorno financeiro do convênio. A mudança precisa respeitar a arquitetura atual do projeto, com status em inglês no API/banco e tradução apenas na UI.

## Goals / Non-Goals

**Goals:**

- Formalizar o fluxo ponta a ponta de convênio como uma jornada operacional única.
- Tratar solicitação e guia como uma sequência do mesmo processo, sem duplicar a linguagem de negócio.
- Permitir registro de sessões, finalização para envio à Unimed e importação do analítico em Excel.
- Alimentar a conciliação e o repasse aos profissionais a partir do que o convênio efetivamente pagou.
- Manter a camada de apresentação responsável por traduzir status para português.

**Non-Goals:**

- Trocar o modelo de tenant, permissões ou autenticação.
- Definir o fornecedor de OCR/IA para leitura de PDF, foto ou Excel.
- Implementar agenda completa ou prontuário clínico além do que for necessário para esse fluxo.
- Substituir a lógica atual de regras de convênio e valores, que continua configurável.

## Decisions

- **Manter a guia como agregado operacional central.** A solicitação médica alimenta o fluxo, mas a guia continua sendo o ponto de referência para antecipações, sessões e conciliação. Alternativa considerada: criar um novo agregado separado para cada etapa; descartada por fragmentar o processo e aumentar o custo de navegação.
- **Persistir novos status em inglês e traduzir na UI.** O sistema deve armazenar os estados técnicos em inglês e expô-los em português apenas na interface. Alternativa considerada: gravar textos em português no banco; descartada por contrariar a decisão arquitetural já adotada.
- **Separar importação, leitura automática e conciliação em serviços distintos.** A leitura do PDF/foto, a importação do Excel e o cálculo financeiro não devem ficar no controller. Alternativa considerada: concentrar tudo em uma mutation única; descartada por dificultar testes e manutenção.
- **Usar sessão como unidade financeira.** O valor devido ao profissional deve ser calculado por sessão paga pelo convênio, com percentual configurável por profissional. Alternativa considerada: calcular por guia inteira; descartada porque o fluxo real do cliente confere sessão a sessão.
- **Exigir confirmação humana antes do envio à Unimed.** O operador valida datas, horários e anexos antes de finalizar o lote de sessões. Alternativa considerada: envio automático; descartada por risco operacional e necessidade de correção manual.
- **Tratar o Excel da Unimed como entrada canônica do retorno financeiro.** O analítico importado é a base para a conciliação inicial. Alternativa considerada: reconstruir o retorno a partir de logs internos; descartada porque o processo real do cliente já parte do arquivo externo.

## Risks / Trade-offs

- [OCR/IA de leitura pode variar entre PDFs, fotos e planilhas] -> O desenho deve aceitar revisão manual antes de persistir dados financeiros.
- [Múltiplos formatos de Excel da Unimed] -> O importador deve ser tolerante a mapeamento configurável de colunas e validações explícitas.
- [Mudança de status pode impactar listas e filtros existentes] -> A migração de status precisa preservar compatibilidade visual na UI e cobrir o comportamento atual por testes.
- [Processo depende de dados configuráveis de convênio e profissional] -> O cálculo financeiro não deve hardcodear percentuais ou frequências.

## Migration Plan

1. Introduzir os novos contratos de status e fluxo na camada de domínio.
2. Ajustar resources e telas para exibir o fluxo operacional com a nova terminologia.
3. Implementar CRUD de sessões e a etapa de finalização/exportação para Unimed.
4. Implementar importação do analítico e derivação de conciliação/repasse.
5. Adicionar testes de API e fluxo de interface por etapa.
6. Validar a migração em dados já existentes, mantendo compatibilidade com registros antigos.

Rollback: se a migração de status ou importação gerar regressão, a camada de UI pode voltar a consumir os estados antigos enquanto o backend permanece com o novo contrato, desde que a mudança esteja coberta por flags internas e testes.

## Open Questions

- Qual será o formato exato do Excel da Unimed que precisa ser aceito no primeiro corte?
- A leitura automática de PDF/foto será feita em que etapa da implementação: já no cadastro da solicitação ou apenas no registro de sessões?
- O alerta de "sem mais agendamentos" deve aparecer em quais telas inicialmente?
- O percentual de retenção da clínica será configurado por profissional, por guia ou por ambos?
