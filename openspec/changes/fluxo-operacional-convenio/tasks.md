## 1. Contrato operacional

- [x] 1.1 Ajustar o vocabulário de status da interface para refletir Cadastrado, Em análise, Aprovado, Cancelado, Negado e Vencido, mantendo a persistência técnica em inglês.
- [x] 1.2 Atualizar a navegação e os rótulos de guia/solicitação para reforçar que representam a mesma jornada operacional.
- [x] 1.3 Revisar os testes de interface afetados pelo novo vocabulário de status.

## 2. Sessões e continuidade

- [x] 2.1 Criar o modelo/CRUD de sessões com vínculo à guia, paciente, profissional e antecipação.
- [x] 2.2 Implementar a impressão em branco do registro de sessões.
- [x] 2.3 Implementar a transcrição automática de foto/documento do registro de sessões.
- [x] 2.4 Adicionar alertas para pacientes sem próximos agendamentos em fluxo ativo.

## 3. Finalização e Unimed

- [x] 3.1 Implementar a confirmação manual antes do envio do registro de sessões.
- [x] 3.2 Exigir o PDF do registro de sessões para a regional 0220 e manter opcional para as demais.
- [x] 3.3 Estruturar o importador do analítico da Unimed em Excel.

## 4. Conciliação e repasse

- [x] 4.1 Transformar as linhas do analítico importado em dados processáveis de conciliação.
- [x] 4.2 Calcular o valor pago por sessão e o repasse configurável por profissional.
- [x] 4.3 Registrar entradas, saídas e a distinção entre profissional informado ao plano e profissional executor.

## 5. Validação

- [ ] 5.1 Executar testes de API, testes e2e do fluxo afetado, build do frontend e `openspec validate`.
- [ ] 5.2 Validar o fluxo no navegador local após a implementação da primeira fatia.
