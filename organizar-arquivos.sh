#!/usr/bin/env bash
set -euo pipefail

# Rode este script a partir da raiz do projeto (gestao-convenios/), com a
# pasta "files" (contendo todos os arquivos que baixou) como subpasta dela.
#
# Uso:
#   chmod +x organizar-arquivos.sh
#   ./organizar-arquivos.sh

SRC="files"

if [ ! -d "$SRC" ]; then
  echo "Pasta '$SRC' não encontrada. Rode este script na raiz do projeto, com os arquivos dentro de ./$SRC"
  exit 1
fi

# Cria a estrutura de destino
mkdir -p docs
mkdir -p api/database/migrations
mkdir -p api/app/Support
mkdir -p api/app/Scopes
mkdir -p api/app/Concerns
mkdir -p api/app/Http/Middleware
mkdir -p api/app/Models

move() {
  local file="$1"
  local dest="$2"
  if [ -f "$SRC/$file" ]; then
    mv "$SRC/$file" "$dest/$file"
    echo "OK  $file -> $dest/"
  else
    echo "--  $file não encontrado em $SRC/ (pulando)"
  fi
}

# ---------- Raiz do projeto ----------
move "README.md" "."
move "PROMPT-CLAUDE-CODE.md" "."

# ---------- docs/ ----------
move "schema.md" "docs"
move "decisoes-arquitetura.md" "docs"
move "roadmap-mvp.md" "docs"
move "notas-bloco1.md" "docs"
move "proposta-gestao-convenios.html" "docs"

# ---------- api/database/migrations/ ----------
move "2025_01_01_000001_create_tenants_table.php" "api/database/migrations"
move "2025_01_01_000002_add_tenant_and_role_to_users_table.php" "api/database/migrations"
move "2025_01_01_000003_create_especialidades_table.php" "api/database/migrations"
move "2025_01_01_000004_create_profissionais_table.php" "api/database/migrations"
move "2025_01_01_000005_create_convenios_table.php" "api/database/migrations"
move "2025_01_01_000006_create_convenio_regras_table.php" "api/database/migrations"
move "2025_01_01_000007_create_pacientes_table.php" "api/database/migrations"
move "2025_01_01_000008_create_solicitacoes_table.php" "api/database/migrations"
move "2025_01_01_000009_create_guias_table.php" "api/database/migrations"
move "2025_01_01_000010_create_tabela_valores_table.php" "api/database/migrations"
move "2025_01_01_000011_create_antecipacoes_table.php" "api/database/migrations"
move "2025_01_01_000012_create_lancamentos_table.php" "api/database/migrations"
move "2025_01_01_000013_create_conciliacoes_financeiras_table.php" "api/database/migrations"
move "2025_01_01_000014_create_conector_execucoes_table.php" "api/database/migrations"
move "2025_01_01_000015_create_audit_logs_table.php" "api/database/migrations"

# ---------- api/app/Support, Scopes, Concerns, Http/Middleware ----------
move "TenantContext.php" "api/app/Support"
move "TenantScope.php" "api/app/Scopes"
move "BelongsToTenant.php" "api/app/Concerns"
move "ResolveTenant.php" "api/app/Http/Middleware"

# ---------- api/app/Models/ ----------
move "Tenant.php" "api/app/Models"
move "User.php" "api/app/Models"
move "Especialidade.php" "api/app/Models"
move "Profissional.php" "api/app/Models"
move "Convenio.php" "api/app/Models"
move "ConvenioRegra.php" "api/app/Models"
move "Paciente.php" "api/app/Models"
move "Solicitacao.php" "api/app/Models"
move "Guia.php" "api/app/Models"
move "TabelaValor.php" "api/app/Models"
move "Antecipacao.php" "api/app/Models"
move "Lancamento.php" "api/app/Models"
move "ConciliacaoFinanceira.php" "api/app/Models"
move "ConectorExecucao.php" "api/app/Models"
move "AuditLog.php" "api/app/Models"

echo ""
echo "Concluído. Verifique se a pasta '$SRC' ficou vazia (o que sobrou lá são arquivos que o script não reconheceu)."
