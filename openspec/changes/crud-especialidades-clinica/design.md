## Context

`Especialidade` já é uma entidade central do domínio e alimenta guias, solicitações, profissionais e tabelas de valor. Hoje ela só tem listagem pública para uso operacional, sem uma área própria para manutenção pela clínica.

## Goals / Non-Goals

**Goals:**

- Disponibilizar um CRUD administrativo para especialidades.
- Manter o cadastro isolado por tenant.
- Permitir manutenção sem romper os vínculos históricos já existentes.
- Reaproveitar o catálogo fixo de permissões para controlar o acesso.

**Non-Goals:**

- Trocar o modelo de especialidades por uma estrutura nova.
- Remover fisicamente especialidades já usadas em registros históricos.
- Alterar como guias, profissionais, solicitações ou valores consomem especialidades.

## Decisions

- **Usar inativação lógica em vez de exclusão física.** A especialidade é dado de referência e pode estar ligada a profissionais e registros antigos; excluir fisicamente criaria risco de integridade e perda de histórico. Alternativa considerada: `DELETE` físico; descartada.
- **Manter a listagem operacional ativa separada da tela administrativa.** Os fluxos do dia a dia continuam usando apenas especialidades ativas; a tela de gestão pode mostrar inativas para revisão. Alternativa considerada: expor tudo em todos os formulários; descartada por poluir combos e quebrar a simplicidade operacional.
- **Validar unicidade do nome por tenant.** Evita duplicidade de cadastro e mantém o texto da especialidade consistente nos fluxos. Alternativa considerada: permitir nomes repetidos com ativo/inativo; descartada por aumentar ambiguidade.
- **Usar uma tela única com lista, formulário e ações.** Isso reduz navegação e segue o padrão das telas administrativas do sistema. Alternativa considerada: separar em páginas diferentes; descartada por aumentar fricção.
- **Controlar o acesso pela permissão `especialidades.manage`.** Mantém o padrão do restante do sistema e facilita habilitar o CRUD por papel. Alternativa considerada: criar lógica ad hoc por rota; descartada.

## Risks / Trade-offs

- [Inativar uma especialidade pode afetar seleções futuras] -> o formulário operacional deve continuar mostrando apenas ativos por padrão.
- [Especialidades duplicadas geram confusão em relatórios e filtros] -> validar nome único por tenant e normalizar comparações.
- [Uma UI administrativa nova adiciona mais uma área de manutenção] -> manter a tela simples, com listagem, filtro e formulário enxuto.

## Migration Plan

1. Criar endpoints administrativos para listar, criar, atualizar e inativar especialidades.
2. Adicionar a permissão `especialidades.manage` ao catálogo fixo e aos papéis seedados.
3. Implementar a tela de especialidades com lista, filtro, formulário e ações.
4. Manter os fluxos operacionais consumindo apenas especialidades ativas.
5. Validar com testes de API, build do frontend e navegação local.

Rollback:
- Remover a rota administrativa e a permissão nova.
- Reverter a tela de gestão sem tocar no consumo operacional de especialidades.
- Manter a listagem ativa usada pelos formulários existentes.
