## Context

O sistema já possui a modelagem de `profissionais` e um endpoint de listagem usado como referência por telas de guias, solicitações, lançamentos, conciliação e usuários. O que falta é a camada operacional de manutenção do cadastro, com interface e endpoints de escrita para a clínica administrar seus próprios profissionais por tenant.

## Goals / Non-Goals

**Goals:**

- Permitir criar, editar, listar e desativar profissionais da clínica.
- Manter o comportamento atual do endpoint de referência `/profissionais`.
- Restringir a manutenção ao tenant autenticado.
- Permitir configuração de percentual de repasse por profissional.

**Non-Goals:**

- Reescrever os fluxos que já consomem profissionais como referência.
- Criar uma entidade separada para médicos e outra para profissionais executantes.
- Alterar regras de cálculo financeiro além do armazenamento do percentual por profissional.
- Mudar a estrutura da tabela `profissionais` de forma destrutiva.

## Decisions

- **Reaproveitar a tabela `profissionais` existente como fonte única.** A clínica já opera com essa entidade em todo o fluxo; criar uma segunda tabela duplicaria referências e quebraria os selects existentes. Alternativa considerada: criar um novo cadastro separado para execução clínica; descartada por duplicar domínio.
- **Separar listagem de referência e escrita por permissão.** O `GET /profissionais` permanece para consumo geral autenticado, enquanto `POST` e `PATCH` passam a exigir `profissionais.manage`. Alternativa considerada: restringir toda a rota; descartada porque os formulários operacionais dependem da lista.
- **Retornar profissionais ativos por padrão na listagem.** Os selects operacionais devem enxergar apenas profissionais ativos, enquanto a tela administrativa pede a listagem completa com um filtro explícito. Alternativa considerada: listar tudo por padrão; descartada por risco de reuso de profissionais inativos em telas operacionais.
- **Usar a mesma experiência de CRUD dos médicos como padrão visual.** A tela de profissionais deve seguir o mesmo padrão de busca, formulário lateral e ações rápidas já adotado em médicos. Alternativa considerada: tela minimalista só de tabela; descartada por reduzir consistência e velocidade operacional.
- **Incluir percentual de repasse no cadastro.** O valor já é usado nos cálculos financeiros e precisa ser configurável no ponto de origem. Alternativa considerada: manter percentual apenas em configurações financeiras; descartada por aumentar acoplamento entre telas.
- **Permitir desativação sem remoção física.** Profissionais podem deixar de atender temporariamente, mas precisam continuar referenciáveis em registros antigos. Alternativa considerada: delete físico; descartada por risco de perda histórica.

## Risks / Trade-offs

- [Permissões novas exigem ajuste em seeders e perfis existentes] -> Atualizar os papéis padrão e validar acesso com testes de API.
- [Mudança de cadastro pode impactar filtros e selects já usados em outras telas] -> Manter o payload de referência estável e cobrir listagem com testes.
- [Percentual de repasse em formato decimal pode gerar divergências de arredondamento] -> Validar entrada e persistência com testes de caso real.
- [Tela de profissionais pode ser confundida com a de médicos] -> Deixar a nomenclatura explícita na navegação e na descrição da página.

## Migration Plan

1. Adicionar a permissão `profissionais.manage` aos catálogos e seeders.
2. Implementar endpoints de escrita e validação para profissionais.
3. Criar a tela de CRUD no frontend e ligar a navegação.
4. Ajustar testes de API e de interface para cobrir listagem, criação, edição e desativação.
5. Validar o impacto nos consumidores existentes do endpoint `/profissionais`.

Rollback: se houver regressão de permissões ou consumo dos selects, remover a exposição dos endpoints de escrita e manter apenas a listagem de referência até corrigir os testes e seeders.

## Open Questions

- A clínica quer buscar profissionais também por especialidade e conselho, ou apenas por nome?
- O percentual de repasse deve aceitar valor vazio no cadastro inicial ou ser obrigatório?
- A tela deve permitir excluir fisicamente um profissional ou apenas desativar?
