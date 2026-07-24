<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'chave' => 'guia_aprovada',
                'nome' => 'Guia aprovada',
                'assunto' => 'Guia aprovada',
                'corpo' => "Olá {{paciente_nome}}, sua guia foi aprovada.",
            ],
            [
                'chave' => 'solicitacao_negada',
                'nome' => 'Solicitação negada',
                'assunto' => 'Solicitação negada',
                'corpo' => "Olá {{paciente_nome}}, sua solicitação foi negada.",
            ],
            [
                'chave' => 'paciente.solicitacao_recebida',
                'nome' => 'Paciente - solicitação recebida',
                'assunto' => 'Recebemos sua solicitação',
                'corpo' => "Olá {{paciente_nome}},\n\nRecebemos sua solicitação para {{especialidade_nome}} pelo convênio {{convenio_nome}}.\n\nNossa equipe fará a conferência dos dados e avisará assim que houver atualização.\n\nAtenciosamente,\n{{clinica_nome}}",
            ],
            [
                'chave' => 'paciente.documentos_pendentes',
                'nome' => 'Paciente - documentos pendentes',
                'assunto' => 'Documentos pendentes para sua solicitação',
                'corpo' => "Olá {{paciente_nome}},\n\nPara continuar a análise da sua solicitação, precisamos dos seguintes documentos:\n\n{{documentos_pendentes}}\n\nEnvie os arquivos pelo canal combinado com a clínica.\n\nAtenciosamente,\n{{clinica_nome}}",
            ],
            [
                'chave' => 'paciente.guia_aprovada',
                'nome' => 'Paciente - guia aprovada',
                'assunto' => 'Sua guia foi aprovada',
                'corpo' => "Olá {{paciente_nome}},\n\nSua guia {{numero_guia}} foi aprovada para {{especialidade_nome}}.\n\nSenha: {{senha}}\nValidade: {{validade_senha}}\nQuantidade autorizada: {{qtd_autorizada}}\n\nAtenciosamente,\n{{clinica_nome}}",
            ],
            [
                'chave' => 'paciente.solicitacao_negada',
                'nome' => 'Paciente - solicitação negada',
                'assunto' => 'Atualização sobre sua solicitação',
                'corpo' => "Olá {{paciente_nome}},\n\nA solicitação enviada ao convênio {{convenio_nome}} não foi aprovada neste momento.\n\nMotivo informado: {{motivo_negativa}}\n\nNossa equipe está disponível para orientar os próximos passos.\n\nAtenciosamente,\n{{clinica_nome}}",
            ],
            [
                'chave' => 'paciente.guia_vencimento_proximo',
                'nome' => 'Paciente - guia próxima do vencimento',
                'assunto' => 'Sua guia está próxima do vencimento',
                'corpo' => "Olá {{paciente_nome}},\n\nA guia {{numero_guia}} vence em {{validade_senha}}.\n\nCaso ainda existam sessões pendentes, fale com a recepção para alinharmos a continuidade do atendimento.\n\nAtenciosamente,\n{{clinica_nome}}",
            ],
            [
                'chave' => 'paciente.sessao_confirmada',
                'nome' => 'Paciente - sessão confirmada',
                'assunto' => 'Sessão confirmada',
                'corpo' => "Olá {{paciente_nome}},\n\nSua sessão de {{especialidade_nome}} está confirmada para {{data_sessao}} às {{hora_sessao}} com {{profissional_nome}}.\n\nAtenciosamente,\n{{clinica_nome}}",
            ],
            [
                'chave' => 'paciente.sessao_cancelada',
                'nome' => 'Paciente - sessão cancelada',
                'assunto' => 'Sessão cancelada',
                'corpo' => "Olá {{paciente_nome}},\n\nA sessão marcada para {{data_sessao}} às {{hora_sessao}} foi cancelada.\n\nMotivo: {{motivo_cancelamento}}\n\nNossa equipe entrará em contato para reagendamento.\n\nAtenciosamente,\n{{clinica_nome}}",
            ],
            [
                'chave' => 'paciente.autorizacao_renovacao',
                'nome' => 'Paciente - renovação de autorização',
                'assunto' => 'Renovação de autorização necessária',
                'corpo' => "Olá {{paciente_nome}},\n\nA autorização atual está chegando ao fim. Para solicitar renovação, precisamos conferir a documentação e a indicação médica atualizada.\n\nEntre em contato com a clínica para orientação.\n\nAtenciosamente,\n{{clinica_nome}}",
            ],
            [
                'chave' => 'profissional.nova_guia_autorizada',
                'nome' => 'Profissional - nova guia autorizada',
                'assunto' => 'Nova guia autorizada para atendimento',
                'corpo' => "Olá {{profissional_nome}},\n\nA guia {{numero_guia}} do paciente {{paciente_nome}} foi autorizada para {{especialidade_nome}}.\n\nQuantidade autorizada: {{qtd_autorizada}}\nValidade: {{validade_senha}}\n\nAtenciosamente,\n{{clinica_nome}}",
            ],
            [
                'chave' => 'profissional.lancamentos_pendentes',
                'nome' => 'Profissional - lançamentos pendentes',
                'assunto' => 'Lançamentos de sessões pendentes',
                'corpo' => "Olá {{profissional_nome}},\n\nExistem sessões pendentes de lançamento ou conferência no período {{periodo_referencia}}.\n\nQuantidade pendente: {{qtd_pendente}}\n\nAcesse o sistema para regularizar os registros.\n\nAtenciosamente,\n{{clinica_nome}}",
            ],
            [
                'chave' => 'profissional.guia_vencimento_proximo',
                'nome' => 'Profissional - guia próxima do vencimento',
                'assunto' => 'Guia próxima do vencimento',
                'corpo' => "Olá {{profissional_nome}},\n\nA guia {{numero_guia}} do paciente {{paciente_nome}} vence em {{validade_senha}}.\n\nVerifique se ainda há sessões a lançar antes do encerramento.\n\nAtenciosamente,\n{{clinica_nome}}",
            ],
            [
                'chave' => 'profissional.repasse_disponivel',
                'nome' => 'Profissional - repasse disponível',
                'assunto' => 'Resumo de repasse disponível',
                'corpo' => "Olá {{profissional_nome}},\n\nO resumo de repasse do período {{periodo_referencia}} está disponível.\n\nValor previsto: {{valor_repasse}}\nSessões pagas: {{qtd_sessoes_pagas}}\n\nAtenciosamente,\n{{clinica_nome}}",
            ],
            [
                'chave' => 'operador.nova_solicitacao',
                'nome' => 'Operador - nova solicitação',
                'assunto' => 'Nova solicitação cadastrada',
                'corpo' => "Olá {{operador_nome}},\n\nUma nova solicitação foi cadastrada para {{paciente_nome}}.\n\nConvênio: {{convenio_nome}}\nEspecialidade: {{especialidade_nome}}\nProfissional: {{profissional_nome}}\n\nAcesse o sistema para iniciar a conferência.",
            ],
            [
                'chave' => 'operador.autorizacao_aprovada',
                'nome' => 'Operador - autorização aprovada',
                'assunto' => 'Autorização aprovada no convênio',
                'corpo' => "Olá {{operador_nome}},\n\nA autorização da guia {{numero_guia}} foi aprovada.\n\nPaciente: {{paciente_nome}}\nSenha: {{senha}}\nValidade: {{validade_senha}}\n\nAtualize os próximos passos no sistema.",
            ],
            [
                'chave' => 'operador.autorizacao_negada',
                'nome' => 'Operador - autorização negada',
                'assunto' => 'Autorização negada no convênio',
                'corpo' => "Olá {{operador_nome}},\n\nA autorização para {{paciente_nome}} foi negada pelo convênio {{convenio_nome}}.\n\nMotivo: {{motivo_negativa}}\n\nRegistre a tratativa e oriente o paciente.",
            ],
            [
                'chave' => 'operador.guia_sem_movimento',
                'nome' => 'Operador - guia sem movimento',
                'assunto' => 'Guia sem lançamentos recentes',
                'corpo' => "Olá {{operador_nome}},\n\nA guia {{numero_guia}} do paciente {{paciente_nome}} está sem lançamentos recentes.\n\nÚltimo lançamento: {{data_ultimo_lancamento}}\n\nVerifique se há atendimento pendente ou necessidade de encerramento.",
            ],
            [
                'chave' => 'operador.analitico_importado',
                'nome' => 'Operador - analítico importado',
                'assunto' => 'Analítico importado para conferência',
                'corpo' => "Olá {{operador_nome}},\n\nO analítico {{analitico_nome}} foi importado para o período {{periodo_referencia}}.\n\nTotal pago: {{total_pago}}\nTotal glosado: {{total_glosado}}\nSaldo: {{saldo}}\n\nAcesse a conciliação para conferir os dados.",
            ],
            [
                'chave' => 'operador.glosa_identificada',
                'nome' => 'Operador - glosa identificada',
                'assunto' => 'Glosa identificada na conciliação',
                'corpo' => "Olá {{operador_nome}},\n\nFoi identificada glosa na guia {{numero_guia}} do paciente {{paciente_nome}}.\n\nMotivo: {{motivo_glosa}}\nValor glosado: {{valor_glosado}}\n\nConfira o registro antes do fechamento financeiro.",
            ],
            [
                'chave' => 'operador.fechamento_concluido',
                'nome' => 'Operador - fechamento concluído',
                'assunto' => 'Fechamento financeiro concluído',
                'corpo' => "Olá {{operador_nome}},\n\nO fechamento financeiro do período {{periodo_referencia}} foi concluído.\n\nTotal recebido: {{total_recebido}}\nTotal repassado: {{total_repassado}}\nSaldo: {{saldo}}\n\nAtenciosamente,\n{{clinica_nome}}",
            ],
        ];

        Tenant::query()->each(function (Tenant $tenant) use ($templates): void {
            foreach ($templates as $template) {
                EmailTemplate::query()->updateOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'chave' => $template['chave'],
                    ],
                    [
                        'nome' => $template['nome'],
                        'assunto' => $template['assunto'],
                        'corpo' => $template['corpo'],
                        'ativo' => true,
                    ],
                );
            }
        });
    }
}
