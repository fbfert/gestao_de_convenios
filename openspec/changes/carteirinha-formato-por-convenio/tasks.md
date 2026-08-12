## 1. Formato como dado do convênio

- [x] 1.1 Migration `2026_08_12_200000`: coluna `convenios.carteirinha_blocos` (JSON, nullable).
- [x] 1.2 `Convenio::blocosCarteirinha()` e `tamanhoCarteirinha()`, com cast de array.
- [x] 1.3 Expor o formato em `ConvenioResource` e no convênio aninhado de `PacienteResource`.
- [x] 1.4 `UpsertConvenioRequest` aceita a lista, com limites de sanidade e `[]` normalizado para `null`.

## 2. Validação e gravação

- [x] 2.1 Extrair a regra para a trait `ValidaCarteirinhaPorConvenio`, usada por Store e Update de paciente.
- [x] 2.2 Derivar a exigência dos blocos do convênio em vez do `connector_driver`.
- [x] 2.3 Normalizar para só dígitos quando o convênio declara formato; preservar o texto quando não declara.
- [x] 2.4 Aceitar o campo antigo `carteirinha_unimed` além do novo `carteirinha_blocos`.

## 3. Interface

- [x] 3.1 Generalizar `lib/carteirinha` para receber os blocos por parâmetro.
- [x] 3.2 `CarteirinhaUnimedInput` vira `CarteirinhaBlocosInput`, com colunas proporcionais ao tamanho de cada bloco.
- [x] 3.3 `PacientesPage` e `LerPedidoMedicoPage` passam a ler `carteirinha_blocos` do convênio.
- [x] 3.4 Campo **Formato da carteirinha** na tela de Convênios, com atalho para o padrão Unimed.

## 4. Validação

- [x] 4.1 Reescrever o teste que codificava o acoplamento com o `connector_driver`.
- [x] 4.2 Casos novos: driver ligado sem formato não impõe validação; formato funciona em convênio sem automação.
- [x] 4.3 Suíte da API e `tsc`/`oxlint` do web.
- [x] 4.4 Verificação em produção: carteirinha válida gravada, curta recusada, outro convênio em texto livre.
- [ ] 4.5 Conferência visual do campo em blocos nos dois temas.
