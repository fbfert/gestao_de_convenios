## Why

A clínica precisa configurar o envio de emails sem alterar arquivos de ambiente ou código. Centralizar SMTP e templates em Configurações permite que cada tenant ajuste remetente, autenticação e mensagens operacionais pela interface.

## What Changes

- Criar uma subaba "Envio de emails" dentro de Configurações.
- Permitir salvar dados SMTP do tenant, incluindo host, porta, usuário, senha, criptografia e remetente.
- Permitir manter templates de emails com chave, assunto e corpo.
- Não expor a senha SMTP já salva na resposta da API; exibir apenas indicador de senha configurada.

## Capabilities

### New Capabilities
- `configuracoes-envio-emails`: Configuração de SMTP e templates de email por tenant.

### Modified Capabilities

## Impact

- API Laravel: nova persistência por tenant, resource/request/controller e rotas autenticadas.
- Frontend React: subaba em Configurações, formulário SMTP e edição de templates.
- Banco de dados: novas tabelas para configuração SMTP e templates de email.
