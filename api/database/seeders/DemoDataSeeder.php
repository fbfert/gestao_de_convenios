<?php

namespace Database\Seeders;

use App\Models\AiPromptTemplate;
use App\Models\AnaliticoUnimedLinha;
use App\Models\AnaliticoUnimedLote;
use App\Models\Antecipacao;
use App\Models\AuditLog;
use App\Models\AutomacaoEvento;
use App\Models\AutomacaoExecucao;
use App\Models\Cid;
use App\Models\ConciliacaoFinanceira;
use App\Models\Convenio;
use App\Models\ConvenioRegra;
use App\Models\Especialidade;
use App\Models\Guia;
use App\Models\GuiaStatusHistorico;
use App\Models\Lancamento;
use App\Models\LancamentoPrintTemplate;
use App\Models\Medico;
use App\Models\MovimentoFinanceiro;
use App\Models\Paciente;
use App\Models\PacienteTelefone;
use App\Models\Profissional;
use App\Models\SaudeComponente;
use App\Models\SaudeComponenteEvento;
use App\Models\Solicitacao;
use App\Models\SolicitacaoItem;
use App\Models\TabelaValor;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Auditoria;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Popula o ambiente local com volume suficiente pra exercitar as telas:
 * listas com paginação, filtros com resultado, estados de vazio/erro raros e
 * todos os status de cada domínio representados.
 *
 * Não é seed de produção — roda sob demanda com `db:seed --class=DemoDataSeeder`.
 * O registro automático de auditoria fica suspenso durante a carga em massa
 * (senão a trilha viraria milhares de linhas de "created" iguais); no fim o
 * seeder grava uma trilha curada, com usuários e ações variadas.
 */
class DemoDataSeeder extends Seeder
{
    private const SEMENTE = 20260825;

    /**
     * Quanto histórico o seed produz, em dias.
     *
     * Noventa é o mínimo para a tela de Relatórios ser avaliável: dá série com
     * forma, comparação com um período anterior de mesmo tamanho, e um mês
     * fechado para trás. Com menos que isso os gráficos nascem vazios e não há
     * como julgar o desenho nem escrever e2e com número conhecido.
     */
    private const DIAS_DE_HISTORICO = 90;

    /** Texto livre, como a operadora manda — o relatório agrupa pelo que veio. */
    private const MOTIVOS_DE_GLOSA = [
        'Procedimento não coberto para a data informada',
        'Guia sem autorização prévia',
        'Quantidade acima do autorizado',
        'Senha vencida na data de realização',
        'Profissional não credenciado para o procedimento',
    ];

    /** Códigos que a automação devolve quando falha. */
    private const ERROS_DE_AUTOMACAO = [
        'PORTAL_TIMEOUT',
        'LOGIN_RECUSADO',
        'CARTEIRINHA_INVALIDA',
        'PORTAL_INDISPONIVEL',
        'CAPTCHA_NAO_RESOLVIDO',
    ];

    private Tenant $tenant;

    /** @var array<string, Convenio> */
    private array $convenios = [];

    /** @var array<string, Especialidade> */
    private array $especialidades = [];

    /** @var array<int, Profissional> */
    private array $profissionais = [];

    /** @var array<int, Medico> */
    private array $medicos = [];

    /** @var array<int, Paciente> */
    private array $pacientes = [];

    /** @var array<int, Cid> */
    private array $cids = [];

    public function run(): void
    {
        // Determinístico: rodar duas vezes produz o mesmo desenho de dados,
        // o que torna a caça a página quebrada reproduzível.
        mt_srand(self::SEMENTE);

        $this->tenant = Tenant::query()->where('slug', 'clinica-exemplo')->firstOrFail();

        Auditoria::semRegistroAutomatico(function () {
            $this->criarClinicasAdicionais();
            $this->criarEspecialidades();
            $this->criarProfissionais();
            $this->criarMedicos();
            $this->carregarConvenios();
            $this->criarRegrasEValores();
            $this->criarPacientes();
            $this->carregarCids();
            $this->criarEsteira();
            $this->criarAnalitico();
            $this->criarUsuariosDeDemonstracao();
            $this->criarConteudoConfiguravel();
            // Depois de tudo: reescreve o histórico de status das guias e
            // acrescenta o que só a tela de Relatórios consome.
            $this->criarHistoricoDeStatus();
            $this->criarFilaDeAntecipacoes();
            $this->criarLotesDeImportacao();
            $this->criarHistoricoDeSaude();
        });

        $this->criarTrilhaAuditoria();

        $this->command?->info('Demo carregada: '.$this->resumo());
    }

    /**
     * A tela /clinicas só faz sentido com mais de um tenant. Ficam sem
     * usuário: existem para provar o recorte multi-tenant da listagem.
     */
    private function criarClinicasAdicionais(): void
    {
        foreach ([
            ['nome' => 'Reabilitar Centro Terapêutico', 'slug' => 'reabilitar-centro'],
            ['nome' => 'Clínica Movimento Integrado', 'slug' => 'movimento-integrado'],
            ['nome' => 'NeuroKids Terapias', 'slug' => 'neurokids-terapias'],
        ] as $clinica) {
            Tenant::query()->updateOrCreate(['slug' => $clinica['slug']], [
                'nome' => $clinica['nome'],
                'ativo' => true,
            ]);
        }
    }

    private function criarEspecialidades(): void
    {
        foreach ([
            'Fisioterapia', 'Fonoaudiologia', 'Terapia ABA',
            'Terapia Ocupacional', 'Psicologia', 'Psicopedagogia',
            'Musicoterapia', 'Hidroterapia',
        ] as $nome) {
            Especialidade::query()->updateOrCreate(
                ['tenant_id' => $this->tenant->id, 'nome' => $nome],
                ['ativo' => true],
            );
        }

        $this->especialidades = Especialidade::query()
            ->where('tenant_id', $this->tenant->id)
            ->get()
            ->keyBy('nome')
            ->all();
    }

    private function criarProfissionais(): void
    {
        $novos = [
            ['Dra. Marina Tavares', 'Fisioterapia', 'CREFITO 123456-F', 55],
            ['Dra. Paula Menezes', 'Fonoaudiologia', 'CRFa 78910', 60],
            ['Dr. Rafael Nascimento', 'Terapia ABA', 'CRP 11/22334', 50],
            ['Dra. Juliana Prado', 'Terapia Ocupacional', 'CREFITO 445566-TO', 58],
            ['Dr. Marcelo Bittencourt', 'Psicologia', 'CRP 12/55667', 52],
            ['Dra. Helena Vasconcelos', 'Psicopedagogia', 'CRP 12/99887', 48],
            ['Dr. Thiago Moreira', 'Musicoterapia', 'MT 3344', 45],
            ['Dra. Renata Camargo', 'Hidroterapia', 'CREFITO 778899-F', 57],
            ['Dr. André Salgado', 'Fisioterapia', 'CREFITO 334455-F', 54],
            ['Dra. Bianca Lorenzi', 'Fonoaudiologia', 'CRFa 44556', 61],
            ['Dra. Cristina Yamada', 'Terapia ABA', 'CRP 12/33445', 49],
            ['Dr. Eduardo Pilotto', 'Psicologia', 'CRP 12/77889', 53],
        ];

        foreach ($novos as [$nome, $especialidade, $registro, $repasse]) {
            $profissional = Profissional::query()->updateOrCreate(
                ['tenant_id' => $this->tenant->id, 'nome' => $nome],
                [
                    'especialidade_id' => $this->especialidades[$especialidade]->id,
                    'conselho_registro' => $registro,
                    'percentual_repasse' => $repasse,
                    'ativo' => true,
                ],
            );

            // Um profissional pode atender além da especialidade principal —
            // a tela de vínculo depende da N-pra-N estar populada.
            $profissional->especialidades()->syncWithoutDetaching([
                $this->especialidades[$especialidade]->id,
            ]);
        }

        // Dois inativos: a lista precisa de linha em estado desligado pro
        // filtro "ativo" ter o que esconder.
        Profissional::query()->updateOrCreate(
            ['tenant_id' => $this->tenant->id, 'nome' => 'Dr. Otávio Reis (afastado)'],
            [
                'especialidade_id' => $this->especialidades['Fisioterapia']->id,
                'conselho_registro' => 'CREFITO 999000-F',
                'percentual_repasse' => 50,
                'ativo' => false,
            ],
        );

        $this->profissionais = Profissional::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('ativo', true)
            ->get()
            ->all();
    }

    private function criarMedicos(): void
    {
        $medicos = [
            ['Carlos Almeida', '12345', 'Neurologia Infantil'],
            ['Sofia Bertoldi', '23456', 'Pediatria'],
            ['Henrique Duarte', '34567', 'Ortopedia'],
            ['Larissa Fontes', '45678', 'Psiquiatria Infantil'],
            ['Gustavo Peixoto', '56789', 'Neuropediatria'],
            ['Mariana Cordeiro', '67890', 'Fisiatria'],
            ['Leonardo Bastos', '78901', 'Clínica Geral'],
            ['Patrícia Vieira', '89012', 'Otorrinolaringologia'],
        ];

        foreach ($medicos as $i => [$nome, $crm, $especialidade]) {
            Medico::query()->updateOrCreate(
                ['tenant_id' => $this->tenant->id, 'nome' => $nome],
                [
                    'crm' => $crm,
                    'crm_uf' => 'SC',
                    'especialidade_medica' => $especialidade,
                    'telefone' => sprintf('(47) 3%03d-%04d', 200 + $i, 1000 + $i * 7),
                    'email' => 'contato'.($i + 1).'@consultorio.test',
                    'ativo' => true,
                ],
            );
        }

        $this->medicos = Medico::query()->where('tenant_id', $this->tenant->id)->get()->all();
    }

    private function carregarConvenios(): void
    {
        foreach ([
            ['Unimed', 'Operadora com automação de guia (RDA).', 'scraping', 'unimed_rda'],
            ['SC Saúde', 'Autarquia estadual — envio manual por portal.', 'manual', null],
            ['Celos', 'Autogestão — conferência mensal por analítico.', 'manual', null],
            ['Cassi', 'Autogestão do Banco do Brasil.', 'manual', null],
            ['Amil', 'Operadora nacional — guia por e-mail.', 'manual', null],
        ] as [$nome, $descricao, $conector, $driver]) {
            Convenio::query()->updateOrCreate(
                ['tenant_id' => $this->tenant->id, 'nome' => $nome],
                [
                    'descricao' => $descricao,
                    'connector_type' => $conector,
                    'connector_driver' => $driver,
                    'carteirinha_blocos' => $nome === 'Unimed' ? '4,4,4,4' : null,
                    'ativo' => true,
                ],
            );
        }

        $this->convenios = Convenio::query()
            ->where('tenant_id', $this->tenant->id)
            ->get()
            ->keyBy('nome')
            ->all();
    }

    private function criarRegrasEValores(): void
    {
        foreach ($this->convenios as $convenio) {
            foreach (['especializada', 'convencional'] as $tipoTerapia) {
                ConvenioRegra::query()->updateOrCreate(
                    [
                        'tenant_id' => $this->tenant->id,
                        'convenio_id' => $convenio->id,
                        'tipo_terapia' => $tipoTerapia,
                    ],
                    [
                        'frequencia_lancamento' => $tipoTerapia === 'especializada' ? 'semanal' : 'mensal',
                        'qtd_autorizada_por_ciclo' => $tipoTerapia === 'especializada' ? 8 : 20,
                        'validade_senha_dias' => 90,
                        'observacoes' => 'Regra de demonstração carregada pelo DemoDataSeeder.',
                        'vigente_desde' => Carbon::now()->subYear()->startOfYear()->toDateString(),
                        'vigente_ate' => null,
                    ],
                );
            }

            foreach ($this->especialidades as $especialidade) {
                TabelaValor::query()->updateOrCreate(
                    [
                        'tenant_id' => $this->tenant->id,
                        'convenio_id' => $convenio->id,
                        'especialidade_id' => $especialidade->id,
                        'profissional_id' => null,
                    ],
                    [
                        'valor' => mt_rand(6500, 18500) / 100,
                        'vigente_desde' => Carbon::now()->subMonths(18)->startOfMonth()->toDateString(),
                        'vigente_ate' => null,
                    ],
                );
            }
        }
    }

    private function criarPacientes(): void
    {
        $nomes = [
            'Ana Paula Ribeiro', 'Bruno Henrique Lima', 'Camila Santos Pereira',
            'Diego Alves Martins', 'Elisa Fernandes Costa', 'Felipe Gomes Nogueira',
            'Gabriela Moreira Dias', 'Heitor Barbosa Rocha', 'Isabela Cardoso Pinto',
            'João Vitor Andrade', 'Karina Mendes Freitas', 'Lucas Oliveira Ramos',
            'Manuela Teixeira Braga', 'Nicolas Ferreira Lopes', 'Olívia Carvalho Pires',
            'Pedro Henrique Sales', 'Queila Monteiro Assis', 'Rafaela Duarte Campos',
            'Samuel Antunes Correia', 'Tainá Machado Siqueira', 'Ubirajara Neves Pinto',
            'Valentina Rezende Fogaça', 'William Souza Delgado', 'Xênia Prado Amorim',
            'Yasmin Bruno Tavares', 'Zeca Pagodinho Almeida', 'Alice Rodrigues Neri',
            'Benício Farias Coutinho', 'Cecília Aragão Bonfim', 'Davi Luiz Marques',
            'Eloá Cristina Serpa', 'Fernanda Wagner Klein', 'Gustavo Meireles Rangel',
            'Helena Poletto Sartori', 'Ícaro Bezerra Fontenele', 'Júlia Beatriz Nunes',
            'Kauã Ribeiro Sampaio', 'Lorena Fagundes Uchoa', 'Miguel Arcanjo Prates',
            'Nathália Quadros Bicalho', 'Otávio Simões Bandeira', 'Priscila Tomé Vargas',
            'Raul Estevão Guimarães', 'Sofia Malta Cavalcanti', 'Théo Brandão Villela',
            'Vitória Régia Sampaio',
        ];

        $convenios = array_values($this->convenios);
        $hoje = Carbon::today();

        foreach ($nomes as $i => $nome) {
            $convenio = $convenios[$i % count($convenios)];

            // Validades espalhadas de propósito: vencida, vencendo e longe.
            // A tela de paciente destaca carteirinha fora de validade.
            $validade = match ($i % 5) {
                0 => $hoje->copy()->subDays(mt_rand(5, 120)),
                1 => $hoje->copy()->addDays(mt_rand(1, 25)),
                default => $hoje->copy()->addMonths(mt_rand(4, 30)),
            };

            $paciente = Paciente::query()->updateOrCreate(
                ['tenant_id' => $this->tenant->id, 'cpf' => sprintf('%011d', 12345678900 + $i + 1)],
                [
                    'nome' => $nome,
                    'data_nascimento' => $hoje->copy()->subYears(mt_rand(3, 62))->subDays(mt_rand(0, 364))->toDateString(),
                    'carteirinha' => strtoupper(substr($convenio->nome, 0, 3)).'-2026-'.sprintf('%04d', $i + 1),
                    'validade_carteirinha' => $validade->toDateString(),
                    'convenio_id' => $convenio->id,
                    'telefone' => sprintf('(47) 9%04d-%04d', mt_rand(1000, 9999), mt_rand(1000, 9999)),
                    'clinica_agil_id' => 'CA-'.sprintf('%04d', $i + 1),
                    'ativo' => $i % 11 !== 0,
                ],
            );

            $this->criarTelefones($paciente, $i);
        }

        $this->pacientes = Paciente::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('ativo', true)
            ->get()
            ->all();
    }

    private function criarTelefones(Paciente $paciente, int $indice): void
    {
        $rotulos = [['Celular', 'Mãe'], ['Recado', 'Pai'], ['Trabalho', 'Responsável']];
        $quantos = 1 + ($indice % 3);

        for ($ordem = 0; $ordem < $quantos; $ordem++) {
            [$rotulo, $contato] = $rotulos[$ordem];

            PacienteTelefone::query()->updateOrCreate(
                [
                    'tenant_id' => $this->tenant->id,
                    'paciente_id' => $paciente->id,
                    'ordem' => $ordem,
                ],
                [
                    'numero' => sprintf('(47) 9%04d-%04d', mt_rand(1000, 9999), mt_rand(1000, 9999)),
                    'rotulo' => $rotulo,
                    'contato_nome' => $contato,
                    'principal' => $ordem === 0,
                ],
            );
        }
    }

    private function carregarCids(): void
    {
        $extras = [
            ['F84.0', 'Autismo infantil'],
            ['F80.1', 'Transtorno expressivo de linguagem'],
            ['F82', 'Transtorno específico do desenvolvimento motor'],
            ['G80.9', 'Paralisia cerebral não especificada'],
            ['M54.5', 'Dor lombar baixa'],
            ['S83.5', 'Entorse de ligamento cruzado do joelho'],
            ['F90.0', 'Distúrbio da atividade e da atenção'],
            ['H90.3', 'Perda de audição neurossensorial bilateral'],
            ['R62.0', 'Retardo do desenvolvimento fisiológico'],
            ['Q90.9', 'Síndrome de Down não especificada'],
        ];

        foreach ($extras as [$codigo, $descricao]) {
            Cid::query()->updateOrCreate(
                ['tenant_id' => $this->tenant->id, 'codigo' => $codigo],
                ['descricao' => $descricao, 'ativo' => true],
            );
        }

        $this->cids = Cid::query()->where('tenant_id', $this->tenant->id)->get()->all();
    }

    /**
     * Constrói a esteira inteira, solicitação por solicitação, com os status
     * distribuídos de propósito — cada tela da operação precisa de linha em
     * todo estado que ela sabe desenhar.
     */
    private function criarEsteira(): void
    {
        $distribuicao = array_merge(
            array_fill(0, 14, 'under_review'),
            array_fill(0, 12, 'ready_for_automation'),
            array_fill(0, 16, 'guia_gerada'),
            array_fill(0, 22, 'approved'),
            array_fill(0, 8, 'denied'),
        );

        $hoje = Carbon::today();

        foreach ($distribuicao as $i => $status) {
            $paciente = $this->pacientes[$i % count($this->pacientes)];
            $especialidade = $this->especialidades[array_rand($this->especialidades)];
            $profissional = $this->profissionalDaEspecialidade($especialidade->id);
            $medico = $this->medicos[$i % count($this->medicos)];
            $solicitadoEm = $hoje->copy()->subDays(mt_rand(1, 180));

            $solicitacao = Solicitacao::query()->create([
                'tenant_id' => $this->tenant->id,
                'paciente_id' => $paciente->id,
                'profissional_id' => $profissional->id,
                'especialidade_id' => $especialidade->id,
                'convenio_id' => $paciente->convenio_id,
                'medico_id' => $medico->id,
                'status' => $status,
                'solicitado_em' => $solicitadoEm->toDateString(),
                'observacoes' => $i % 4 === 0 ? 'Paciente em continuidade de tratamento.' : null,
            ]);

            $solicitacao->cidCadastros()->sync(
                collect($this->cids)->random(mt_rand(1, 2))->pluck('id')->all(),
            );

            $quantosItens = 1 + ($i % 3);

            for ($n = 0; $n < $quantosItens; $n++) {
                $especialidadeItem = $n === 0
                    ? $especialidade
                    : $this->especialidades[array_rand($this->especialidades)];
                $profissionalItem = $this->profissionalDaEspecialidade($especialidadeItem->id);

                $item = SolicitacaoItem::query()->create([
                    'tenant_id' => $this->tenant->id,
                    'solicitacao_id' => $solicitacao->id,
                    'especialidade_id' => $especialidadeItem->id,
                    'profissional_id' => $profissionalItem->id,
                    'quantidade' => [8, 10, 12, 16, 20][mt_rand(0, 4)],
                    'status_operacional' => in_array($status, ['guia_gerada', 'approved'], true)
                        ? 'guia_generated'
                        : 'pending',
                    'observacoes' => null,
                ]);

                if (in_array($status, ['under_review', 'ready_for_automation'], true)) {
                    continue;
                }

                $this->criarGuia($solicitacao, $item, $status, $solicitadoEm);
            }
        }
    }

    private function criarGuia(
        Solicitacao $solicitacao,
        SolicitacaoItem $item,
        string $statusSolicitacao,
        Carbon $solicitadoEm,
    ): void {
        $statusGuia = match ($statusSolicitacao) {
            'approved' => ['approved', 'approved', 'finalized'][mt_rand(0, 2)],
            'denied' => 'denied',
            default => ['under_review', 'needs_verification', 'canceled'][mt_rand(0, 2)],
        };

        $aprovada = in_array($statusGuia, ['approved', 'finalized'], true);
        $dataSolicitacao = $solicitadoEm->copy()->addDays(mt_rand(0, 3));

        $guia = Guia::query()->create([
            'tenant_id' => $this->tenant->id,
            'solicitacao_id' => $solicitacao->id,
            'solicitacao_item_id' => $item->id,
            'convenio_id' => $solicitacao->convenio_id,
            'paciente_id' => $solicitacao->paciente_id,
            'profissional_id' => $item->profissional_id,
            'especialidade_id' => $item->especialidade_id,
            'numero_guia' => 'G'.str_pad((string) mt_rand(1, 999999), 8, '0', STR_PAD_LEFT),
            'tipo_terapia' => mt_rand(0, 1) === 0 ? 'especializada' : 'convencional',
            'status' => $statusGuia,
            'unimed_status' => $aprovada ? 'AUTORIZADA' : ($statusGuia === 'denied' ? 'NEGADA' : 'EM ANALISE'),
            'unimed_last_checked_at' => Carbon::now()->subHours(mt_rand(1, 72)),
            'unimed_next_check_at' => Carbon::now()->addHours(mt_rand(2, 24)),
            'sessoes_solicitadas' => $item->quantidade,
            'sessoes_autorizadas' => $aprovada ? max(1, $item->quantidade - mt_rand(0, 4)) : null,
            'protocolo_operadora' => 'PRT-'.mt_rand(100000, 999999),
            'data_solicitacao' => $dataSolicitacao->toDateString(),
            'data_finalizacao' => $statusGuia === 'finalized' ? $dataSolicitacao->copy()->addDays(mt_rand(30, 90))->toDateString() : null,
            'senha' => $aprovada ? (string) mt_rand(10000000, 99999999) : null,
            'validade_senha' => $aprovada ? $dataSolicitacao->copy()->addDays(90)->toDateString() : null,
            'observacoes' => $statusGuia === 'needs_verification' ? 'Retorno da operadora não pôde ser lido automaticamente.' : null,
        ]);

        $this->criarAutomacao($item, $guia, $statusGuia);

        if ($aprovada) {
            $this->criarLancamentos($guia, $dataSolicitacao);
        }
    }

    private function criarAutomacao(SolicitacaoItem $item, Guia $guia, string $statusGuia): void
    {
        $statusExecucao = match ($statusGuia) {
            'approved', 'finalized' => 'succeeded',
            'needs_verification' => 'needs_attention',
            'canceled' => 'failed',
            default => 'running',
        };

        $enfileiradoEm = Carbon::now()
            ->subDays(mt_rand(1, self::DIAS_DE_HISTORICO))
            ->setTime(mt_rand(6, 22), mt_rand(0, 59));

        // Espera em fila e duração separadas, e com dispersão larga: são elas
        // que dão sentido ao p95 e ao histograma da aba de Automações. Um valor
        // estreito faria média e percentil coincidirem, e os dois indicadores
        // pareceriam redundantes.
        $esperaSegundos = mt_rand(0, 100) < 80 ? mt_rand(2, 90) : mt_rand(120, 1800);
        $duracaoSegundos = mt_rand(0, 100) < 85 ? mt_rand(20, 240) : mt_rand(300, 1500);

        $iniciadoEm = $enfileiradoEm->copy()->addSeconds($esperaSegundos);

        $execucao = AutomacaoExecucao::query()->create([
            'tenant_id' => $this->tenant->id,
            'solicitacao_item_id' => $item->id,
            'guia_id' => $guia->id,
            'operacao' => 'gerar_guia',
            'status' => $statusExecucao,
            'idempotency_key' => 'demo-'.$guia->id.'-gerar',
            'payload' => ['numero_carteirinha' => 'demo', 'sessoes' => $item->quantidade],
            'resultado' => $statusExecucao === 'succeeded'
                ? ['numero_guia' => $guia->numero_guia, 'status_guia' => $guia->status]
                : null,
            'erro_codigo' => $statusExecucao === 'failed'
                ? self::ERROS_DE_AUTOMACAO[mt_rand(0, count(self::ERROS_DE_AUTOMACAO) - 1)]
                : null,
            'erro_mensagem' => $statusExecucao === 'failed' ? 'O portal da operadora não respondeu em 90s.' : null,
            'queued_at' => $enfileiradoEm,
            'started_at' => $iniciadoEm,
            // Execução em curso não tem fim, e é assim que o relatório a deixa
            // de fora dos dois períodos até ela terminar.
            'finished_at' => $statusExecucao === 'running' ? null : $iniciadoEm->copy()->addSeconds($duracaoSegundos),
        ]);

        foreach ([
            ['tipo' => 'login', 'status' => 'ok'],
            ['tipo' => 'preenchimento_formulario', 'status' => 'ok'],
            ['tipo' => 'envio', 'status' => $statusExecucao === 'failed' ? 'erro' : 'ok'],
        ] as $passo => $evento) {
            AutomacaoEvento::query()->create([
                'tenant_id' => $this->tenant->id,
                'automacao_execucao_id' => $execucao->id,
                'tipo' => $evento['tipo'],
                'status' => $evento['status'],
                'payload' => ['passo' => $passo + 1],
                'evidencias' => null,
                'registrado_em' => $enfileiradoEm->copy()->addMinutes($passo + 1),
            ]);
        }

        $guia->update(['automacao_execucao_id' => $execucao->id]);
    }

    /**
     * As sessões de uma guia.
     *
     * Já foi "antecipação e lançamentos": o seeder abria um ciclo de cota em
     * `antecipacoes` e pendurava as sessões nele. Esse modelo foi derrubado na
     * change `antecipacao-alerta-e-fila-de-elegiveis` — `antecipacoes` virou a
     * fila de elegíveis, sem `guia_id` nem `ciclo_*`, e a sessão passou a
     * apontar direto para a guia. O seeder continuou escrevendo nas colunas
     * antigas e parou de rodar; isto o realinha com o schema atual.
     */
    private function criarLancamentos(Guia $guia, Carbon $dataSolicitacao): void
    {
        $cicloInicio = $dataSolicitacao->copy()->addDays(mt_rand(1, 7))->startOfWeek();
        $autorizada = $guia->sessoes_autorizadas ?? 8;
        $cicloCompleto = mt_rand(0, 100) < 45;

        $quantosLancamentos = $cicloCompleto ? $autorizada : mt_rand(1, max(1, $autorizada - 2));
        $utilizadas = 0;

        for ($n = 0; $n < $quantosLancamentos; $n++) {
            $status = match (true) {
                mt_rand(0, 100) < 8 => 'missed',
                mt_rand(0, 100) < 5 => 'canceled',
                default => 'completed',
            };

            $dataSessao = $cicloInicio->copy()->addDays($n * 3 + mt_rand(0, 1));
            $horaInicio = sprintf('%02d:%02d:00', mt_rand(8, 17), [0, 30][mt_rand(0, 1)]);

            Lancamento::query()->create([
                'tenant_id' => $this->tenant->id,
                'guia_id' => $guia->id,
                'profissional_id' => $guia->profissional_id,
                'data_sessao' => $dataSessao->toDateString(),
                'hora_inicio' => $horaInicio,
                'hora_fim' => sprintf('%02d:%02d:00', (int) substr($horaInicio, 0, 2) + 1, (int) substr($horaInicio, 3, 2)),
                'acompanhante' => ['Mãe', 'Pai', 'Avó', 'Responsável legal', null][mt_rand(0, 4)],
                'resumo_atividades' => $status === 'completed'
                    ? 'Sessão conduzida conforme plano terapêutico; boa adesão às atividades propostas.'
                    : null,
                'status' => $status,
                'observacoes' => $status === 'missed' ? 'Falta não justificada.' : null,
            ]);

            if ($status === 'completed') {
                $utilizadas++;
            }
        }

        if ($utilizadas > 0) {
            $this->criarConciliacao($guia, $utilizadas);
        }
    }

    private function criarConciliacao(Guia $guia, int $quantidade): void
    {
        $valorUnitario = TabelaValor::query()
            ->where('convenio_id', $guia->convenio_id)
            ->where('especialidade_id', $guia->especialidade_id)
            ->value('valor') ?? 95.00;

        $status = ['pending', 'pending', 'reviewed', 'paid'][mt_rand(0, 3)];

        $conciliacao = ConciliacaoFinanceira::query()->create([
            'tenant_id' => $this->tenant->id,
            'guia_id' => $guia->id,
            'profissional_id' => $guia->profissional_id,
            'quantidade' => $quantidade,
            'valor_unitario' => $valorUnitario,
            'valor_total' => round($valorUnitario * $quantidade, 2),
            'referencia_analitico_convenio' => 'ANL-'.Carbon::now()->format('Ym').'-'.mt_rand(1000, 9999),
            'status' => $status,
            'conferido_em' => $status === 'pending' ? null : Carbon::now()->subDays(mt_rand(1, 30)),
        ]);

        MovimentoFinanceiro::query()->create([
            'tenant_id' => $this->tenant->id,
            'conciliacao_financeira_id' => $conciliacao->id,
            'guia_id' => $guia->id,
            'profissional_informado_id' => $guia->profissional_id,
            'profissional_executor_id' => $guia->profissional_id,
            'tipo' => 'entrada',
            'origem' => 'analitico',
            'quantidade' => $quantidade,
            'valor_unitario' => $valorUnitario,
            'valor_total' => round($valorUnitario * $quantidade, 2),
            'referencia_analitico_convenio' => $conciliacao->referencia_analitico_convenio,
            'descricao' => 'Crédito do analítico da operadora.',
        ]);

        $repasse = Profissional::query()->find($guia->profissional_id)?->percentual_repasse ?? 50;
        $valorRepasse = round($valorUnitario * $quantidade * ($repasse / 100), 2);

        MovimentoFinanceiro::query()->create([
            'tenant_id' => $this->tenant->id,
            'conciliacao_financeira_id' => $conciliacao->id,
            'guia_id' => $guia->id,
            'profissional_informado_id' => $guia->profissional_id,
            'profissional_executor_id' => $guia->profissional_id,
            'tipo' => 'saida',
            'origem' => 'repasse',
            'quantidade' => $quantidade,
            'valor_unitario' => round($valorUnitario * ($repasse / 100), 2),
            'valor_total' => $valorRepasse,
            'referencia_analitico_convenio' => null,
            'descricao' => 'Repasse ao profissional ('.$repasse.'%).',
        ]);
    }

    /**
     * Dois lotes de analítico: um conferido e um recém-importado, com linhas
     * de pagamento e de glosa — a tela separa as duas naturezas.
     */
    private function criarAnalitico(): void
    {
        $guias = Guia::query()
            ->where('tenant_id', $this->tenant->id)
            ->whereIn('status', ['approved', 'finalized'])
            ->limit(40)
            ->get();

        if ($guias->isEmpty()) {
            return;
        }

        // Três competências, uma por mês: com um lote só, a série "executado ×
        // pago" da aba Financeira teria um ponto e não mostraria tendência
        // nenhuma.
        foreach ([
            ['ANALITICO_UNIMED_2026_06.xlsx', 'conferido', 2],
            ['ANALITICO_UNIMED_2026_07.xlsx', 'conferido', 1],
            ['ANALITICO_UNIMED_2026_08.xlsx', 'importado', 0],
        ] as [$arquivo, $status, $mesesAtras]) {
            $lote = AnaliticoUnimedLote::query()->create([
                'tenant_id' => $this->tenant->id,
                'arquivo_nome_original' => $arquivo,
                'arquivo_path' => 'analiticos/'.$arquivo,
                'status' => $status,
                'importado_em' => Carbon::now()->subMonths($mesesAtras)->subDays(mt_rand(0, 12)),
                'cabecalho_json' => ['competencia' => Carbon::now()->subMonths($mesesAtras)->format('m/Y')],
                'planilhas_json' => ['Analítico', 'Glosas'],
                'totais_json' => null,
            ]);

            $totalPago = 0.0;
            $totalGlosado = 0.0;
            $linhasAnalitico = 0;
            $linhasGlosa = 0;

            foreach ($guias as $n => $guia) {
                $glosa = mt_rand(0, 100) < 18;
                $valor = round(mt_rand(6500, 18500) / 100, 2);
                $qtd = mt_rand(1, 8);
                $valorLinha = round($valor * $qtd, 2);

                AnaliticoUnimedLinha::query()->create([
                    'tenant_id' => $this->tenant->id,
                    'analitico_unimed_lote_id' => $lote->id,
                    'linha' => $n + 2,
                    // Os mesmos valores que o AnaliticoUnimedImportService grava
                    // ao ler a planilha de verdade. O seeder escrevia "Glosas"
                    // e "pagamento", que não casam com nada — e o pareto de
                    // motivos da aba Financeira, que filtra por `origem`,
                    // nascia vazio sobre o seed.
                    'origem' => $glosa ? 'glosa' : 'analitico',
                    'natureza' => $glosa ? 'glosado' : 'pago',
                    'processavel' => true,
                    'numero_guia_operadora' => $guia->numero_guia,
                    'numero_guia_prestador' => 'P'.$guia->id,
                    'codigo' => '5010'.mt_rand(100, 999),
                    'usuario' => $guia->paciente?->nome,
                    'data_autorizacao' => $guia->data_solicitacao?->format('d/m/Y'),
                    'data_realizacao' => Carbon::now()->subMonths($mesesAtras)->format('d/m/Y'),
                    'procedimento' => '5010'.mt_rand(100, 999),
                    'descricao_procedimento' => $guia->especialidade?->nome.' — sessão',
                    'qtd' => (string) $qtd,
                    'qtd_normalizada' => $qtd,
                    'tipo' => $glosa ? 'GLOSA' : 'PAGO',
                    // Motivos variados: com um só, o pareto de glosa vira uma
                    // barra e não ensina nada sobre onde a clínica perde.
                    'motivo' => $glosa ? self::MOTIVOS_DE_GLOSA[mt_rand(0, count(self::MOTIVOS_DE_GLOSA) - 1)] : null,
                    'valor' => number_format($valorLinha, 2, ',', '.'),
                    'valor_normalizado' => $valorLinha,
                    'local_realizacao' => 'Clínica Exemplo — Unidade Centro',
                    'chave_conciliacao' => $guia->numero_guia.'|'.$n,
                    'dados_json' => null,
                ]);

                if ($glosa) {
                    $totalGlosado += $valorLinha;
                    $linhasGlosa++;
                } else {
                    $totalPago += $valorLinha;
                    $linhasAnalitico++;
                }
            }

            $lote->update([
                'total_linhas_analitico' => $linhasAnalitico,
                'total_linhas_glosa' => $linhasGlosa,
                'total_linhas_conciliacao' => $linhasAnalitico,
                'total_pago' => $totalPago,
                'total_glosado' => $totalGlosado,
                'saldo_total' => $totalPago - $totalGlosado,
                'totais_json' => ['pago' => $totalPago, 'glosado' => $totalGlosado],
            ]);
        }
    }

    /**
     * A tela /clinicas exige `users.super_admin`, e nenhum usuário do seeder
     * padrão tem a marca — sem isto a página só sabe mostrar o 403.
     */
    private function criarUsuariosDeDemonstracao(): void
    {
        // Spatie em modo teams: sem fixar o tenant, `syncRoles` procura um
        // papel global (tenant_id NULL) que nao existe.
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);

        $superAdmin = User::query()->updateOrCreate(
            ['email' => 'superadmin@clinica-exemplo.test'],
            [
                'tenant_id' => $this->tenant->id,
                'name' => 'Super Admin (demo)',
                'password' => 'password',
                'ativo' => true,
                'super_admin' => true,
            ],
        );

        $superAdmin->syncRoles(['admin']);

        // Um usuário inativo para a listagem ter linha em cada estado.
        User::query()->updateOrCreate(
            ['email' => 'desligado@clinica-exemplo.test'],
            [
                'tenant_id' => $this->tenant->id,
                'name' => 'Recepção (desligada)',
                'password' => 'password',
                'ativo' => false,
            ],
        )->syncRoles(['funcionario']);
    }

    /**
     * Conteúdo que a clínica edita pela tela: prompts da IA e modelos de
     * impressão. Sem eles as telas nascem vazias e não dá pra ver o desenho
     * de lista, só o estado de vazio.
     */
    private function criarConteudoConfiguravel(): void
    {
        foreach ([
            [
                'pedido_medico',
                'Leitura de pedido médico',
                'Extrai paciente, CID, especialidade e quantidade de sessões do pedido.',
            ],
            [
                'resumo_sessao',
                'Resumo de sessão',
                'Transforma a transcrição bruta do atendimento em resumo clínico objetivo.',
            ],
            [
                'analise_glosa',
                'Análise de glosa',
                'Lê o motivo da glosa no analítico e sugere a ação de recurso.',
            ],
        ] as [$chave, $nome, $descricao]) {
            AiPromptTemplate::query()->updateOrCreate(
                ['tenant_id' => $this->tenant->id, 'chave' => $chave],
                [
                    'nome' => $nome,
                    'descricao' => $descricao,
                    'model_id' => 'gpt-4o-mini',
                    'system_prompt' => 'Você é um assistente administrativo de uma clínica de reabilitação. Responda somente com o JSON pedido.',
                    'user_prompt' => "Documento:\n{{documento}}\n\nDevolva os campos estruturados.",
                    'ativo' => true,
                ],
            );
        }

        foreach ([
            ['ficha_sessao', 'Ficha de sessão'],
            ['relatorio_mensal', 'Relatório mensal do paciente'],
        ] as [$chave, $nome]) {
            LancamentoPrintTemplate::query()->updateOrCreate(
                ['tenant_id' => $this->tenant->id, 'chave' => $chave],
                [
                    'nome' => $nome,
                    'html' => '<h1>{{paciente_nome}}</h1><p>Sessão em {{data_sessao}} com {{profissional_nome}}.</p><p>{{resumo_atividades}}</p>',
                    'ativo' => true,
                ],
            );
        }
    }

    /**
     * Trilha curada: ações que um dia de operação produz, distribuídas entre
     * os usuários seedados. Substitui os milhares de "created" que a carga em
     * massa geraria — a tela de auditoria fica legível e ainda tem o que filtrar.
     */
    /**
     * O histórico de transição de cada guia, espalhado no tempo.
     *
     * Reescreve o que a criação da guia gravou sozinha: o hook de `Guia` registra
     * a primeira transição com a hora de AGORA, então, sem isto, todas as guias
     * do seed apareceriam decididas hoje — e a aba de Operação, que conta pela
     * data da transição, mostraria um pico único no dia em que alguém rodou o
     * seeder. É o defeito mais fácil de não notar: os números existem, só estão
     * todos na data errada.
     */
    private function criarHistoricoDeStatus(): void
    {
        $guias = Guia::query()->where('tenant_id', $this->tenant->id)->get();

        GuiaStatusHistorico::query()->where('tenant_id', $this->tenant->id)->delete();

        $linhas = [];

        foreach ($guias as $guia) {
            $entrouEm = Carbon::parse($guia->data_solicitacao)
                ->setTime(mt_rand(8, 17), mt_rand(0, 59));

            $linhas[] = $this->transicao($guia, null, 'under_review', $entrouEm);

            if ($guia->status === 'under_review') {
                continue;
            }

            // Da entrada em análise à decisão: de duas horas a cinco dias. É a
            // dispersão que dá forma ao KPI de tempo médio e à diferença entre
            // média e mediana.
            $decididoEm = $entrouEm->copy()->addMinutes(mt_rand(120, 7200));

            if ($decididoEm->isFuture()) {
                $decididoEm = Carbon::now()->subHours(mt_rand(1, 48));
            }

            $intermediario = $guia->status === 'finalized' ? 'approved' : $guia->status;

            $linhas[] = $this->transicao($guia, 'under_review', $intermediario, $decididoEm);

            if ($guia->status !== 'finalized') {
                continue;
            }

            $aprovadoEm = $decididoEm->copy()->addDays(mt_rand(1, 20));

            $linhas[] = $this->transicao(
                $guia,
                'approved',
                'finalized',
                $aprovadoEm->isFuture() ? Carbon::now()->subHours(mt_rand(1, 24)) : $aprovadoEm,
            );
        }

        foreach (array_chunk($linhas, 500) as $lote) {
            GuiaStatusHistorico::query()->insert($lote);
        }
    }

    /** @return array<string, mixed> */
    private function transicao(Guia $guia, ?string $de, string $para, Carbon $quando): array
    {
        return [
            'tenant_id' => $this->tenant->id,
            'guia_id' => $guia->id,
            'de' => $de,
            'para' => $para,
            'ocorrido_em' => $quando,
            'origem' => GuiaStatusHistorico::ORIGEM_MIGRACAO,
            'motivo' => null,
            'created_at' => $quando,
        ];
    }

    /**
     * A fila de elegíveis: antecipações geradas e dispensadas ao longo do
     * período.
     *
     * Sem as duas, o KPI de dispensa nasce sem denominador e a aba de Operação
     * mostra "—" onde deveria mostrar a decisão do operador.
     */
    private function criarFilaDeAntecipacoes(): void
    {
        $solicitacoes = Solicitacao::query()
            ->where('tenant_id', $this->tenant->id)
            ->whereIn('status', ['guia_gerada', 'approved'])
            ->limit(40)
            ->get();

        foreach ($solicitacoes as $i => $solicitacao) {
            $quando = Carbon::today()
                ->subDays(mt_rand(0, self::DIAS_DE_HISTORICO))
                ->setTime(mt_rand(8, 17), mt_rand(0, 59));

            // Cerca de um terço é dispensado — o número que o KPI de dispensa
            // existe para acompanhar.
            $ignorada = $i % 3 === 0;

            Antecipacao::query()->create([
                'tenant_id' => $this->tenant->id,
                'solicitacao_origem_id' => $solicitacao->id,
                'status' => $ignorada ? Antecipacao::STATUS_IGNORADA : Antecipacao::STATUS_GERADA,
                'data_alvo' => $quando->copy()->addDays(mt_rand(3, 15))->toDateString(),
                'itens_selecionados' => [],
                'observacoes' => $ignorada ? 'Paciente em férias no período.' : null,
                'gerado_em' => $ignorada ? null : $quando,
                'ignorado_em' => $ignorada ? $quando : null,
            ]);
        }
    }

    /**
     * Lotes de importação confirmados, para a aba de Uso ter o que contar.
     *
     * Só o cabeçalho do lote: as linhas importadas já viraram cadastro pelos
     * outros métodos deste seeder, e duplicá-las aqui criaria paciente e guia
     * fantasma para alimentar um KPI.
     */
    private function criarLotesDeImportacao(): void
    {
        $tipos = [
            'paciente_import_lotes' => 'pacientes-marco.xlsx',
            'solicitacao_import_lotes' => 'solicitacoes-marco.xlsx',
            'guia_import_lotes' => 'guias-marco.xlsx',
            'lancamento_import_lotes' => 'sessoes-marco.xlsx',
            'conciliacao_import_lotes' => 'conciliacoes-marco.xlsx',
        ];

        foreach ($tipos as $tabela => $arquivo) {
            foreach (range(1, mt_rand(2, 4)) as $n) {
                $quando = Carbon::today()
                    ->subDays(mt_rand(0, self::DIAS_DE_HISTORICO))
                    ->setTime(mt_rand(9, 17), mt_rand(0, 59));

                $linhas = mt_rand(20, 180);
                // Planilha de clínica sempre traz alguma linha ruim; sem
                // nenhuma, a taxa de erro da aba de Uso seria sempre zero e o
                // indicador não serviria para nada.
                $invalidas = mt_rand(0, (int) round($linhas * 0.2));

                DB::table($tabela)->insert([
                    'tenant_id' => $this->tenant->id,
                    'arquivo_nome_original' => $n.'-'.$arquivo,
                    'arquivo_path' => 'importacoes/'.$n.'-'.$arquivo,
                    'status' => 'confirmado',
                    'confirmado_em' => $quando,
                    'total_linhas' => $linhas,
                    'total_validas' => $linhas - $invalidas,
                    'total_invalidas' => $invalidas,
                    'total_importados' => $linhas - $invalidas,
                    'total_atualizados' => 0,
                    'total_ignorados' => 0,
                    'created_at' => $quando,
                    'updated_at' => $quando,
                ]);
            }
        }
    }

    /**
     * Algumas quedas e voltas dos componentes monitorados.
     *
     * A tabela de eventos nasce vazia em produção e só enxerga do deploy em
     * diante — mas no ambiente de demonstração ela precisa ter conteúdo, senão
     * o KPI de horas fora do ar aparece como ausente e não dá para avaliar o
     * desenho da aba.
     */
    private function criarHistoricoDeSaude(): void
    {
        $componentes = SaudeComponente::query()->where('tenant_id', $this->tenant->id)->get();

        foreach ($componentes as $i => $componente) {
            $eventos = [[
                'estado' => SaudeComponente::ESTADO_SAUDAVEL,
                'quando' => Carbon::today()->subDays(self::DIAS_DE_HISTORICO)->setTime(0, 0),
            ]];

            // Nem todo componente cai: um deles fica no ar o período inteiro,
            // para a tabela ter a linha de zero hora fora e provar a diferença
            // entre "não caiu" e "não tenho registro".
            $quedas = $i === 0 ? 0 : mt_rand(1, 3);

            for ($n = 0; $n < $quedas; $n++) {
                $caiuEm = Carbon::today()
                    ->subDays(mt_rand(1, self::DIAS_DE_HISTORICO - 1))
                    ->setTime(mt_rand(0, 23), mt_rand(0, 59));

                $eventos[] = ['estado' => SaudeComponente::ESTADO_FORA, 'quando' => $caiuEm];
                $eventos[] = [
                    'estado' => SaudeComponente::ESTADO_SAUDAVEL,
                    'quando' => $caiuEm->copy()->addMinutes(mt_rand(15, 480)),
                ];
            }

            usort($eventos, fn (array $a, array $b) => $a['quando'] <=> $b['quando']);

            foreach ($eventos as $evento) {
                SaudeComponenteEvento::query()->create([
                    'tenant_id' => $this->tenant->id,
                    'saude_componente_id' => $componente->id,
                    'estado' => $evento['estado'],
                    'ocorrido_em' => $evento['quando'],
                    'mensagem' => $evento['estado'] === SaudeComponente::ESTADO_FORA
                        ? 'Sem sinal de vida além do intervalo esperado.'
                        : null,
                ]);
            }
        }
    }

    private function criarTrilhaAuditoria(): void
    {
        $usuarios = User::query()->where('tenant_id', $this->tenant->id)->get();

        if ($usuarios->isEmpty()) {
            return;
        }

        $acoes = [
            ['created', 'solicitacoes'],
            ['updated', 'solicitacoes'],
            ['created', 'guias'],
            ['updated', 'guias'],
            ['updated', 'guias'],
            ['created', 'antecipacoes'],
            ['updated', 'antecipacoes'],
            ['created', 'lancamentos'],
            ['updated', 'conciliacoes_financeiras'],
            ['updated', 'pacientes'],
            ['updated', 'users'],
            ['acesso.negado', 'users'],
        ];

        $linhas = [];

        // Noventa dias, e não quarenta e cinco: é o recorte que a aba de Uso
        // precisa para ter série, comparação com o período anterior e um mês
        // fechado para trás.
        for ($dia = 0; $dia < self::DIAS_DE_HISTORICO; $dia++) {
            $data = Carbon::today()->subDays($dia);

            // Fim de semana movimenta pouco. Sem isso, o gráfico de uso por dia
            // vira uma reta e não mostra nada sobre como a clínica trabalha.
            $quantas = $data->isWeekend() ? mt_rand(0, 3) : mt_rand(6, 18);

            for ($i = 0; $i < $quantas; $i++) {
                [$acao, $entidade] = $acoes[mt_rand(0, count($acoes) - 1)];
                $usuario = $usuarios[mt_rand(0, $usuarios->count() - 1)];

                $linhas[] = $this->linhaDeTrilha(
                    $usuario->id,
                    $acao,
                    $entidade,
                    $this->emHorarioDeExpediente($data),
                );
            }

            // Um login por pessoa que trabalhou no dia, logo cedo. A aba de Uso
            // conta acessos por `acesso.login`, que é o que o AuthController
            // grava de verdade — usar outro nome aqui faria o KPI nascer zerado.
            if ($quantas > 0) {
                foreach ($usuarios->take(mt_rand(1, $usuarios->count())) as $usuario) {
                    $linhas[] = $this->linhaDeTrilha(
                        $usuario->id,
                        'acesso.login',
                        'users',
                        $data->copy()->setTime(mt_rand(7, 9), mt_rand(0, 59)),
                    );
                }
            }
        }

        foreach (array_chunk($linhas, 500) as $lote) {
            AuditLog::query()->insert($lote);
        }
    }

    /** @return array<string, mixed> */
    private function linhaDeTrilha(int $usuarioId, string $acao, string $entidade, Carbon $quando): array
    {
        return [
            'tenant_id' => $this->tenant->id,
            'user_id' => $usuarioId,
            'acao' => $acao,
            'entidade' => $entidade,
            'entidade_id' => mt_rand(1, 120),
            'payload' => json_encode(['origem' => 'demo', 'ip_interno' => true]),
            'ip' => '192.168.0.'.mt_rand(2, 250),
            'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
            // audit_logs so tem created_at: a trilha e imutavel por desenho.
            'created_at' => $quando,
        ];
    }

    /**
     * Um instante dentro do expediente, com os dois picos que uma clínica tem:
     * a manhã, quando se organiza o dia, e o fim da tarde, quando se lança o
     * que foi feito.
     */
    private function emHorarioDeExpediente(Carbon $data): Carbon
    {
        $hora = mt_rand(0, 100) < 55
            ? mt_rand(8, 11)
            : mt_rand(14, 18);

        return $data->copy()->setTime($hora, mt_rand(0, 59), mt_rand(0, 59));
    }

    private function profissionalDaEspecialidade(int $especialidadeId): Profissional
    {
        $candidatos = array_values(array_filter(
            $this->profissionais,
            fn (Profissional $p) => $p->especialidade_id === $especialidadeId,
        ));

        return $candidatos === []
            ? $this->profissionais[array_rand($this->profissionais)]
            : $candidatos[array_rand($candidatos)];
    }

    private function resumo(): string
    {
        return implode(', ', [
            Paciente::query()->count().' pacientes',
            Solicitacao::query()->count().' solicitações',
            Guia::query()->count().' guias',
            Antecipacao::query()->count().' antecipações',
            Lancamento::query()->count().' lançamentos',
            ConciliacaoFinanceira::query()->count().' conciliações',
            AuditLog::query()->count().' eventos de auditoria',
        ]);
    }
}
