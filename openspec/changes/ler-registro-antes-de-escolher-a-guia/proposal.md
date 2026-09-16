## Why

Em `/lancamentos/novo` a leitura só libera depois de escolher a guia e o executante — a ordem está invertida. Quem chega com a folha na mão tem os dados **na folha**: ela traz o número da guia, o paciente, o número do cartão e o nome de quem executou. Obrigar a achar a guia antes é pedir que o operador leia à mão exatamente o que a IA vai ler em seguida, e depois confira o que ele já digitou.

O bloqueio não tem razão técnica. A rota é `POST /guias/{guia}/lancamentos/ler-registro`, mas `RegistroSessoesAiService::analisar()` **nunca recebe a guia**: o `LancamentoController` usa o parâmetro só para o route binding e passa adiante tenant, arquivo e caminho. A guia na URL é decorativa.

E o dado para resolver a guia já vem de volta hoje, descartado pela tela: o serviço devolve `cabecalho.guia_numero`, `paciente`, `numero_cartao`, `profissional_executante` e `terapia_aplicada`, e `aplicarResultado` aproveita só o `numero_cartao`.

## What Changes

- **Nova rota `POST /lancamentos/ler-registro`**, sem guia. A antiga, com guia, fica como alias do mesmo handler por uma versão — deploy da API e do bundle web não são atômicos, e um front em cache chamando uma rota removida daria 404 na única ação da tela.
- **"Ler foto ou PDF do registro" e "Usar webcam" liberam sem guia e sem executante.** Confirmar as sessões continua exigindo os dois: o que muda é a ordem de descobrir, não o que é obrigatório para gravar.
- **A guia é resolvida pelo número lido.** Batendo exatamente uma guia disponível para lançamento, o sistema a escolhe e diz que veio da leitura. Batendo nenhuma ou mais de uma, abre a busca de guia já preenchida com o número lido — em vez de deixar o operador redigitar o que acabou de ser lido.
- **O executante lido é mostrado, nunca preenchido.** A folha é manuscrita, e `LancamentosPage` já registra que executante fora da especialidade gera glosa na conciliação. O nome aparece como "a folha diz: …" ao lado do campo, e a escolha continua sendo de quem confere.

## Capabilities

### Modified Capabilities
- `importacao-de-sessoes`: a leitura do registro deixa de exigir a guia escolhida antes, e passa a alimentar a escolha dela.

> **Ordem de arquivamento.** A capability `importacao-de-sessoes` ainda não está em `openspec/specs/` — nasce na change `importar-sessoes-por-ia`, aberta pela tarefa 3.4 (conferir uma leitura real com a chave OpenAI de produção). Esta change e a `webcam-para-ler-registro-de-sessoes` precisam ser arquivadas **depois** daquela.

## Impact

**API**
- `routes/api.php` — rota nova sem guia; a antiga vira alias
- `app/Http/Controllers/LancamentoController.php` — `lerRegistroSessoes` deixa de receber `Guia`
- `tests/Feature/LancamentosApiTest.php`

**Web**
- `features/lancamentos/LancamentosPage.tsx`
- `features/lancamentos/useLancamentos.ts` — a leitura deixa de precisar do `guiaId`
- `features/lancamentos/SelecionarGuiaModal.tsx` — aceita um termo inicial

**Não faz parte desta change**
- Resolver a guia por paciente ou por número de cartão quando o número da guia não for lido. O número é o identificador; cair para o nome do paciente casaria com várias guias do mesmo paciente e escolheria a errada em silêncio.
- Preencher o executante por semelhança de nome.
- Criar guia a partir da leitura.
