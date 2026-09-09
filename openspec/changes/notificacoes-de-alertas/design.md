## Context

A central de alertas materializa o que exige ação, e `alerta_regras.critica` já nasceu marcando quem merece interrupção imediata. `email_smtp_settings` guarda o SMTP de cada tenant e `email_templates` guarda modelos que ninguém consumia.

## Goals / Non-Goals

**Goals:**
- Cada pessoa recebe o que lhe diz respeito, e não tudo.
- Uma automação em loop não vira enxurrada de e-mail.
- O suporte recebe um e-mail por dia com todos os tenants, e não um por tenant.
- O alerta de SMTP quebrado consegue sair.

**Non-Goals:**
- Canal que não seja e-mail.
- Editor de modelos novo.

## Decisions

- **Destinatário global em tabela separada, e não `tenant_id` nulo.** Racional: `BelongsToTenant` aplica um global scope, e um registro com tenant nulo desaparece de qualquer consulta que não use `withoutGlobalScopes()`. No dia em que alguém esquecer isso num refactor, o suporte simplesmente para de receber — e ausência de e-mail parece "sem problemas", que é o pior modo de falhar. Tabela separada, sem o trait, torna o esquecimento impossível.

- **Filtro por chave, e não uma lista de e-mails.** Cada destinatário declara quais níveis e quais chaves quer. Racional: a recepcionista quer senha vencendo, o financeiro quer glosa, o suporte quer worker offline. Um campo único com e-mails separados por vírgula manda tudo para todos, e em um mês pedem para desligar — o que na prática desliga também o que importava.

- **Digest vazio não é enviado.** Racional: um e-mail diário dizendo "nada aqui" treina a pessoa a arquivar sem ler, e o dia em que houver algo ela arquiva também.

- **Imediato só para vermelho de regra crítica, com duas travas.** A janela de silêncio (`alerta_regras.janela_silencio_horas`) impede reenviar o mesmo alerta antes do prazo, e o agrupamento sai de graça da deduplicação da fase anterior: como só existe um alerta aberto por chave e entidade, N falhas em série já são um alerta — e portanto um e-mail. Racional: sem as duas, uma automação em loop manda duzentos e-mails de madrugada. Não é hipótese, é o comportamento padrão de qualquer alerta ingênuo.

- **`alertas.notificado_em` guarda o último envio imediato.** Racional: a janela de silêncio precisa de estado, e o estado pertence ao alerta, não a uma tabela de log paralela — que exigiria limpeza própria e uma segunda fonte de verdade sobre o que já foi avisado.

- **Um job de hora em hora, e não um agendamento por horário possível.** O job olha quais destinatários têm `horario_digest` naquela hora. Racional: 24 agendamentos fixos seriam 24 entradas no scheduler para fazer o que uma consulta resolve.

- **O e-mail do suporte sai pelo mailer da aplicação, não pelo SMTP do tenant.** Racional: um dos alertas que o suporte precisa receber é justamente "o SMTP do tenant está falhando" — mandá-lo pelo canal quebrado seria garantir que nunca chegue. O mailer da aplicação vem do `.env` (`MAIL_MAILER` e afins), que é infraestrutura da Xiax e não da clínica.

- **Modelo ausente cai em texto padrão do código.** Racional: notificação que não sai por falta de modelo é falha silenciosa, e o custo de um texto de reserva é uma constante.

- **Desativação após falhas seguidas, com alerta.** Bounce derruba a reputação do SMTP e ninguém fica sabendo. O destinatário é desativado e abre-se um alerta amarelo `email.destinatario_falhando` — que aparece na central, fechando o ciclo com a fase anterior.

## Risks / Trade-offs

- **[A regra `email.destinatario_falhando` não tem avaliador]** -> Ela é aberta pelo próprio notificador, e não por varredura periódica. Como o avaliador resolve automaticamente o que não vem no conjunto de uma regra que ele avalia, esta chave **não** é registrada no resolvedor — senão a rodada seguinte a fecharia sozinha. O preço é que ela só se resolve quando alguém reativa o destinatário; está documentado aqui para não parecer esquecimento.

- **[Janela de silêncio esconde agravamento]** -> Se um alerta piorar dentro da janela, o segundo e-mail não sai. Aceito: o digest do dia seguinte cobre, e a alternativa — reenviar a cada mudança — é a enxurrada que se quis evitar.

- **[Confirmação de e-mail sem fluxo de clique]** -> `verificado_em` é preenchido quando um envio a esse endereço tem sucesso, e não por um link de confirmação. É mais fraco que double opt-in, e é o suficiente para o objetivo real aqui, que é distinguir endereço digitado errado de endereço que funciona.
