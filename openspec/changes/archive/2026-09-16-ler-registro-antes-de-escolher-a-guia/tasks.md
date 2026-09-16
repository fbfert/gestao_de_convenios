> Implementado em 16/09/2026. API: 674 testes. E2E: 26. `npx tsc -b`,
> `npm run lint` e `npm run build` sem erro; `pint` passou nos arquivos tocados.

## 1. API — a leitura deixa de exigir a guia

- [x] 1.1 `LancamentoController::lerRegistroSessoes` deixa de receber `Guia`. O parâmetro nunca chegou a `RegistroSessoesAiService::analisar()`, que recebe tenant, arquivo e caminho — era route binding e nada mais.
- [x] 1.2 Rota `POST /lancamentos/ler-registro`, mesma permissão da anterior.
- [x] 1.3 `POST /guias/{guia}/lancamentos/ler-registro` mantida como alias do mesmo handler por uma versão. A API e o bundle web não sobem no mesmo instante: um front ainda em cache chamando a rota removida daria 404 na única ação da tela. A guia do caminho é ignorada, como sempre foi.
- [x] 1.4 `LancamentosApiTest`: `test_le_registro_de_sessoes_escaneado_por_ia` passou a usar a rota sem guia e confere que o alias continua respondendo; `test_leitura_devolve_o_numero_da_guia_para_a_tela_resolver` prova que `guia_numero` e `profissional_executante` voltam no payload — sem eles a tela não teria como resolver a guia e o fluxo novo viraria a escolha manual de sempre.

## 2. Web — ler primeiro

- [x] 2.1 `useLerRegistroSessoes` recebe só o arquivo.
- [x] 2.2 "Ler foto ou PDF do registro" e "Usar webcam" habilitados sem guia e sem executante.
- [x] 2.3 `ler()` deixou de sair cedo quando não há guia.
- [x] 2.4 O aviso ao lado dos botões passou a dizer que a guia vem escolhida quando a folha tiver o número.
- [x] 2.5 `prontoParaLer` virou `prontoParaAnalisarTexto`: depois desta change ele governa só o caminho do texto colado, que posta em `/guias/{id}/lancamentos/importar-transcricao` — com guia no caminho e executante no corpo — e portanto continua exigindo os dois. O nome antigo passaria a mentir.

## 3. Web — a leitura alimenta a escolha da guia

- [x] 3.1 `buscarGuiasDisponiveis()` consulta `/guias?busca=…&disponivel_para_lancamento=1`. Função, e não hook: roda depois de a leitura terminar — um evento, não um estado de render. Mesma consulta do `SelecionarGuiaModal`, então "disponível para lançamento" quer dizer o mesmo nos dois lugares.
- [x] 3.2 Uma só: seleciona e marca na tela que veio da leitura (`lancamento-guia-da-leitura`). Uma guia que se preenche sozinha sem dizer por quê é pior que uma em branco — ninguém confere o que não sabe que foi decidido por outro.
- [x] 3.3 Nenhuma ou várias: abre `SelecionarGuiaModal` com o número lido já preenchido. Falha na busca cai no mesmo caminho: derrubar a leitura, que é a parte cara, por causa de uma consulta barata seria o pior negócio possível.
- [x] 3.4 Sem número lido: não abre nada e não escolhe por outro dado.
- [x] 3.5 `SelecionarGuiaModal` aceita `termoInicial`.

## 4. Web — executante lido é só informação

- [x] 4.1 `cabecalho.profissional_executante` aparece como "A folha diz: …" ao lado do campo, sem preencher.

## 5. Verificação

- [x] 5.1 Suíte da API verde: 674 testes.
- [x] 5.2 `npx tsc -b`, `npm run lint`, `npm run build`.
- [x] 5.3 E2E em `lancamento-webcam.spec.ts`: os dois caminhos de leitura liberam com a tela recém-aberta (e o texto colado não); o número lido resolve a guia e a marca como vinda da leitura; número que não resolve abre a busca preenchida sem escolher nada; registro sem número legível não abre nada nem escolhe por paciente.
- [x] 5.4 Suíte e2e verde: 26 testes.

**Armadilha achada ao escrever o e2e**, que vale para qualquer teste futuro desta tela: guia criada sem `sessoes_solicitadas` nem `sessoes_autorizadas` **nunca** é "disponível para lançamento" — o filtro é `COALESCE(autorizadas, solicitadas, 0) > lançadas`, e nulo vira zero. O cenário antigo passava porque chegava na tela por `?guia_id=`, que lê a guia direto; a resolução pelo número lido passa pelo filtro, e a guia sem quantidade sumia.

## 6. Em aberto

- [x] 6.1 Conferir no navegador, com a chave OpenAI de produção, se a IA lê o número da guia de forma confiável numa folha real. O e2e intercepta a leitura e injeta o `guia_numero`: prova que a tela resolve a guia a partir dele, não que a IA o extraia bem. Mesma passada manual da tarefa 3.4 da change `importar-sessoes-por-ia` e da 5.1 da `webcam-para-ler-registro-de-sessoes`.

  **Conferido em 16/09/2026**, em produção: a leitura foi aprovada pelo responsável do produto. A escolha automática fica como está, sem o passo de confirmação extra que estava previsto caso o número viesse errado com frequência.

  A rede de segurança que continua valendo, se algum dia vier errado: o número que não resolve cai na busca preenchida, e o que resolve para a guia de outro paciente é barrado pela conferência cruzada (change `conferencia-cruzada-da-folha-lida`).
