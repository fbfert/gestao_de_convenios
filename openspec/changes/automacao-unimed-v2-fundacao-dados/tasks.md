## 1. Backend Data Foundation

- [x] 1.1 Adicionar migration/model/resource/request para CID em Solicitação e mapeamentos Especialidade x Convênio e Profissional x Convênio com `tenant_id`.
- [x] 1.2 Atualizar Solicitações para aceitar, persistir e retornar CID sem quebrar compatibilidade.
- [x] 1.3 Atualizar Guias para número nullable, novos campos operacionais e domínio de status compatível.
- [x] 1.4 Criar CRUDs/rotas tenant-safe para os dois mapeamentos.
- [x] 1.5 Criar permissão dedicada de Unimed, seed/sync para papel administrativo e aplicar nas rotas Unimed.

## 2. Frontend

- [x] 2.1 Atualizar tipos/hooks/telas de Solicitações para CID e exibição de código de procedimento quando houver mapeamento.
- [x] 2.2 Criar UI administrativa simples para mapeamentos de especialidade e profissional por convênio.
- [x] 2.3 Atualizar listagem/detalhe de Guias com campos operacionais e marcador para número ausente.
- [x] 2.4 Atualizar formulário de Pacientes para máscara Unimed 4+4+6+2+1 preservando demais convênios.
- [x] 2.5 Ocultar ou desabilitar ações de configuração Unimed quando faltar permissão dedicada.

## 3. Tests And Documentation

- [x] 3.1 Adicionar testes backend para CID, mapeamentos, Guia sem número/status, carteirinha Unimed e permissão dedicada.
- [x] 3.2 Executar testes backend pertinentes e corrigir regressões da etapa.
- [x] 3.3 Executar lint/build frontend pertinentes e corrigir regressões da etapa.
- [x] 3.4 Criar `docs/automacao-unimed/v2-01-fundacao-dados.md`.
- [x] 3.5 Executar `openspec validate automacao-unimed-v2-fundacao-dados --strict`.
- [x] 3.6 Se validações relevantes passarem, comitar com `feat(unimed-v2): etapa 1 - fundacao de dados`.
