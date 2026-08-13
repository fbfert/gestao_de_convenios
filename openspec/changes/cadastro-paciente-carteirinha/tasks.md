## Decisões (2026-08-13)

Telefones em tabela própria, com rótulo, nome do contato e principal. Imagem da
carteirinha capturada por câmera ou arquivo, lida por IA para carteirinha, nome,
convênio, CPF, validade e nascimento. Imagem guardada por 30 dias, configurável.
Carteirinha vencida avisa, sem bloquear. CPF opcional com dígitos verificadores
conferidos. `clinica_agil_id` some da tela, mas fica no banco.

## 1. Banco

- [x] 1.1 `paciente_telefones`: número, rótulo, nome do contato, principal e ordem.
- [x] 1.2 `pacientes`: `validade_carteirinha` e `data_nascimento`, ambas nullable.
- [x] 1.3 `paciente_documentos`: tipo, caminho, mime, nome original e data de expiração.
- [x] 1.4 `configuracoes_globais`: `carteirinha_retencao_dias`, padrão 30.

## 2. Cadastro de paciente

- [x] 2.1 CPF: só dígitos, dígitos verificadores conferidos, gravado sem formatação, opcional.
- [x] 2.2 Telefones no payload de criação e edição, com um único principal.
- [x] 2.3 Validade e nascimento no payload e no recurso devolvido.
- [x] 2.4 `clinica_agil_id` deixa de ser aceito pelo formulário sem apagar o que já existe.
- [x] 2.5 `storePacienteRapido` (fluxo de solicitação) continua funcionando com o modelo novo.

## 3. Leitura da carteirinha

- [x] 3.1 Chave de prompt `ler_carteirinha` entre as de sistema, com padrão criado junto das demais.
- [x] 3.2 `CarteirinhaAiService`: envia imagem, recebe JSON, casa o convênio lido com o cadastro.
- [x] 3.3 `POST /pacientes/ler-carteirinha` devolvendo dados, sugestão de convênio e referência do arquivo.
- [x] 3.4 Número lido normalizado para o formato de blocos do convênio identificado.
- [x] 3.5 Vincular a imagem ao paciente na gravação, com data de expiração.

## 4. Expurgo da imagem

- [x] 4.1 Job diário apagando arquivo e registro vencidos.
- [x] 4.2 Imagem lida e nunca vinculada a paciente também expira.

## 5. Tela

- [x] 5.1 Convênio como primeira pergunta, sem pré-seleção.
- [x] 5.2 `Nome` vira `Nome Completo`.
- [x] 5.3 Máscara de CPF na digitação.
- [x] 5.4 Lista de telefones: acrescentar, remover, rotular, nomear o contato e marcar o principal.
- [x] 5.5 Remover o campo ID Clínica Ágil.
- [x] 5.6 Botão `Ler Carteirinha` no topo, com câmera no celular e arquivo no computador.
- [x] 5.9 Webcam no computador: `capture` é ignorado fora do celular, então o PC ganhou captura na própria página, com prévia, foto e desligamento da câmera ao fechar.
- [x] 5.7 Campos de validade e nascimento, com aviso de carteirinha vencida no paciente e na solicitação.
- [x] 5.8 Prazo de guarda da imagem em Configurações → Globais.

## 6. Validação

- [x] 6.1 Testes de API: CPF, telefones, datas, leitura da carteirinha e expurgo.
- [x] 6.2 `openspec validate cadastro-paciente-carteirinha --type change --no-interactive`.
- [x] 6.3 `tsc -b`, `oxlint`, `vite build` e `php artisan test` (228 testes, 1095 asserções).
- [ ] 6.4 Rodar a suíte e2e do Playwright — o servidor não tem Node e PHP fora dos containers.
- [ ] 6.5 Conferir no navegador: câmera do celular, máscara de CPF e leitura real de uma carteirinha com a chave OpenAI de produção.

## 7. Achados durante a implementação

- O `telefone` antigo (coluna única) deixou de ser aceito pelo formulário. A coluna e os dados ficam, e o recurso continua devolvendo o valor, mas a fonte agora é a lista `telefones`.
- Os testes de paciente usavam CPFs de dígito verificador inválido (`99988877766`). Foram trocados por CPFs válidos — a falha era a validação nova funcionando.
- `storePacienteRapido`, o cadastro rápido dentro de Solicitações, não mexe em telefone: segue funcionando sem alteração.
