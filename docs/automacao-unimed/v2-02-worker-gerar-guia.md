# Automação Unimed v2 - Etapa 2 Worker Gerar Guia

## Escopo

Esta etapa substitui o mock da operação `gerar_guia` por um worker Node com Playwright. A implementação usa fixtures HTML locais nos testes e não acessa o portal real da Unimed.

## Contrato HTTP

O Laravel chama `POST /operations/gerar_guia` com:

- `execution_id`
- `idempotency_key`
- `payload`

O payload contém credencial em memória, paciente com carteirinha normalizada, médico solicitante, CID, código de procedimento, quantidade, profissional executante e anexos. O worker não acessa banco de dados e não persiste credenciais.

## Máquina de Estados

1. Login no portal.
2. Novo exame, ignorar cartão e cadastro de beneficiário.
3. Preenchimento da carteirinha em blocos `CD_UNIMED`, `CD_CARTAO`, `CD_BENEF`, `CD_DEPEN`, `NR_DV`.
4. Se houver restrição administrativa, retorna `needs_verification` sem número de guia.
5. Se houver atualização cadastral, clica em atualizar sem alterar dados.
6. Entra em SP/SADT manual.
7. Preenche formulário principal com CID e parâmetros fixos do fluxo.
8. Seleciona prestador por CRM, nome ou fallback "nao cooperado".
9. Preenche procedimento, quantidade e campos genéricos quando exibidos.
10. Envia anexos, com Pedido Médico obrigatório.
11. Seleciona profissional executante pelo código da operadora.
12. Clica uma vez em "Finalizar e Gerar guia".
13. Lê protocolo, guia, situação, sessões e senha quando retornados.

## Status

O texto da operadora é preservado em `unimed_status`/`status_operadora`. O worker mapeia:

- `Autorizado` ou `Em execução`: `approved`
- `Em estudo` ou `Em Análise`: `under_review`
- `Negado`: `denied`
- `Cancelado`: `canceled`
- Restrição administrativa: `needs_verification`, sem `numero_guia`

## Códigos de Erro

- `LOGIN_ERROR`: credencial recusada.
- `CONFIGURATION_INVALID_CARD`: carteirinha inválida para Unimed.
- `CONFIGURATION_INVALID_ITEM`: procedimento ausente.
- `CONFIGURATION_INVALID_EXECUTOR`: código do profissional ausente.
- `PRESTADOR_SOLICITANTE_NOT_FOUND`: nenhum prestador ativo encontrado.
- `PROFISSIONAL_EXECUTANTE_NOT_FOUND`: código executante não aparece no select.
- `PEDIDO_MEDICO_REQUIRED`: payload não trouxe pedido médico.
- `PEDIDO_MEDICO_UPLOAD_FAILED`: upload obrigatório não foi confirmado.
- `UPLOAD_TYPE_NOT_ALLOWED`: extensão não aceita.
- `UPLOAD_TOO_LARGE`: arquivo acima de 5 MB.
- `UNCERTAIN_AFTER_SUBMIT`: timeout ou resposta ambígua depois do submit final; o worker não faz retry.
- `WORKER_INTERNAL_FATAL`: falha inesperada no worker.

## Segurança

Credenciais entram somente no payload enviado pelo Laravel ao worker local. O worker não registra login, senha, carteirinha completa ou arquivos em logs. Fixtures de teste não contêm dados reais.

## Homologação

Esta etapa não valida seletores contra `rda.unimedsc.com.br`. A confirmação contra portal real fica para a Etapa 5, com responsável presente e credencial fornecida fora do chat.
