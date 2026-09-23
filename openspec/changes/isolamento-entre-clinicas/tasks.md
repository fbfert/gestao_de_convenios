# Tasks

A varredura da API já está escrita e verde (bloco 1 feito antes desta change existir — registrado aqui porque é ela que mede o resto). Os blocos seguintes fecham o oráculo e a fila.

## 1. Varredura de vazamento pela API

- [x] 1.1 `VazamentoEntreClinicasTest`: descobrir as rotas pelo roteador e resolver o model de cada parâmetro por reflexão, inclusive em controller invocável; verificar que parâmetro sem model aparece numa constante declarada
- [x] 1.2 Atacar toda rota GET de um parâmetro com registro da clínica vizinha, com asserção de controle no registro próprio; verificar que a varredura reprova com o isolamento desarmado
- [x] 1.3 Conferir que nenhuma listagem soma linha da vizinha; verificar que reprova com o `TenantScope` desarmado
- [x] 1.4 Atacar toda rota de escrita de um parâmetro com corpo vazio, afirmando 404 e que a linha da vizinha continua intacta; verificar que reprova com as três camadas desarmadas
- [x] 1.5 Ataque dirigido ao `profissional_id` vindo do corpo; verificar que o teste reprova com o `TenantScope` desarmado (a primeira versão passava verde porque a guia não estava aprovada)

## 2. Fechar o oráculo de existência

- [x] 2.1 Criar um único lugar que produza a regra `exists` recortada pela clínica do usuário autenticado, no formato de `RelatorioFiltrosRequest::existeNaClinica()`; verificar com teste que a regra recusa id de outra clínica
- [x] 2.2 Aplicar em `StoreLancamentoRequest` e `UpdateLancamentoRequest` (`profissional_id`); verificar com teste que id de profissional de outra clínica responde 422 e que o da própria continua aceito
- [x] 2.3 Aplicar em `ImportLancamentosTranscricaoRequest` (`profissional_id`); verificar com teste
- [x] 2.4 Aplicar em `ListConciliacaoRequest` (`convenio_id`, `especialidade_id`, `profissional_id`); verificar com teste que o filtro recusa id de outra clínica em vez de responder lista vazia
- [x] 2.5 Aplicar na validação em linha de `LancamentoController` (`profissional_id`) e `SolicitacaoController::storePacienteRapido` (`convenio_id`); verificar com teste
- [x] 2.6 Teste que percorre os pontos corrigidos e afirma que id de outra clínica e id inexistente produzem a MESMA resposta; verificar que reprova se algum ponto voltar ao `exists:` cru

## 3. Isolamento fora de requisição

- [x] 3.1 `IsolamentoNaFilaTest`: povoar duas clínicas e rodar um job real afirmando que ele tocou só na clínica que processava; verificar que o teste reprova se o recorte explícito do job for removido
- [x] 3.2 Cobrir o caso do `TenantContext` vazio: afirmar que, sem contexto, o `TenantScope` não filtra — para o teste documentar a razão de o recorte explícito ser obrigatório, em vez de deixá-la só em comentário
- [x] 3.3 Conferir os jobs existentes um a um e registrar no resumo quais passam o tenant explicitamente; corrigir o que não passar

## 4. Fechamento

- [x] 4.1 Rodar `./vendor/bin/pint --test` nos arquivos tocados (o projeto tem 96 arquivos com dívida de estilo anterior a esta change, nenhum deles tocado aqui) e `php artisan test` inteiro, verde
- [x] 4.2 Rodar `openspec validate isolamento-entre-clinicas --type change --strict` sem erro
