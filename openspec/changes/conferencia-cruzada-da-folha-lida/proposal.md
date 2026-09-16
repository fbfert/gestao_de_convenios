## Why

Desde `931f647` o número de guia lido na folha escolhe a guia sozinho. Isso resolveu o trabalho manual e abriu um risco novo, mais caro que o antigo.

Número ilegível não é o problema: ele não resolve para nada e já cai na busca preenchida, que é inofensivo. O caso perigoso é **um dígito lido errado que acerta outra guia real**. Aí a tela seleciona com confiança, o operador confirma, e as sessões entram na cota de outro paciente — exatamente o que o tooltip da própria tela avisa ("lançar contra a guia errada consome a cota de outro paciente ou especialidade"). Hoje nada distingue esse caso de um acerto.

A defesa já está no payload, sem custo nenhum. A folha traz **dois** identificadores independentes: o número da guia e o paciente (nome e número do cartão). A guia encontrada traz `paciente.nome` e `paciente.carteirinha` (`GuiaResource`). Um dígito errado no número da guia cai numa guia de **outro** paciente — e é isso que a comparação enxerga.

## What Changes

- **Conferência cruzada**: sempre que houver uma folha lida e uma guia escolhida, o sistema compara o paciente da folha com o paciente da guia, pelo número do cartão quando legível e pelo nome como reforço.
- **A escolha automática exige que os dois fechem.** Divergindo, o sistema NÃO escolhe: abre a busca dizendo o que não bateu.
- **Aviso permanente e explícito** enquanto a guia escolhida contradisser a folha, dizendo os dois lados ("a folha diz X, a guia é de Y"). Vale também para a escolha manual: o perigo é o mesmo, venha de onde vier.
- **Confirmação com justificativa obrigatória** ao confirmar as sessões sob divergência. Não é um "tem certeza?": exige texto, mostra o conflito e grava quem decidiu e por quê na trilha de auditoria (`Auditoria::registrar`, sem tabela nova).

Divergir não bloqueia: há motivos legítimos (paciente que trocou de nome, cartão reemitido, folha do paciente certo com cabeçalho antigo). O que não pode é passar **em silêncio**.

## Como a comparação evita o alarme falso

Um aviso que dispara à toa é pior que nenhum: ensina a clicar sem ler, e aí deixa de proteger no dia que importa. Por isso a comparação é deliberadamente tolerante, e só acusa o que não tem explicação inocente:

- **Cartão**: compara só dígitos. Um contendo o outro conta como conferido — leitura parcial é comum e não é contradição. Só decide quando os dois lados têm ao menos 6 dígitos.
- **Nome**: normaliza (minúsculas, sem acento, sem pontuação) e basta o primeiro **ou** o último nome bater. "ANA P. RIBEIRO" e "Ana Paula Ribeiro" fecham.
- **Precedência**: havendo cartão dos dois lados, é ele que decide — é impresso, e erra muito menos que nome manuscrito. O nome só é consultado quando não há cartão comparável.
- **Sem dado na folha**: nada a conferir, nenhum aviso.

## Capabilities

### Modified Capabilities
- `importacao-de-sessoes`: a escolha da guia pela leitura passa a exigir que o paciente feche, e a divergência passa a exigir justificativa registrada.

> **Ordem de arquivamento.** `importacao-de-sessoes` ainda não está em `openspec/specs/` — nasce na change `importar-sessoes-por-ia`, aberta pela tarefa 3.4. Esta change entra depois de `webcam-para-ler-registro-de-sessoes` e de `ler-registro-antes-de-escolher-a-guia`, cujas MODIFIED ela continua.

## Impact

**API**
- `app/Http/Requests/ImportLancamentosTranscricaoRequest.php` — aceita `divergencia` e `divergencia_justificativa`, com a justificativa obrigatória quando há divergência declarada
- `app/Http/Controllers/LancamentoController.php` — registra o evento de auditoria
- `tests/Feature/LancamentosApiTest.php`

**Web**
- `features/lancamentos/conferenciaDaFolha.ts` (novo) — a comparação, isolada e testável
- `features/lancamentos/ConfirmarDivergenciaModal.tsx` (novo)
- `features/lancamentos/LancamentosPage.tsx`, `useLancamentos.ts`, `types.ts`

**Não faz parte desta change**
- Bloquear o lançamento sob divergência. A decisão é de quem tem a folha na mão; o sistema exige que ela seja consciente e fique registrada.
- Validar a divergência no servidor. Ele não viu a folha: só o cliente sabe o que foi lido, então a API registra a declaração em vez de recalculá-la. É guarda de operação, não controle de acesso — e o `Não faz parte` existe para que ninguém leia o contrário depois.
- Conferir especialidade ou terapia da folha contra a guia.
