<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class AiPromptTemplate extends Model
{
    use Auditable, BelongsToTenant;

    /**
     * Chaves que o codigo procura pelo nome. `ler_solicitacao_medica` e lida
     * por App\Services\PedidoMedicoAiService; `ler_sessoes_escaneadas` esta
     * reservada para a leitura de sessoes escaneadas. `mapear_cabecalho_importacao`
     * e lida por App\Services\ImportacaoHeaderMappingAiService — usada como
     * fallback quando o cabecalho de uma planilha de importacao (Pacientes,
     * Solicitacoes, Guias...) nao bate com o modelo esperado.
     *
     * Um prompt com uma dessas chaves pode ser editado a vontade, mas nao pode
     * ser apagado nem ter a chave trocada: nos dois casos a leitura automatica
     * ficaria sem prompt e o erro so apareceria na hora do upload.
     */
    public const CHAVES_SISTEMA = [
        'ler_solicitacao_medica',
        'ler_carteirinha',
        'ler_sessoes_escaneadas',
        'mapear_cabecalho_importacao',
    ];

    public function ehDeSistema(): bool
    {
        return in_array($this->chave, self::CHAVES_SISTEMA, true);
    }

    /**
     * Cria os prompts de sistema que ainda nao existirem no tenant.
     *
     * Roda na leitura (tela de conexao e listagem do CRUD) porque nao ha
     * seeder para eles em producao: o entrypoint so executa `migrate`. Usa
     * firstOrCreate, entao um prompt ja editado pelo operador fica intocado.
     */
    public static function garantirPadroes(int $tenantId): void
    {
        $padroes = [
            [
                'chave' => 'ler_solicitacao_medica',
                'nome' => 'Ler solicitação médica',
                'descricao' => 'Extrai dados de solicitações médicas para criar Solicitações.',
                'model_id' => null,
                'system_prompt' => 'Você extrai dados de documentos médicos para um sistema de convênios. Responda somente em JSON válido.',
                'user_prompt' => 'Leia a solicitação médica escaneada e retorne paciente, médico, convênio, especialidade, data solicitada e observações relevantes.',
                'ativo' => true,
            ],
            [
                'chave' => 'ler_carteirinha',
                'nome' => 'Ler carteirinha',
                'descricao' => 'Extrai os dados do cartão do beneficiário para preencher o cadastro do paciente.',
                'model_id' => null,
                'system_prompt' => 'Você lê carteirinhas de plano de saúde brasileiras e extrai os dados impressos. Responda somente em JSON válido. Nunca invente um valor que não esteja no cartão.',
                'user_prompt' => 'Leia a imagem da carteirinha e retorne o número do beneficiário, o nome completo, a operadora, o CPF, a data de nascimento e a validade do cartão.',
                'ativo' => true,
            ],
            [
                'chave' => 'ler_sessoes_escaneadas',
                'nome' => 'Ler sessões escaneadas',
                'descricao' => 'Extrai sessões escaneadas para criar lançamentos no banco.',
                'model_id' => null,
                'system_prompt' => 'Você extrai registros de sessões terapêuticas de documentos escaneados. Responda somente em JSON válido.',
                'user_prompt' => 'Leia o registro de sessões escaneado e retorne data, hora início, hora fim, acompanhante, profissional e resumo das atividades de cada sessão.',
                'ativo' => true,
            ],
            [
                'chave' => 'mapear_cabecalho_importacao',
                'nome' => 'Mapear cabeçalho de importação',
                'descricao' => 'Usado como reforço quando o cabeçalho de uma planilha de importação (Pacientes, Solicitações, Guias, Sessões, Antecipações, Conciliações) não bate com o modelo baixável — a clínica enviou a própria planilha, com os nomes de coluna dela.',
                'model_id' => null,
                'system_prompt' => 'Você mapeia cabeçalhos de colunas de planilhas de clínicas para os campos que um sistema de gestão de convênios espera. Responda somente em JSON válido.',
                'user_prompt' => 'Você recebe a lista de cabeçalhos de colunas encontrados numa planilha e a lista de campos que o sistema espera, cada um com uma breve descrição do que significa. Para cada cabeçalho da planilha, diga a qual campo esperado ele corresponde — ou null se nenhum campo combinar com aquela coluna.',
                'ativo' => true,
            ],
        ];

        foreach ($padroes as $padrao) {
            static::query()->firstOrCreate(
                ['tenant_id' => $tenantId, 'chave' => $padrao['chave']],
                $padrao,
            );
        }
    }

    protected $fillable = [
        'tenant_id',
        'chave',
        'nome',
        'descricao',
        'model_id',
        'system_prompt',
        'user_prompt',
        'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];
}
