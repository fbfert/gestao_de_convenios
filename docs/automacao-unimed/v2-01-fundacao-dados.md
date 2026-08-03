# Automação Unimed v2 - Etapa 1 Fundação de Dados

## Escopo

Esta etapa adiciona os dados necessários para a automação real da Unimed, sem alterar o worker Node/Playwright.

## Novos campos

- `solicitacoes.cid`: CID opcional, usado futuramente como indicação clínica no portal.
- `guias.numero_guia`: passa a aceitar `NULL` para casos como `needs_verification`.
- `guias.sessoes_solicitadas` e `guias.sessoes_autorizadas`: quantidades retornadas/esperadas no fluxo Unimed.
- `guias.protocolo_operadora`: protocolo retornado pela operadora.

## Novas tabelas

- `convenio_especialidade_mapeamentos`: vincula tenant, convênio e especialidade ao `codigo_procedimento`, quantidade padrão e campos genéricos da operadora.
- `convenio_profissional_mapeamentos`: vincula tenant, convênio e profissional ao `codigo_operadora`.

Ambas têm unicidade por tenant, convênio e entidade mapeada.

## Status da Guia

A decisão técnica foi migrar `guias.status` de ENUM rígido para string no MySQL/MariaDB, mantendo validação no domínio. Isso preserva os status legados `under_review`, `finalized` e `denied`, e permite os status operacionais `approved`, `canceled` e `needs_verification` sem nova migration a cada variação do portal.

Na UI, `needs_verification` é exibido como "Verificar Restrição".

## Carteirinha Unimed

Para convênios com `connector_driver = unimed_rda`, o frontend exibe cinco campos visuais com tamanhos 4, 4, 6, 2 e 1. A API valida 17 dígitos e salva a carteirinha em campo único normalizado.

Convênios sem driver Unimed mantêm o campo livre existente.

## Permissão

As rotas `/configuracoes/unimed`, `/configuracoes/unimed/worker-health`, `/configuracoes/unimed/reativar` e os CRUDs de mapeamento exigem `configuracoes.unimed.manage`. A permissão é adicionada ao catálogo e atribuída ao papel `admin`.

## Compatibilidade

As alterações são aditivas. Nenhuma coluna legada foi removida e o worker permanece intocado nesta etapa.
