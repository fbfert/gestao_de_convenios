# Configurações de e-mail e templates

## Interface

- A tela `/configuracoes` mantém as abas operacionais: Geral, Envio de emails e Configurações de IA.
- A aba Envio de emails exibe somente os dados SMTP do tenant.
- O botão Templates de E-mails leva para `/configuracoes/templates-emails`.
- A tela de Templates de E-mails permite listar, criar, editar, ativar/inativar e excluir templates do tenant atual.

## API

As rotas exigem autenticação Sanctum e permissão `configuracoes.manage`.

- `GET /api/configuracoes/emails`: retorna SMTP e templates do tenant, mantendo compatibilidade com o contrato original.
- `PUT /api/configuracoes/emails`: salva SMTP; o campo `templates` é opcional.
- `GET /api/configuracoes/emails/templates`: lista templates do tenant.
- `POST /api/configuracoes/emails/templates`: cria template.
- `PUT /api/configuracoes/emails/templates/{emailTemplate}`: atualiza template do mesmo tenant.
- `DELETE /api/configuracoes/emails/templates/{emailTemplate}`: exclui template do mesmo tenant.

## Templates iniciais

`DatabaseSeeder` executa `EmailTemplateSeeder`, que cria templates padrão para todos os tenants existentes. O seeder é idempotente e usa `tenant_id + chave` para atualizar ou criar registros.

Os templates iniciais cobrem comunicações para:

- Pacientes: solicitação recebida, documentos pendentes, guia aprovada, solicitação negada, guia próxima do vencimento, sessão confirmada, sessão cancelada e renovação de autorização.
- Profissionais: nova guia autorizada, lançamentos pendentes, guia próxima do vencimento e resumo de repasse.
- Operadores: nova solicitação, autorização aprovada, autorização negada, guia sem movimento, analítico importado, glosa identificada e fechamento financeiro concluído.

As variáveis são mantidas como placeholders textuais, por exemplo `{{paciente_nome}}`, `{{numero_guia}}`, `{{validade_senha}}` e `{{operador_nome}}`. A validação dessas variáveis e o envio real dos e-mails continuam fora do escopo desta etapa.
