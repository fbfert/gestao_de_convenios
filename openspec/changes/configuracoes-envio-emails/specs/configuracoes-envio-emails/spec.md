## ADDED Requirements

### Requirement: Subaba de envio de emails
O sistema SHALL disponibilizar uma subaba "Envio de emails" dentro da página de Configurações.

#### Scenario: Abrir subaba
- **WHEN** o usuário acessar Configurações
- **THEN** o sistema SHALL permitir selecionar a subaba "Envio de emails"

### Requirement: Configuração SMTP por tenant
O sistema SHALL permitir salvar uma configuração SMTP por tenant com host, porta, usuário, senha, criptografia, remetente e estado ativo.

#### Scenario: Salvar dados SMTP
- **WHEN** o operador preencher os dados SMTP obrigatórios e salvar
- **THEN** o sistema SHALL persistir a configuração para o tenant atual

#### Scenario: Preservar senha existente
- **WHEN** uma configuração SMTP já possuir senha salva e o operador salvar o formulário sem informar nova senha
- **THEN** o sistema SHALL preservar a senha existente

#### Scenario: Não expor senha salva
- **WHEN** o sistema carregar a configuração SMTP
- **THEN** a resposta SHALL NOT incluir a senha salva em texto claro

### Requirement: Templates de emails por tenant
O sistema SHALL permitir manter templates de email por tenant com chave, nome, assunto, corpo e estado ativo.

#### Scenario: Salvar template
- **WHEN** o operador criar ou alterar um template de email
- **THEN** o sistema SHALL persistir chave, nome, assunto, corpo e estado ativo para o tenant atual

#### Scenario: Listar templates
- **WHEN** o usuário abrir a subaba de envio de emails
- **THEN** o sistema SHALL listar os templates do tenant atual
