## Why

As Etapas 1 a 4 implementaram e testaram a automacao Unimed com fixtures locais, mas o risco principal restante esta na compatibilidade com o portal real da Unimed RDA. Esta mudanca formaliza a homologacao assistida como etapa controlada, com evidencias por caso e decisao GO/NO-GO antes de habilitar uso produtivo.

## What Changes

- Criar um roteiro normativo de homologacao real para as automacoes Unimed v2.
- Exigir execucao assistida, com responsavel presente e credenciais fornecidas fora do chat e fora de arquivos versionados.
- Registrar resultado esperado, resultado obtido, evidencia, pendencias e decisao GO/NO-GO.
- Permitir somente correcoes pontuais de defeitos dentro do escopo das Etapas 1 a 4; funcionalidade nova fica fora desta etapa.
- Criar o relatorio `docs/automacao-unimed/v2-05-homologacao-real.md`.

## Capabilities

### New Capabilities
- `automacao-unimed-homologacao-real`: cobre o processo de homologacao assistida contra o portal real da Unimed RDA, incluindo evidencias, criterios GO/NO-GO e rollback.

### Modified Capabilities

## Impact

- Afeta o processo operacional de validacao das automacoes Unimed v2.
- Afeta documentacao e artefatos OpenSpec.
- Nao altera APIs, banco de dados, dependencias ou comportamento funcional por padrao.
- Acesso externo ao portal real so pode ocorrer durante a sessao assistida e com credencial fornecida pelo responsavel no ambiente local.
