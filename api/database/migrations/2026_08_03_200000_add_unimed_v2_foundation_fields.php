<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitacoes', function (Blueprint $table) {
            $table->string('cid')->nullable()->after('medico_id');
        });

        Schema::create('convenio_especialidade_mapeamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('convenio_id')->constrained('convenios')->restrictOnDelete();
            $table->foreignId('especialidade_id')->constrained('especialidades')->restrictOnDelete();
            $table->string('codigo_procedimento');
            $table->string('descricao_operadora')->nullable();
            $table->unsignedInteger('quantidade_padrao')->default(10);
            $table->boolean('usa_descricao_generica')->default(false);
            $table->string('valor_generico')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'convenio_id', 'especialidade_id'], 'conv_esp_map_unique');
            $table->index(['tenant_id', 'ativo']);
        });

        Schema::create('convenio_profissional_mapeamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('convenio_id')->constrained('convenios')->restrictOnDelete();
            $table->foreignId('profissional_id')->constrained('profissionais')->restrictOnDelete();
            $table->string('codigo_operadora');
            $table->string('nome_operadora')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'convenio_id', 'profissional_id'], 'conv_prof_map_unique');
            $table->index(['tenant_id', 'ativo']);
        });

        Schema::table('guias', function (Blueprint $table) {
            $table->unsignedInteger('sessoes_solicitadas')->nullable()->after('unimed_next_check_at');
            $table->unsignedInteger('sessoes_autorizadas')->nullable()->after('sessoes_solicitadas');
            $table->string('protocolo_operadora')->nullable()->after('sessoes_autorizadas');
        });

        $this->alterGuiaStatusAndNumber(nullable: true);
    }

    public function down(): void
    {
        $this->alterGuiaStatusAndNumber(nullable: false, enum: true);

        Schema::table('guias', function (Blueprint $table) {
            $table->dropColumn(['sessoes_solicitadas', 'sessoes_autorizadas', 'protocolo_operadora']);
        });

        Schema::dropIfExists('convenio_profissional_mapeamentos');
        Schema::dropIfExists('convenio_especialidade_mapeamentos');

        Schema::table('solicitacoes', function (Blueprint $table) {
            $table->dropColumn('cid');
        });
    }

    private function alterGuiaStatusAndNumber(bool $nullable, bool $enum = false): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            $nullSql = $nullable ? 'NULL' : 'NOT NULL';
            DB::statement("ALTER TABLE guias MODIFY numero_guia VARCHAR(255) {$nullSql}");

            if ($enum) {
                DB::statement("ALTER TABLE guias MODIFY status ENUM('under_review','finalized','denied') NOT NULL DEFAULT 'under_review'");
            } else {
                DB::statement("ALTER TABLE guias MODIFY status VARCHAR(50) NOT NULL DEFAULT 'under_review'");
            }
        } elseif ($driver === 'sqlite' && $nullable && ! $enum) {
            $this->rebuildSqliteGuias();
        }
    }

    private function rebuildSqliteGuias(): void
    {
        DB::statement('PRAGMA foreign_keys=OFF');
        DB::statement('PRAGMA legacy_alter_table=ON');
        DB::statement('ALTER TABLE guias RENAME TO guias_old');
        DB::statement(<<<'SQL'
CREATE TABLE guias (
    id integer primary key autoincrement not null,
    tenant_id integer not null,
    solicitacao_id integer null,
    convenio_id integer not null,
    paciente_id integer not null,
    profissional_id integer not null,
    especialidade_id integer not null,
    numero_guia varchar(255) null,
    tipo_terapia varchar(255) not null,
    status varchar(50) not null default 'under_review',
    data_solicitacao date not null,
    data_finalizacao date null,
    senha varchar(255) null,
    validade_senha date null,
    observacoes text null,
    created_at datetime null,
    updated_at datetime null,
    solicitacao_item_id integer null,
    automacao_execucao_id integer null,
    unimed_status varchar(255) null,
    unimed_last_checked_at datetime null,
    unimed_next_check_at datetime null,
    sessoes_solicitadas integer null,
    sessoes_autorizadas integer null,
    protocolo_operadora varchar(255) null
)
SQL);
        DB::statement(<<<'SQL'
INSERT INTO guias (
    id, tenant_id, solicitacao_id, convenio_id, paciente_id, profissional_id, especialidade_id,
    numero_guia, tipo_terapia, status, data_solicitacao, data_finalizacao, senha,
    validade_senha, observacoes, created_at, updated_at, solicitacao_item_id,
    automacao_execucao_id, unimed_status, unimed_last_checked_at, unimed_next_check_at,
    sessoes_solicitadas, sessoes_autorizadas, protocolo_operadora
)
SELECT
    id, tenant_id, solicitacao_id, convenio_id, paciente_id, profissional_id, especialidade_id,
    numero_guia, tipo_terapia, status, data_solicitacao, data_finalizacao, senha,
    validade_senha, observacoes, created_at, updated_at, solicitacao_item_id,
    automacao_execucao_id, unimed_status, unimed_last_checked_at, unimed_next_check_at,
    sessoes_solicitadas, sessoes_autorizadas, protocolo_operadora
FROM guias_old
SQL);
        DB::statement('DROP TABLE guias_old');
        DB::statement('CREATE INDEX guias_tenant_id_status_index ON guias (tenant_id, status)');
        DB::statement('CREATE INDEX guias_convenio_id_numero_guia_index ON guias (convenio_id, numero_guia)');
        DB::statement('CREATE INDEX guias_validade_senha_index ON guias (validade_senha)');
        DB::statement('CREATE INDEX guias_tenant_id_unimed_next_check_at_index ON guias (tenant_id, unimed_next_check_at)');
        DB::statement('PRAGMA legacy_alter_table=OFF');
        DB::statement('PRAGMA foreign_keys=ON');
    }
};
