## Context

A change `automacao-unimed-multi-itens` criou a base de automação Unimed, mas sua auditoria posterior registrou lacunas de dados que impedem o preenchimento real do portal. O worker Playwright precisa receber dados configuráveis e tenant-safe: CID da Solicitação, códigos de procedimento por especialidade/convênio, códigos de profissional por profissional/convênio, carteira Unimed segmentável e Guias com status/campos que representem o retorno real da operadora.

As specs aprovadas atuais cobrem detalhe de Guia e conciliação/analítico; esta change adiciona a fundação v2 sem contrariar esses contratos. O banco/API mantêm status em inglês e a tradução continua na UI.

## Goals / Non-Goals

**Goals:**

- Adicionar dados mínimos para que a automação real possa preencher a Unimed sem hardcode.
- Manter todas as novas tabelas e validações isoladas por tenant.
- Dar ao operador CRUD simples para mapeamentos exigidos pela Unimed.
- Tornar Guias compatíveis com número ainda ausente e novos status operacionais.
- Aplicar máscara 4+4+6+2+1 apenas para pacientes de convênio Unimed RDA.
- Separar permissão de credenciais Unimed da permissão genérica de configurações.

**Non-Goals:**

- Implementar worker Playwright real.
- Alterar `worker-unimed/`.
- Criar disparo automático novo.
- Remover campos legados ou quebrar APIs existentes.

## Decisions

### Mapeamentos como tabelas por tenant, convênio e entidade

Criar tabelas para Especialidade x Convênio e Profissional x Convênio com `tenant_id`, chaves da entidade e `ativo`. A unicidade por `tenant_id + convenio_id + especialidade_id` e `tenant_id + convenio_id + profissional_id` impede ambiguidade no envio.

Alternativa considerada: adicionar `codigo_unimed` diretamente em especialidades/profissionais. Rejeitada porque o mesmo profissional ou especialidade pode ter código diferente por convênio e porque o plano exige não acoplar UI a um campo fixo `codigo_unimed`.

### Guia com status string validado no domínio

Preferir coluna string para `status` quando o schema atual permitir migração segura. A validação dos status aceitos fica em model/request/service, mantendo compatibilidade com `under_review`, `finalized` e `denied` e adicionando `approved`, `canceled` e `needs_verification`.

Alternativa considerada: ampliar ENUM de banco. Rejeitada por fragilidade operacional em MariaDB e por exigir migration a cada novo status retornado pela operadora.

### Carteirinha Unimed normalizada em campo único

Usar UI com cinco blocos apenas quando o convênio selecionado tiver `connector_driver = unimed_rda`, mas persistir o valor normalizado em um único campo existente para preservar compatibilidade com pacientes e convênios não-Unimed.

Alternativa considerada: criar cinco colunas de carteirinha. Rejeitada porque espalha regra específica da Unimed no modelo de paciente e dificulta compatibilidade com convênios livres.

### Permissão dedicada administrativa

Criar permissão no padrão existente do projeto para gerenciar Unimed, atribuída somente ao papel administrativo do tenant. Rotas de credencial, health e reativação Unimed passam a exigir essa permissão.

Alternativa considerada: manter `configuracoes.manage`. Rejeitada porque credenciais Unimed são segredo operacional sensível e exigem autorização mais restrita.

## Risks / Trade-offs

- [Status string permite valores inválidos se bypassar domínio] -> Centralizar lista de status permitidos no backend e cobrir requests/services em testes.
- [CRUD de mapeamentos pode duplicar padrões visuais] -> Reaproveitar controllers, requests e telas de CRUD existentes onde possível.
- [Carteirinha existente mal formatada] -> Decompor apenas quando houver 17 dígitos; caso contrário exigir correção na edição Unimed.
- [Permissão nova pode bloquear usuários administrativos existentes] -> Atualizar seeder/sincronização de permissões para conceder ao papel administrativo.
- [Working tree contém arquivos não rastreados não relacionados] -> Não tocar nesses arquivos e limitar commit aos arquivos desta change.

## Migration Plan

1. Adicionar migrations reversíveis e models.
2. Atualizar requests/resources/services com validação tenant-safe.
3. Criar CRUDs e rotas protegidas por permissões existentes adequadas.
4. Ajustar UI de Solicitações, Pacientes, Guias e Configurações.
5. Adicionar testes focados e documentação v2-01.
6. Executar `openspec validate`, testes backend pertinentes e lint/build frontend.

Rollback preferencial: reverter commit da etapa antes de produção. As migrations devem ser reversíveis e não destrutivas; nenhuma coluna legada será removida.
