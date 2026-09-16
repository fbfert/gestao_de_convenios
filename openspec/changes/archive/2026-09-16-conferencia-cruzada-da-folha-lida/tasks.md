> Implementado em 16/09/2026. API: 678 testes. E2E: 45. `npx tsc -b`,
> `npm run lint` e `npm run build` sem erro; `pint` passou.

## 1. A comparação

- [x] 1.1 `features/lancamentos/conferenciaDaFolha.ts`, fora do componente: a regra decide se as sessões vão para a cota certa, e precisa poder ser lida e testada sem montar a tela.
- [x] 1.2 Cartão só por dígitos, um contendo o outro conta como conferido, e só decide com 6+ dígitos dos dois lados.
- [x] 1.3 Nome normalizado (minúsculas, sem acento, sem pontuação) e **dois** pedaços em comum para confirmar.
- [x] 1.4 `descreverDivergencia()` — uma frase só, para a tela e a auditoria contarem a mesma história.

## 2. API

- [x] 2.1 `ImportLancamentosTranscricaoRequest` aceita `divergencia` e `divergencia_justificativa`, com `required_with` e mínimo de 10 caracteres.
- [x] 2.2 `LancamentoController` registra `lancamento_divergencia_confirmada` via `Auditoria::registrar`, contra a GUIA — é a cota dela que foi consumida, e é no histórico dela que alguém vai perguntar depois por que essas sessões estão ali. Sem tabela nova.
- [x] 2.3 Testes: registro com autor e payload, recusa sem justificativa, recusa com justificativa curta, e ausência de evento no caso normal (para o caminho comum não virar ruído na trilha).

## 3. Web

- [x] 3.1 `conferencia` recalculada a cada render, e não guardada em estado: depende da leitura e da escolha da guia, que mudam por caminhos independentes — um esquecimento de recalcular deixaria o aviso desatualizado sem nada acusar.
- [x] 3.2 A escolha automática exige que o paciente feche. Divergindo, não escolhe: abre a busca dizendo o que não bateu.
- [x] 3.3 Aviso permanente (`lancamento-divergencia-aviso`) nomeando os dois lados, valendo também para a escolha manual.
- [x] 3.4 `ConfirmarDivergenciaModal` na confirmação, com justificativa obrigatória. A trava fica no gravar, e não na escolha da guia: é o passo irreversível, e o único por onde passam a escolha automática, a manual, e o caso de escolher a guia antes de ler a folha.
- [x] 3.5 `aplicarResultado` passou a guardar o paciente lido, alimentando os DOIS caminhos de leitura (ver 5.1).

## 4. Testes

- [x] 4.1 `conferencia-da-folha.spec.ts` — 16 casos da função pura, separados em "o que NÃO é divergência" e "o que É". Vive num spec do Playwright porque o projeto não tem runner de unidade, e um teste que não toca em `page` roda como qualquer outro.
- [x] 4.2 E2E do fluxo: número que cai em guia de outro paciente não é escolhido sozinho; escolher essa guia à mão avisa e exige justificativa; justificativa curta é recusada; cancelar não grava; folha que confere não pede nada.
- [x] 4.3 Suíte e2e verde: 45 testes.

## 5. O que a implementação mudou de plano

- [x] 5.1 **O `mvp-flow` quebrou, e estava certo em quebrar.** A folha colada ali diz `Paciente: Ana Paula Ribeiro` e `Número Cartão: 0220 090000 551.330-8`; a carteirinha da semente é `UNI-2026-0001`. Nome conferia, cartão não. Duas correções saíram disso:

  - A regra virou **qualquer confirmação basta**: nome conferindo limpa um cartão que não bate, e vice-versa. Só discordando um dos dois é quase sempre qualidade de dado — carteirinha antiga, paciente migrado, cartão reemitido, número guardado em formato diferente do impresso. O que se quer pegar é a guia de OUTRA pessoa, e aí nenhum dos dois confirma.
  - `aplicarResultado` passou a guardar o paciente lido. Antes só o caminho da IA preenchia, então o texto colado ficava com cartão e sem nome — um lado só, e a conferência acusava sempre que o formato da carteirinha diferisse.

  Sem esse fixture o defeito teria ido para produção e disparado o aviso na primeira folha real.

- [x] 5.2 **Preço consciente**: dois pacientes de nome igual com cartões diferentes deixam de ser acusados. É mais raro que o ruído de cadastro, e a alternativa era pior — aviso que dispara toda hora ensina a clicar sem ler, e aí não protege no dia que importa. Há teste nomeando a limitação, para que mudá-la seja decisão e não acidente.

- [x] 5.3 O piso do nome subiu de "primeiro **ou** último" para **dois pedaços em comum**: uma clínica tem muitas Marias, e "Maria Silva" x "Maria Santos" passando seria confirmação por coincidência.

## 6. Em aberto

- [x] 6.1 Conferir na passada com folhas reais **quantas vezes o aviso dispara**. Junto com a 3.4 da `importar-sessoes-por-ia`, a 5.1 da `webcam-para-ler-registro-de-sessoes` e a 6.1 da `ler-registro-antes-de-escolher-a-guia`.

  **Conferido em 16/09/2026**, em produção: a leitura foi aprovada pelo responsável do produto, sem relato de aviso em folha legítima. A regra fica como está.

  **O que observar daqui em diante**, porque uma passada não é uma amostra: se o aviso começar a aparecer em folha legítima, o caminho **não** é afrouxar mais a regra — é olhar o dado. O suspeito é `pacientes.carteirinha` não ser o número impresso na folha (paciente migrado de sistema antigo, cartão reemitido, placeholder herdado). Nesse caso a comparação de cartão sai e fica só o nome, em `features/lancamentos/conferenciaDaFolha.ts`.
