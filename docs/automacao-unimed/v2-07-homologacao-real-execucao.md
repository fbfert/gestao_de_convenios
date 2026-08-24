# Automacao Unimed v2 - Etapa 7: Homologacao real executada

Data: 2026-08-24
Ambiente: producao (`gescon.gestaonossa.com.br`), portal real `rda.unimedsc.com.br`
Caso: Solicitacao aprovada da paciente Isabella Freitas de Oliveira (`solicitacao_item_id = 2`)
Decisao final: GO para o caso "Guia aprovada diretamente" (caso 1 do roteiro da Etapa 5)

## Regras da sessao (mantidas da Etapa 5)

- Nenhuma senha, carteirinha completa ou documento de paciente foi registrado
  neste arquivo — os valores abaixo estao mascarados.
- Toda alteracao de codigo desta etapa corrige defeitos encontrados ao vivo,
  confirmados no portal real antes de ir pro commit.

## Contexto

O botao manual "Enviar para Unimed" estava disponivel desde a Etapa 6, mas
nunca tinha sido validado contra o portal real de ponta a ponta — a Etapa 5
ficou com o roteiro inteiro em `Pendente`. Esta etapa é essa primeira execucao
real, motivada por uma solicitacao real da clinica (Isabella) que nao
conseguia ser enviada.

## Causas raiz encontradas e corrigidas

Investigado com scripts de diagnostico descartaveis rodando o worker de
verdade contra `rda.unimedsc.com.br` (nunca so contra a fixture), cada causa
confirmada ao vivo antes da correcao. Commit: `5d14b69`.

1. **Formulario de credenciais com `<form>` aninhado** — submeter os
   mapeamentos de Especialidade/Profissional na tela de Configuracoes
   submetia por engano o formulario de credencial Unimed por cima.
2. **Link de busca de prestador errado** — `#link_busca_contrt` (usado pro
   profissional solicitante) busca CONTRATO, nao SOLICITANTE; o campo certo
   e `#link_busca_solic`.
3. **Idempotencia sem atualizacao de payload** — `AutomacaoService::enfileirar()`
   e idempotente por `(tenant, operacao, item, guia)`, o que impede duplicar
   uma execucao em andamento, mas fazia `return $existing` mesmo apos uma
   falha anterior — toda correcao de CRM/CID/codigo do profissional feita
   depois de um erro nunca chegava ao worker, porque `payload` ficava
   congelado na tentativa original. Corrigido: so devolve a execucao existente
   quando ativa (`queued`/`running`) ou ja concluida com sucesso; se falhou
   antes, atualiza o payload e reenfileira de verdade.
4. **Campo "Nome do Contratado" nunca preenchido** — campo obrigatorio
   separado do profissional solicitante (e a propria clinica). Sem ele o
   Finalizar falhava com "O valor do campo Nome do contratado é obrigatório",
   sem nenhum sinal pro worker. Adicionado `unimed_rda_credentials.nome_contratado`
   (configuravel em Configuracoes > Unimed) e um passo no worker que busca e
   seleciona o contratado — com espera proposital antes de fechar essa popup,
   porque fechar cedo demais interrompe o postback assincrono do portal que
   preenche o campo.
5. **Parser de resultado usando seletor inexistente** — `#resultado-guia`
   nunca existiu na pagina real de confirmacao, nem no caminho de sucesso;
   toda solicitacao aceita pelo portal ainda assim voltava `uncertain` pro
   gescon. A confirmacao real (`lista_impressao.do`) e uma tabela comum sem
   id; o parser agora le pelas colunas do cabecalho.
6. **Volume de storage nao compartilhado** — `gescon-worker` nao tinha acesso
   ao storage do `gescon-app`; o pedido medico (anexo obrigatorio) dava
   `ENOENT`. Resolvido compartilhando o volume `gescon_storage` (leitura).
7. **Timeout curto demais** — `UNIMED_WORKER_TIMEOUT=20` nao dava conta do
   fluxo real (varias popups, upload, espera ativa de opcao no select),
   bem mais lento que o mock. Subido pra 180s.

## Resultado da execucao real

| Campo | Valor |
|---|---|
| Paciente | Isabella Freitas de Oliveira |
| Profissional | Mariana dos Santos (estrategia: CRM) |
| Especialidade | Psicologia ABA |
| Procedimento | `2250005286` |
| Nº da guia | `50143930233` |
| Situacao no portal | "Em execução" -> mapeada para `approved` |
| Senha de autorizacao | capturada e gravada no banco (mascarada aqui) |
| Sessoes solicitadas / autorizadas | 10 / 10 |
| Protocolo de atendimento | nao retornado pelo portal nesta guia |

A guia foi criada de fato no portal Unimed atraves de um script de
diagnostico rodando o worker corrigido (nao atraves da fila normal — a
correcao da idempotencia so foi deployada depois). Para nao gerar uma
duplicata no portal, o resultado real **nao foi reenviado** pela automacao:
foi registrado diretamente via `GerarGuiaUnimedService::aplicarResultado()`,
criando a `Guia` local (visivel em `/guias`) com os dados reais acima.

## Pendencias e proximos passos

- Casos 2 a 15 do roteiro da Etapa 5 (`v2-05-homologacao-real.md`) seguem
  sem execucao real — negado, restricao administrativa, atualizacao
  cadastral, fallback por nome/"MEDICO NAO COOPERADO", campos genericos,
  consulta de status/senha, timeout antes do submit, resultado incerto, e
  mudanca de estrutura do portal.
- Confirmacao manual pendente dos codigos de operadora com correspondencia
  moderada/baixa de nome (mapeamentos de profissional x convenio),
  levantados numa correcao anterior e nao aplicados automaticamente por
  falta de confianca suficiente no match de nome.
- Este roteiro de descoberta foi todo manual (scripts de diagnostico
  descartaveis); nao ha ainda um E2E automatizado contra o portal real —
  os 14 testes do worker (`node --test`) continuam rodando so contra a
  fixture local, agora reescrita pra refletir a arquitetura de popups real.
