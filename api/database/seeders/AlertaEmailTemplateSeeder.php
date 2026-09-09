<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use App\Models\Tenant;
use App\Services\Alertas\NotificadorDeAlertas;
use Illuminate\Database\Seeder;

/**
 * Modelos padrao das notificacoes de alerta, dentro de `email_templates`.
 *
 * Vao para a tabela que JA EXISTE, com CRUD e tela proprios — esta fase e o
 * primeiro consumidor dela. Nenhum CRUD novo de modelos foi criado.
 */
class AlertaEmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $padroes = [
            [
                'chave' => NotificadorDeAlertas::TEMPLATE_DIGEST,
                'nome' => 'Alertas — resumo diário',
                'assunto' => 'Resumo de alertas — Gestão de Convênios',
                'corpo' => "Bom dia,\n\n"
                    ."Estes são os alertas abertos agora no Gestão de Convênios.\n\n"
                    ."Abra a central de alertas para tratar cada um.\n",
            ],
            [
                'chave' => NotificadorDeAlertas::TEMPLATE_IMEDIATO,
                'nome' => 'Alertas — aviso imediato',
                'assunto' => '[Alerta] Ocorrência crítica no Gestão de Convênios',
                'corpo' => "Um alerta crítico foi aberto agora.\n\n"
                    ."Abra a central de alertas para ver o detalhe e tratar.\n",
            ],
        ];

        Tenant::query()->orderBy('id')->each(function (Tenant $tenant) use ($padroes) {
            foreach ($padroes as $padrao) {
                EmailTemplate::query()->withoutGlobalScopes()->firstOrCreate(
                    ['tenant_id' => $tenant->id, 'chave' => $padrao['chave']],
                    $padrao + ['tenant_id' => $tenant->id, 'ativo' => true],
                );
            }
        });
    }
}
