## Context

`ManualController::show` já lê `resources/manual/default.html` quando o tenant ainda não tem registro — ou seja, o arquivo versionado já era a origem do conteúdo. O que a tabela acrescenta é a divergência: a partir da primeira edição, cada clínica passa a ter um manual diferente e ninguém sabe qual é o certo.

A conferência mostrou que a única clínica em produção editou os dois documentos. Isso não invalida a decisão — reforça o problema: já existe divergência entre o que o repositório diz e o que a clínica lê.

## Goals / Non-Goals

**Goals:**
- Manual é conteúdo do produto, igual para todos, versionado no git.
- Não perder uma linha do texto que a clínica escreveu.
- Novidade com controle de leitura por usuário.

**Non-Goals:**
- Editor de novidades.
- Manual por tenant, em qualquer forma.

## Decisions

- **O texto da clínica vira a linha de base do produto, e não é descartado.** Racional: com um tenant, a versão editada é a mais atual que existe — descartá-la em favor da semente do repositório seria perder trabalho de gente. As sementes originais ficam versionadas ao lado (`default.html`, `mapa-mental-default.html`) para que a comparação continue possível.

- **Drop com `down()` que recria a tabela, e um passo de exportação documentado.** Racional: `down()` recriar a estrutura vazia não devolve o conteúdo; por isso a exportação é um passo à parte, feito antes, e o conteúdo exportado entra no próprio repositório — que é onde ele sobrevive a qualquer rollback.

- **Novidade é arquivo markdown com frontmatter, e não linha de tabela.** `resources/novidades/AAAA-MM-DD-slug.md`. Racional: o volume real é de poucas por mês, escritas por quem faz o deploy, no mesmo commit da mudança que anunciam. Uma tabela exigiria tela de admin, permissão e migração — custo alto para um problema que ainda não existe.

- **Leitura POR USUÁRIO fica em tabela.** `novidade_leituras (user_id, slug, lido_em)`. Racional: é o que faz o card mostrar "2 não lidas" e parar de aparecer depois. Sem isso o card vira paisagem em duas semanas — o mesmo defeito que o digest vazio tem na fase anterior.

- **O slug é a chave, e não um id.** Racional: novidade não tem id de banco; o nome do arquivo é o identificador estável, e é o que a leitura referencia.

- **A listagem é cacheada.** Ler e parsear um diretório a cada abertura de dashboard seria I/O por requisição para um conteúdo que só muda em deploy.

- **`manual.manage` sai do catálogo de permissões.** Racional: permissão que não protege nada é ruído na tela de Perfis e Permissões, e cria a expectativa de que existe algo a permitir.

## Risks / Trade-offs

- **[Correção de texto passa a exigir deploy]** -> É o custo aceito, e vale registrar em ADR. Em troca, some a divergência de manual entre clínicas e passa a existir uma única fonte de verdade. Com uma clínica o custo é baixo; se a edição por tenant voltar a ser requisito, a decisão se reabre com dado real.

- **[A permissão `manual.manage` some de papéis já configurados]** -> Papéis que a têm ficam com uma atribuição órfã. Não quebra nada — o catálogo é fixo no código (ADR-14) e o que não está nele simplesmente não aparece —, mas a linha continua na tabela `role_has_permissions` até alguém limpar.

- **[Novidade sem tela de admin]** -> Quem não faz deploy não consegue publicar. É deliberado, e o gatilho para mudar está escrito: quando publicar sem deploy virar dor real.
