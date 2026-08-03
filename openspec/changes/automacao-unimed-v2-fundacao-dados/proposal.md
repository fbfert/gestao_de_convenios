## Why

A auditoria da primeira implementação Unimed identificou que a infraestrutura foi criada, mas faltam dados e regras de domínio indispensáveis para preencher o portal Unimed com segurança. Antes de substituir o worker mockado por Playwright real, o sistema precisa armazenar CID, mapeamentos por convênio, campos completos de Guia, carteirinha Unimed segmentada e uma permissão administrativa dedicada.

## What Changes

- Adicionar CID opcional em Solicitações e expô-lo na API/UI como indicação clínica futura da Unimed.
- Criar mapeamentos tenant-safe de Especialidade x Convênio e Profissional x Convênio, com CRUD administrativo e unicidade por tenant/convênio/entidade.
- Ampliar o domínio de Guias para suportar número ainda ausente, protocolo da operadora, sessões solicitadas/autorizadas e status adicionais como `approved`, `canceled` e `needs_verification`.
- Exibir a listagem de Guias com dados operacionais necessários ao acompanhamento Unimed, sem inventar número de Guia quando ele ainda não existir.
- Aplicar máscara visual 4+4+6+2+1 para carteirinha de pacientes de convênios com `connector_driver = unimed_rda`, preservando texto livre para demais convênios.
- Criar e aplicar permissão dedicada para configuração/credenciais Unimed, substituindo o uso da permissão genérica nas rotas Unimed.
- Documentar a fundação v2 em `docs/automacao-unimed/v2-01-fundacao-dados.md`.

Non-goals:

- Não implementar automação Playwright real nesta etapa.
- Não alterar `worker-unimed/`.
- Não alterar scheduler ou fluxos de status/senha.
- Não remover colunas legadas de Solicitações ou Guias.

## Capabilities

### New Capabilities

- `automacao-unimed-v2-fundacao-dados`: Campos, mapeamentos, regras de carteirinha, status de Guia e permissão dedicada necessários para preparar a automação real Unimed.

### Modified Capabilities

- `guia-detail`: O detalhe/listagem de Guia deve suportar os novos campos operacionais e número de Guia ausente, preservando os campos existentes.

## Impact

- Backend Laravel: migrations, models, requests, resources, controllers, rotas, seeders/permissões, services de Solicitação/Guia e testes Feature.
- Frontend React: telas/tipos/hooks de Solicitações, Pacientes, Guias e Configurações ou CRUDs administrativos equivalentes.
- Banco de dados: novas tabelas de mapeamento, novos campos em `solicitacoes` e `guias`, eventual migração de status para string ou validação em domínio PHP.
- Documentação e validação: nova página em `docs/automacao-unimed/`, `openspec validate` e testes/lint/build pertinentes.
