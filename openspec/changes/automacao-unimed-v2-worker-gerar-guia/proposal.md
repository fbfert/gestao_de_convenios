## Why

A etapa anterior criou os dados necessários para preencher corretamente o portal Unimed, mas o worker Node ainda responde como mock. A geração de guia precisa sair do placeholder e passar a executar o fluxo real em Playwright usando apenas fixtures locais nesta etapa, sem acesso ao portal real.

## What Changes

- Substituir a operação mock de geração de guia por uma implementação Playwright para login, beneficiário, SP/SADT, médico solicitante, procedimento, anexos, profissional executante e leitura do resultado.
- Criar fixtures HTML e testes locais do worker cobrindo sucesso, restrição administrativa, atualização cadastral, fallback de médico, campos genéricos, falha de upload e resultado incerto pós-submit.
- Ajustar a integração Laravel para enviar ao worker os dados normalizados da Etapa 1 e persistir resultado real em `guias` sem número fictício.
- Documentar a máquina de estados, códigos de erro e limites de homologação em `docs/automacao-unimed/v2-02-worker-gerar-guia.md`.
- Manter fora do escopo consulta de status, captura de senha/validade em lote, circuit breaker avançado e qualquer acesso real a `rda.unimedsc.com.br`.

## Capabilities

### New Capabilities
- `automacao-unimed-worker-gerar-guia`: cobre a execução local/testável da Automação 1 para geração de guia Unimed via worker Playwright e contrato de resultados com o Laravel.

### Modified Capabilities
- `guia-detail`: a guia deve refletir dados reais retornados pela geração Unimed, incluindo número opcional, protocolo, sessões, senha quando disponível e status operacional traduzido.

## Impact

- `worker-unimed/`: dependência Playwright, estrutura de automação, fixtures e testes.
- `api/app/Services/Automation/GerarGuiaUnimedService.php` e cliente/job de worker: payload e aplicação de resultado.
- `api/tests/Feature` e testes do worker: cobertura de contrato e dos cenários locais.
- Documentação em `docs/automacao-unimed/`.
