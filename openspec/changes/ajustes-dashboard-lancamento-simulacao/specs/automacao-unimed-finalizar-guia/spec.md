## MODIFIED Requirements

### Requirement: Modo simulação

O sistema SHALL oferecer um modo de simulação, ligado por configuração, em que a finalização percorre o portal por inteiro — localiza a guia, preenche a execução, anexa as folhas — e **para antes do passo que grava e finaliza**.

O sistema SHALL permitir ligar e desligar o modo simulação na tela de configurações, por clínica, e SHALL pedir confirmação ao desligá-lo, informando que a finalização passará a gravar e finalizar a guia na operadora.

O sistema SHALL guardar evidência do que foi preenchido na simulação, de forma que uma pessoa consiga conferir no portal o que teria sido enviado.

O sistema SHALL deixar claro, em toda parte onde a execução apareça, que ela correu em simulação.

O sistema SHALL NOT alterar a situação da guia no Gescon a partir de uma execução em simulação.

#### Scenario: Simulação ligada
- **WHEN** o modo simulação estiver ligado e o operador finalizar uma guia
- **THEN** o sistema SHALL preencher e anexar tudo, SHALL parar antes de gravar e finalizar, e SHALL registrar a execução como simulada

#### Scenario: Guia não muda de situação na simulação
- **WHEN** uma execução simulada terminar com sucesso
- **THEN** o sistema SHALL NOT dar a guia por finalizada

#### Scenario: Simulação desligada
- **WHEN** o modo simulação estiver desligado
- **THEN** o sistema SHALL concluir a finalização de verdade no portal

#### Scenario: Desligar pela tela
- **WHEN** quem administra configurações desligar o modo simulação e confirmar
- **THEN** o sistema SHALL gravar a escolha para a clínica, e a próxima finalização SHALL correr fora da simulação
