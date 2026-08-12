## Why

`profissionais.especialidade_id` é uma coluna única: cada profissional pertencia a exatamente uma especialidade, sem tabela de ligação. Numa clínica multidisciplinar isso não se sustenta — a mesma pessoa atende em mais de uma terapia, e o cadastro obrigava a escolher.

O efeito prático aparecia no filtro e na seleção de profissional por especialidade: quem atendesse numa especialidade que não fosse a sua simplesmente não aparecia como opção, sem erro nenhum.

## What Changes

- Nova tabela `especialidade_profissional`, com backfill de todo profissional para a própria especialidade atual.
- `profissionais.especialidade_id` permanece como **especialidade principal** — o registro de conselho.
- O filtro por especialidade e a seleção de profissional na solicitação passam a considerar a ligação.
- Tela de Profissionais com seleção múltipla; a principal fica travada marcada.

## Capabilities

### Modified Capabilities

- `crud-profissionais-clinica`: o cadastro passa a registrar em quantas especialidades o profissional atende.

## Impact

- **API**: migration com backfill, relação `especialidades()`, evento `saved` que garante a invariante, `ProfissionalController`, `ProfissionalResource` e os requests.
- **Frontend**: `ProfissionaisPage`, `SolicitacaoItensFields` e os tipos de referência.
- **Banco**: tabela de ligação nova. Nenhum dado existente é alterado além do backfill.

## Decisões

- **A coluna `especialidade_id` não foi removida.** Oito arquivos de teste, o `ProfissionalSeeder` e o `DatabaseSeeder` consultam por ela, e o `UserResource` a expõe. Removê-la nesta mudança trocaria a rede de segurança da própria mudança. Fica como limpeza futura; enquanto isso ela tem um significado definido — a especialidade principal.
- **A invariante "a principal está sempre entre as que atende" vive num evento `saved` do model.** Profissional também nasce de seeder e de factory; por esses caminhos a ligação ficaria vazia e o profissional sumiria dos filtros sem erro. Deixar a garantia só no controller cobriria apenas um dos caminhos — foi exatamente o que quebrou a suíte na primeira tentativa.
- **A principal não pode ser desmarcada na tela.** Um profissional que não atendesse na própria especialidade de registro é um estado incoerente, difícil de perceber e pior de depurar. O servidor reforça, independentemente da tela.
- **O filtro passou a usar a ligação, não a coluna.** Manter a coluna no filtro deixaria a metade do recurso invisível.

## Non-Goals

- O **percentual de repasse continua um só por profissional**, não por especialidade. Se a mesma pessoa precisar de percentuais diferentes conforme a terapia, é outra mudança.
- Não há especialidade principal por convênio nem por período.
- O mapeamento de profissional para a operadora (Unimed RDA) continua por profissional, sem recorte por especialidade.
