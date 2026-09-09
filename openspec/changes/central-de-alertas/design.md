## Context

O agendador já roda (`schedule:work` sob supervisão, ver ADR-25) e a fase de saúde de componentes já provou o padrão "job periódico escreve estado, tela lê". Falta o que é acumulado e exige ação humana.

`configuracoes_globais.senha_alerta_dias` existe desde sempre e ganhou o primeiro consumidor na fase anterior; agora ganha o segundo. `automacao_execucoes` guarda o histórico de execuções com `status` e `operacao`, que é a matéria-prima da regra de falhas em série.

## Goals / Non-Goals

**Goals:**
- Um lugar só para o que exige ação.
- Regra nova é uma classe e uma linha de seed, sem tocar no job.
- Nada de duplicata, e fechamento automático quando a causa some.
- Limiar é dado por tenant, nunca constante em código.

**Non-Goals:**
- Notificar. É a fase seguinte, e a tabela já nasce com o campo `critica` que ela vai usar.
- As quinze regras de backlog.
- Alerta por usuário. Alerta é do tenant; "reconhecido por" registra quem viu, não para quem é.

## Decisions

- **Materializado, e não derivado na leitura.** Racional: alerta que não pode ser notificado é relatório. Materializar é o que permite, na fase seguinte, mandar e-mail sem recalcular tudo — e é o que dá `aberto_em`, que é a informação que o operador realmente usa ("isso está aqui desde terça").

- **A deduplicação é uma coluna, e não um índice parcial.** O alvo de produção é MariaDB, que não tem índice único parcial. A solução é a coluna `aberto_dedupe`: vale `1` enquanto o alerta está aberto e `NULL` quando resolvido, com índice único em `(tenant_id, chave, entidade, entidade_id, aberto_dedupe)`. Racional: os dois bancos tratam `NULL` como distinto num índice único, então vários alertas resolvidos da mesma chave convivem, e só um aberto é possível. Coluna gerada resolveria também, mas exigiria sintaxe diferente em SQLite e MariaDB — e os testes rodam no primeiro.

- **Fechamento automático é requisito, não otimização.** Cada avaliação devolve o conjunto do que *deveria* estar aberto; o que está aberto e não veio no conjunto é resolvido. Racional: sem isso a central acumula lixo em um mês e o time para de olhar — e uma central ignorada é pior que nenhuma, porque dá a sensação de que alguém está vigiando.

- **Verde existe no banco, mas não vai para o card.** O card do dashboard mostra só amarelo e vermelho. Racional: se verde significa "tudo certo", o card enche de linhas irrelevantes e as vermelhas somem no meio. A ausência de alerta é o próprio estado verde, e é isso que o card diz quando não há nada.

- **O card não some quando está vazio.** Mostra "Nenhum alerta pendente". Racional: "não apareceu nada" e "está tudo bem" precisam ser distinguíveis — um card que some ensina o operador a não procurar.

- **Menu próprio, configuração junto das outras.** `/alertas` é operação (senha vencendo e glosa não são automação), então tem entrada própria. Já `/alertas/configuracoes` fica alcançável também por Automações → Configurações, onde `senha_alerta_dias` mora.

- **Regra é classe; o job não conhece nenhuma.** Uma interface com `avaliar(int $tenantId): array` e um resolver que mapeia chave → classe. Racional: é o mesmo raciocínio do ADR-02 para conectores — regra nova não pode exigir alteração do orquestrador.

- **Silenciar tem data; reconhecer não.** Reconhecer é "eu vi"; silenciar é "não me mostre até tal dia". Racional: silenciar sem prazo é fechar, e fechar já é papel do avaliador quando a causa some.

## Risks / Trade-offs

- **[Remover o `GuiaAlertaNegacoes` é uma regressão em potencial]** -> Ele oferece hoje duas ações que a listagem não tem: ocultar o alerta e abrir nova solicitação a partir da guia. Se sumirem, quem opera todo dia perde função. Por isso o alerta de `guia.negada` carrega as duas, e o banner só sai depois que elas existem no lugar novo.

- **[O avaliador roda a cada 15 minutos para todos os tenants]** -> Cada regra é uma consulta por tenant. Com quatro regras e um tenant, são quatro consultas a cada quinze minutos — irrelevante. Com cinquenta tenants e vinte regras, mil: aí o avaliador precisa virar fila por tenant. Registrado para quando o número de regras crescer, que é o eixo que estoura primeiro.

- **[Limiar configurável convida a desligar o alerta incômodo]** -> É o preço de "regra é dado". A mitigação é que ligar e desligar é auditado (o model usa `Auditable`), então a decisão fica rastreável.

- **[`entidade`/`entidade_id` sem chave estrangeira]** -> É polimórfico por natureza: um alerta aponta ora para guia, ora para componente de saúde. Aqui a ausência de FK é aceitável porque o alvo é referência de exibição, e não de integridade — se a entidade sumir, o avaliador resolve o alerta na rodada seguinte por não encontrá-lo mais.
