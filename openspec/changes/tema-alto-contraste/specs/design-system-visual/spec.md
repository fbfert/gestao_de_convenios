## MODIFIED Requirements

### Requirement: Temas do sistema
O sistema SHALL manter o tema `claro` como padrão e SHALL oferecer tema adicional apenas quando
houver necessidade de uso que o justifique — acessibilidade, condição de ambiente ou requisito de
operação —, nunca por preferência estética. Todo tema SHALL redefinir somente os papéis semânticos,
e SHALL passar pela verificação de contraste do contrato de design.

Esta requisição substitui a anterior ("Tema único"), que proibia alternância de aparência. A
proibição existia porque o tema escuro removido em 26/08/2026 não tinha uso e divergia a cada
mudança de token; ela não deve impedir um tema que atende requisito real de acessibilidade.

#### Scenario: Preferência antiga salva no navegador
- **WHEN** o navegador tiver gravado um tema que não existe mais
- **THEN** o sistema SHALL aplicar o tema padrão

#### Scenario: Tema novo entra no sistema
- **WHEN** um tema for adicionado
- **THEN** ele SHALL redefinir apenas os papéis semânticos, e o contrato de design SHALL verificar
  o contraste dos pares documentados nesse tema

#### Scenario: Pedido de tema por preferência
- **WHEN** a motivação para um tema novo for apenas estética
- **THEN** o sistema SHALL NOT ganhar o tema, porque tema sem uso não é testado e diverge
