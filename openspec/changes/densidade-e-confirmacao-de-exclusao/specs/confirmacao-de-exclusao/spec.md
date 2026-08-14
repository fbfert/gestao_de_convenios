## ADDED Requirements

### Requirement: Confirmação em duas etapas para exclusão sem volta
O sistema SHALL exigir, antes de excluir um anexo, uma confirmação em duas etapas: um diálogo identificando o que será apagado e a digitação da palavra `EXCLUIR`.

#### Scenario: Excluir um anexo
- **WHEN** o usuário acionar a exclusão de um anexo
- **THEN** o sistema SHALL abrir um diálogo com o nome do arquivo e a consequência da exclusão

#### Scenario: Liberar a exclusão
- **WHEN** o usuário digitar a palavra de confirmação
- **THEN** o sistema SHALL habilitar a ação de excluir

#### Scenario: Desistir
- **WHEN** o usuário cancelar ou fechar o diálogo
- **THEN** o sistema SHALL manter o anexo intacto
