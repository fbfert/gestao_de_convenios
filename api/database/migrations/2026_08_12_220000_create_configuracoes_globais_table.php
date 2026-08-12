<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configurações globais do tenant.
 *
 * Uma linha por tenant, com uma coluna por parâmetro, em vez de chave-valor:
 * cada parâmetro tem tipo e faixa próprios, e um `valor` genérico em texto
 * empurraria toda a validação para o código de leitura, onde um valor
 * inválido só apareceria em produção.
 *
 * O que entra aqui é ajuste de comportamento do sistema. Regra de convênio
 * continua em convenio_regras e tabela_valores, como manda o ADR-03.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracoes_globais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();

            // Quanto tempo um login vale, contado da emissão do token.
            $table->unsignedInteger('sessao_minutos')->default(480);

            // Antecedência do aviso de senha da guia prestes a vencer.
            // Estava fixo em 7 no frontend (SENHA_VENCENDO_EM_DIAS).
            $table->unsignedSmallInteger('senha_alerta_dias')->default(7);

            // Quantidade sugerida ao abrir uma especialidade na solicitação.
            // Estava fixa em '10' no formulário.
            $table->unsignedSmallInteger('sessoes_padrao')->default(10);

            // Tamanho padrão de página nas listagens.
            $table->unsignedSmallInteger('itens_por_pagina')->default(15);

            $table->timestamps();

            $table->unique('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracoes_globais');
    }
};
