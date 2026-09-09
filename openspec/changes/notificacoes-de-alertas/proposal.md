## Why

A central de alertas mostra o que precisa de ação — para quem abre a tela. Worker caído às 2h da manhã continua descoberto por reclamação do cliente, porque ninguém está com o dashboard aberto de madrugada.

## Constatação confirmada antes de escrever

O plano afirmava que `email_templates` tem CRUD completo e que nenhum código do app consome esses templates. **A busca confirma.** As únicas referências a `EmailTemplate` no `app/` são o próprio CRUD (`EmailTemplateController`, `StoreEmailTemplateRequest`, `EmailTemplateResource`) e a leitura/gravação em `EmailSettingsController`. Nenhum envio usa template: o único ponto do sistema que manda e-mail hoje é o botão de teste de SMTP, e ele manda texto cru.

Portanto esta fase é o **primeiro consumidor** de `email_templates`, e não cria um segundo CRUD de modelos.

## What Changes

- Duas tabelas de destinatários: uma do tenant e outra global (a Xiax).
- Digest diário por destinatário, no horário que cada um escolher.
- Envio imediato para vermelho de regra marcada como crítica, com janela de silêncio e agrupamento.
- Corpo montado a partir de `email_templates`, com texto padrão de reserva no código.
- E-mail do tenant sai pelo SMTP do tenant; e-mail do suporte sai pelo SMTP da própria aplicação.
- Higiene de destinatário: confirmação de e-mail e desativação automática após falhas seguidas.

## Capabilities

### New Capabilities
- `notificacoes-de-alertas`: destinatários, digest diário, envio imediato e consumo dos modelos de e-mail já existentes.

### Modified Capabilities

## Impact

- API Laravel: duas tabelas de destinatários, duas colunas novas (uma em `alertas`, outra em `alerta_regras`), um serviço de notificação, um job de hora em hora e o gancho de envio imediato no avaliador.
- Frontend React: bloco de destinatários em `/alertas/configuracoes`, e um link para os modelos de e-mail que já existem — não uma cópia.
- Banco de dados: `alerta_destinatarios` e `alerta_destinatarios_globais`.

## Não-objetivos

- WhatsApp. O campo `canal` é string justamente para ele entrar depois sem migration, mas não é implementado agora.
- Um segundo CRUD de modelos de e-mail.
- Notificação de alerta verde. Verde é informativo e não acorda ninguém.
