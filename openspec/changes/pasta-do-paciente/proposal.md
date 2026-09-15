## Why

Clicar no nome do paciente em `/pacientes` abria o `PastaDoPacienteDrawer`, uma
gaveta `max-w-xl` sem rota própria. Cabiam o cadastro e os anexos; não cabia nada
do histórico. Quem precisava saber o que já tinha sido pedido, autorizado, lançado
ou antecipado para aquele paciente tinha de sair da gaveta e filtrar quatro telas
diferentes — e três delas (`/solicitacoes`, `/lancamentos`, antecipações) nem
oferecem filtro por paciente.

Havia também um documento que o sistema exigia e jogava fora. Desde
`importar-sessoes-por-ia`, a confirmação de transcrição cobra
`pdf_registro_sessoes` quando o número do cartão é da regional 0220
(`LancamentoController::regiaoExigePdf`). O arquivo era validado, conferido e
descartado ao fim da requisição: nada o gravava. O comprovante daquela remessa de
sessões não existia em lugar nenhum depois.

## What Changes

- A pasta ganha rota própria, `/pacientes/{id}`, substituindo o drawer. O drawer
  continua no código porque `GrupoDocumento` — o bloco de upload/exclusão por tipo
  de documento — é reaproveitado pela página.
- Novo endpoint `GET /api/pacientes/{paciente}/pasta`, que devolve numa resposta o
  cadastro e as cinco listas: solicitações, guias, sessões, antecipações e arquivos.
- Novo tipo de documento `registro_sessoes` em `PacienteArquivo::TIPOS`.
- A folha de registro de sessões passa a ser gravada na pasta do paciente ao
  confirmar a transcrição, com `metadata` amarrando a guia que originou a remessa.

## Decisões

- **Um endpoint, não cinco chamadas às listagens existentes.** As seções abrem
  recolhidas exibindo a contagem, então a tela precisa dos totais antes de qualquer
  expansão; e solicitações, sessões e antecipações não têm filtro por paciente nas
  suas listagens — acrescentá-lo em três serviços para uma tela só espalharia o
  assunto.
- **Seções sempre recolhidas ao abrir**, como os alertas de `/guias`: o histórico de
  um paciente antigo, todo aberto, vira parede de rolagem. Expandir vale para a
  visita e não fica guardado.
- **Sessões chegam pela guia.** `lancamentos` não tem `paciente_id`; o vínculo é
  `whereHas('guia', …)`. Antecipações, do mesmo modo, são por solicitação de origem.
- **O PDF é gravado depois de confirmar, não antes.** Se a confirmação falhar, não
  fica arquivo órfão na pasta.
- **`registro_sessoes` fica fora de `TIPOS_DA_SOLICITACAO` e de `TIPOS_POR_ITEM`.**
  Não pertence à solicitação nem ao item: é o comprovante de uma remessa de sessões.

## Capabilities

### New Capabilities

- `pasta-do-paciente`: tela única com cadastro, histórico e arquivos do paciente.

## Non-Goals

- Nenhum filtro por paciente nas listagens de solicitações, sessões ou antecipações.
- Nenhuma remoção do `PastaDoPacienteDrawer` — `GrupoDocumento` ainda vem dele.
- Nenhuma leitura por IA do registro de sessões já gravado.

## Impact

- **API**: `PacientePastaController` (novo), rota nova sob `permission:dashboard.pacientes`,
  `LancamentoController::guardarRegistroDeSessoes()`, `PacienteArquivo::TIPOS`.
- **Frontend**: `PacientePastaPage.tsx` e `usePacientePasta.ts` (novos), rota em
  `AppRoutes.tsx`, clique de `PacientesPage.tsx` passa a navegar, `GrupoDocumento`
  exportado, `documentoTipos.ts` ganha o tipo e o rótulo.
- **Testes**: `PacientePastaApiTest` (novo, 5 casos) e dois casos novos em
  `LancamentosApiTest` cobrindo a gravação e a não-gravação do PDF.
- **Banco**: nenhuma migration — `paciente_arquivos.tipo` é string livre no schema;
  a restrição é de aplicação.
