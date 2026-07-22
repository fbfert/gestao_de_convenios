## Context

A tela de Configurações existe como placeholder e ainda não possui API própria. O projeto é multi-tenant, então qualquer configuração operacional precisa pertencer ao tenant atual. O Laravel já possui configuração global de mail via `.env`, mas o pedido exige salvar dados SMTP e templates pela aplicação.

## Goals / Non-Goals

**Goals:**
- Persistir configuração SMTP por tenant.
- Persistir templates de email por tenant.
- Expor uma interface única na subaba "Envio de emails".
- Evitar devolver a senha SMTP salva para o frontend.

**Non-Goals:**
- Enviar emails reais nesta mudança.
- Validar conexão SMTP em tempo real.
- Substituir imediatamente todos os envios existentes para usar os templates configurados.

## Decisions

- Usar tabelas dedicadas `email_smtp_settings` e `email_templates`. Racional: os campos têm formatos diferentes e templates precisam de múltiplos registros por tenant.
- Permitir uma única configuração SMTP por tenant com índice único. Racional: evita ambiguidade na hora de enviar emails.
- Aceitar senha vazia no update para preservar a senha existente. Racional: o frontend não recebe a senha real e precisa conseguir salvar outros campos sem sobrescrevê-la.
- Usar corpo em texto livre para templates. Racional: entrega a edição necessária agora e preserva variáveis futuras sem acoplar a um editor rico.

## Risks / Trade-offs

- [Senha SMTP armazenada no banco] -> Não retornar a senha pela API e usar campo separado para indicar existência; criptografia em repouso pode ser adicionada depois.
- [Templates sem validação de variáveis] -> Manter validação estrutural mínima nesta etapa e documentar que envio real não faz parte do escopo.
- [Configuração salva mas não testada] -> Não prometer teste de conexão nesta mudança.
