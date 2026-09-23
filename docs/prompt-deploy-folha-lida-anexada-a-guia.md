# Prompt de deploy na VPS — change `folha-lida-anexada-a-guia`

> Para colar numa sessão do Claude Code rodando **na VPS**, dentro de `/opt/gescon`.
> Copie tudo abaixo da linha.

---

Você está na VPS de produção do Gescon, em `/opt/gescon`. Vamos publicar a change
`folha-lida-anexada-a-guia`, duas correções e o manual atualizado: oito commits de código e manual, de `32f22c9` em diante.

**Existe um único tenant de uso real (NeuroKids), com a automação da Unimed rodando em guias reais
todos os dias.** Quebrar isso custa atendimento de paciente. **Faça este deploy fora do horário de
uso da clínica**: é bundle novo, e quem estiver com a tela aberta só recebe a versão nova no próximo
carregamento.

Um passo por vez, com a saída na tela antes do próximo — não encadeie os passos.

## O que este deploy leva

| O quê | Detalhe |
|---|---|
| **Folha lida guardada na guia** | Ao registrar sessões a partir da leitura (arquivo ou webcam), o mesmo arquivo é anexado à guia. Antes era descartado — só a regional 0220, pelo campo avulso, guardava folha |
| **Anexar folhas à mão** | Campo "Anexar folhas de registro" sempre visível no registro de sessões, com várias folhas e "Retirar". Antes só aparecia para a regional 0220, com um arquivo |
| **Foto e webcam viram PDF** | Conversão no servidor, reduzindo a 2400 px no lado maior. Vale também para folha anexada depois, pela guia |
| **Pasta do paciente** | Cada folha mostra "Guia nº …"; folha de guia finalizada não pode mais ser removida pela pasta (antes podia, e o servidor apagava) |
| **Editar paciente** | Aberta pela pasta ("Editar cadastro") ou por endereço, a edição passa a carregar o paciente pelo id. Antes, quem não estava na primeira página da listagem abria com o formulário em branco |
| **Manual e mapa mental** | Atualizados com estas mudanças e as do deploy anterior |
| **Migration** | **Nenhuma** |
| **Dependências novas** | Nenhuma. A conversão usa `gd` e `exif`, extensões PHP já instaladas pelo `Dockerfile` |
| **Worker** | Sem mudança de código. O `redeploy.sh` rebuilda mesmo assim |

### O que muda para quem já usa o sistema

- **Registrar sessões pela leitura passa a guardar a folha.** Um aviso verde acima da tabela mostra
  qual arquivo vai junto. Nada muda no clique.
- **As folhas das sessões registradas antes deste deploy não voltam sozinhas.** O arquivo lido
  ficava no servidor sem vínculo com guia nem paciente; casá-lo agora seria adivinhação. Essas guias
  precisam receber a folha à mão, no detalhe da guia, antes de finalizar na Unimed.
- **Regional 0220**: a folha lida ou uma anexada à mão cumpre a exigência.

## Passo 0 — Onde a VPS está

```bash
git -C /opt/gescon log --oneline -3
git -C /opt/gescon status --short
docker ps --format '{{.Names}}\t{{.Status}}'
```

O primeiro commit tem que ser **exatamente** `32f22c9`. Se o `git status` mostrar alteração local não
commitada, me mostre antes de qualquer pull: o `redeploy.sh` faz `--ff-only` e vai falhar.

**O que o pull vai trazer** — confira antes, e não depois:

```bash
git -C /opt/gescon fetch origin
git -C /opt/gescon log --oneline HEAD..origin/main
```

Tem que listar estes oito, e só eles (mais os commits do próprio prompt, ver abaixo):

```
e52095b feat(lancamentos): anexar folhas de registro a mao ao registrar as sessoes
6285927 fix(lancamentos): folha lida reconhecida tambem pela extensao
0d0b205 fix(pacientes): editar pelo endereco carrega o paciente pelo id
7bccbc5 docs(manual): folha anexada a guia, pasta do paciente, painel e configuracoes das automacoes
dd6c021 fix(pacientes): pasta mostra a guia de cada folha e nao remove folha de guia finalizada
f9c5ae7 fix(lancamentos): a folha lida fica anexada a guia ao registrar as sessoes
1624e67 docs(openspec): change para anexar a folha lida a guia
fd59362 test(api): super admin em acesso valida ids pela clinica acessada; registra o deploy de 23/09
```

Podem aparecer, no meio deles, commits cujo título começa com `docs:` e que só mexem em
`docs/prompt-deploy-folha-lida-anexada-a-guia.md` — é este próprio arquivo. Confira cada um com
`git -C /opt/gescon show --stat <commit>`. **Qualquer outro commit: pare e me diga.** Foi assim que a change
`isolamento-entre-clinicas` subiu sem estar no roteiro no deploy anterior.

Carregue os segredos sem imprimi-los, e confira como ficou o modo simulação no deploy anterior:

```bash
set -a; . /opt/gescon/deploy/.secrets.env; set +a

docker exec gescon-db mariadb -u root -p"$DB_ROOT" gestao_convenios \
  -e "SELECT tenant_id, automacao_finalizar_guia_simulacao_ativo AS simulacao FROM configuracoes_globais;"
```

Este deploy não mexe na simulação. **Me mostre o valor.** Se o tenant 1 estiver com `0` e a
homologação com duas folhas ainda não tiver sido feita, o Passo 4 do deploy anterior ficou pendente —
avise o responsável antes de seguir.

E as extensões de que a conversão depende:

```bash
docker exec gescon-app php -m | grep -E '^(gd|exif)$'
```

Têm que aparecer as duas. **Sem `gd`, anexar foto ou captura da webcam passa a dar erro** — pare e me
diga.

## Passo 1 — Backup

Este deploy não tem migration, mas passa a gravar arquivos novos na pasta dos pacientes. O backup é o
ponto de retorno.

```bash
set -a; . /opt/gescon/deploy/.secrets.env; set +a
STAMP=pre_folha_lida_$(date +%Y%m%d_%H%M%S)
mkdir -p /opt/gescon/deploy/backups
cd /opt/gescon/deploy/backups

docker exec gescon-db mariadb-dump -u root -p"$DB_ROOT" \
  --single-transaction --routines --triggers gestao_convenios \
  | gzip > "${STAMP}_gestao_convenios.sql.gz"

git -C /opt/gescon rev-parse HEAD | tee "${STAMP}_commit_antes.txt"
```

Conferência:

```bash
ls -la /opt/gescon/deploy/backups/${STAMP}_*
gzip -t "${STAMP}_gestao_convenios.sql.gz" && echo "dump integro"
```

E quantas folhas existem hoje, para comparar depois:

```bash
docker exec gescon-db mariadb -u root -p"$DB_ROOT" gestao_convenios \
  -e "SELECT COUNT(*) AS folhas FROM paciente_arquivos WHERE tipo = 'registro_sessoes';"
```

**Me mostre as três saídas antes de seguirmos.**

## Passo 2 — Deploy

```bash
/opt/gescon/deploy/redeploy.sh
```

Me mostre a saída inteira. Além do HTTP 200, olhe:

- **`git pull` partiu de `32f22c9`** e trouxe os commits conferidos no Passo 0.
- **O bundle servido é o da imagem nova.** Se o script disser que o servido difere do da imagem, as
  mudanças de tela **não estão valendo**, mesmo com HTTP 200.

## Passo 3 — A conversão funciona dentro do container

Sem tocar em banco nem em arquivo de paciente — gera uma imagem, converte e confere o resultado:

```bash
docker exec gescon-app php -r '
require "/var/www/html/vendor/autoload.php";
$img = imagecreatetruecolor(3000, 4000);
imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
$tmp = sys_get_temp_dir()."/teste-folha.jpg";
imagejpeg($img, $tmp, 90);
$pdf = App\Support\ImagemParaPdf::converter($tmp);
unlink($tmp);
echo substr($pdf, 0, 8), " | ", strlen($pdf), " bytes | ",
  str_contains($pdf, "/MediaBox [0 0 595 842]") ? "A4 em pe" : "PAGINA ERRADA", " | pico ",
  round(memory_get_peak_usage() / 1048576), " MB\n";'
```

Tem que sair algo como `%PDF-1.4 | 98904 bytes | A4 em pe | pico 116 MB`. O pico inclui a própria
imagem de teste de 12 MP criada no mesmo processo; tem que ficar **abaixo de ~150 MB** (o limite do PHP
em produção é 256 MB). Qualquer erro: pare e me diga.

E o log sem erro novo desde a subida:

```bash
docker exec gescon-app tail -40 storage/logs/laravel.log
```

## Passo 4 — Limpar o cache de aplicação

O manual é lido do disco a cada acesso, mas o cache de aplicação pode segurar conteúdo por até uma
hora:

```bash
docker exec gescon-app php artisan cache:clear
```

> Não rode `config:cache` nem `route:cache` à mão: o `entrypoint.sh` já cuida do primeiro, e o
> segundo quebra o app (há rotas com Closure em `web.php`).

## Passo 5 — Conferência pela interface

Logado como admin da NeuroKids:

1. **Lançamentos → Novo**: na próxima folha que **já seria registrada de qualquer forma**, leia pelo
   arquivo ou pela webcam. Acima da tabela deve aparecer o aviso verde "A folha lida (…) será anexada à
   guia ao registrar as sessões". Logo abaixo, o campo **"Anexar folhas de registro"** tem que estar
   visível, mesmo fora da regional 0220; se houver segunda via, anexe por ali. **Não registre sessões só
   para testar.**
2. Depois de registrar, no **detalhe da guia**, em "Folhas de registro", a folha aparece — em PDF se
   veio de foto ou webcam.
3. **Pacientes → nome do paciente → Arquivos → Ver**: no grupo "Registro de Sessões", a folha mostra
   "Guia nº …". Em folha de guia **finalizada**, aparece "Guia finalizada" e **não** há botão Remover.
4. **Editar paciente**: abra a pasta de um paciente que **não** esteja na primeira página da listagem
   de Pacientes e clique em **Editar cadastro**. Nome, carteirinha e convênio têm que aparecer
   preenchidos. **Não salve** — só confira e cancele.
5. **Manual** (menu): a seção 5 tem "Pasta do paciente"; a seção 10 tem "A folha fica guardada na
   guia"; a seção 18 tem "Configurações das automações".

Se não houver folha para registrar agora, o item 1–2 fica para o primeiro uso real — registre isso.

Conferência no banco depois do primeiro registro real:

```bash
docker exec gescon-db mariadb -u root -p"$DB_ROOT" gestao_convenios \
  -e "SELECT id, paciente_id, nome_original, mime, JSON_EXTRACT(metadata, '\$.numero_guia') AS guia, created_at
        FROM paciente_arquivos WHERE tipo = 'registro_sessoes' ORDER BY id DESC LIMIT 5;"
```

A folha nova tem que ter `mime = application/pdf` e o número da guia preenchido.

## Passo 6 — A automação continua de pé

```bash
docker ps --format '{{.Names}}\t{{.Status}}' | grep gescon-worker

docker exec gescon-db mariadb -u root -p"$DB_ROOT" gestao_convenios \
  -e "SELECT convenio_id, ativo, automation_paused_at FROM convenio_credenciais WHERE tenant_id = 1;"
```

No painel, o card de saúde dos componentes deve continuar verde.

## Rollback

Não há migration, então o rollback é só código:

```bash
git -C /opt/gescon checkout $(cat /opt/gescon/deploy/backups/<STAMP>_commit_antes.txt)
/opt/gescon/deploy/redeploy.sh
```

As folhas guardadas depois do deploy **continuam** na pasta dos pacientes e seguem válidas — são PDFs
comuns, e o código anterior já sabe listá-las e anexá-las na finalização. Só volta a ser possível
removê-las pela pasta mesmo com a guia finalizada.

## Regras para esta sessão

- Um passo por vez, com a saída na tela antes do próximo
- **Pare no Passo 0** se o pull for trazer commit que este roteiro não lista
- **Fora do horário de uso da clínica**: é bundle novo
- Nunca imprima senha, `APP_KEY` ou conteúdo de `.secrets.env`
- Não rode `migrate` nem `migrate:rollback`: esta change não tem migration
- Não rode `route:cache` (há rotas com Closure em `web.php`)
- Não registre sessões nem finalize guia só para testar o deploy
- Se algo divergir do esperado, **pare e me diga** em vez de tentar corrigir por conta própria
