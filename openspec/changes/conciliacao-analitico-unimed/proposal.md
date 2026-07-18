## Why

O sistema já consegue ler o Excel do analítico da Unimed e já gera conciliações a partir das guias, mas o fluxo ainda fica muito dependente de conferência manual e não preserva de forma operacional o retorno importado. Isso limita rastreabilidade, dificulta reprocessamento e deixa o fechamento financeiro mais frágil do que o necessário para a clínica.

## What Changes

- Estruturar o fluxo de conciliação financeira a partir do analítico da Unimed como uma operação persistente e rastreável.
- Permitir importar o arquivo Excel do analítico e salvar o lote importado para conferência posterior.
- Normalizar as linhas do analítico e das glosas em registros processáveis por guia, quantidade e valor.
- Conectar a importação ao fechamento financeiro por guia, mantendo a distinção entre profissional informado ao plano e profissional executor.
- Calcular entradas, saídas e repasses por sessão paga, usando o percentual configurável por profissional.
- Manter a interface de pré-visualização e conferência, mas com persistência suficiente para revisão e reprocessamento.

## Capabilities

### New Capabilities
- `conciliacao-analitico-unimed`: importação persistente do analítico da Unimed, normalização das linhas, conferência e base para cálculo financeiro por guia e por sessão.

### Modified Capabilities
- Nenhuma.

## Impact

- API Laravel: evolução do importador do analítico, novas persistências para lotes e linhas de conciliação, e ajuste nos fluxos de conferência/pagamento.
- Frontend React: ajustes na tela de lançamento/importação e na tela de conciliação para exibir o lote importado e seu status operacional.
- Banco de dados: suporte para armazenar o arquivo/lote importado e as linhas normalizadas do retorno da Unimed.
- Regras financeiras: cálculo de repasse por profissional executor continua configurável e rastreável por sessão paga.
- Auditoria e operação: melhora a rastreabilidade do que foi importado, conferido e pago, reduzindo dependência de planilhas soltas.
