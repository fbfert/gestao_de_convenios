/**
 * Estrutura do menu superior.
 *
 * Fica separada do ShellLayout porque as telas de grupo (Cadastros e Operacao
 * Convenios) montam os cartoes a partir da mesma lista: assim a ordem dos
 * submenus e a ordem dos cartoes nunca saem de sincronia.
 *
 * `metricKey` casa com o `key` dos blocos devolvidos por GET /dashboard
 * (DashboardController). Item sem metricKey simplesmente nao mostra numero.
 */

export type NavLeaf = {
  to: string
  label: string
  /** frase curta usada nos cartoes das telas de grupo */
  descricao: string
  /** chave do bloco em GET /dashboard, quando existe metrica */
  metricKey?: string
  /**
   * Permissao que habilita o item. Sem ela, o item vale para todo mundo —
   * caso do Dashboard e do Manual, que nao tem permissao propria na API.
   */
  permissao?: string
  /**
   * Item que aparece com QUALQUER UMA das permissoes da lista.
   *
   * Existe para Relatorios, que tem uma permissao por aba: quem pode ver
   * qualquer uma das quatro precisa enxergar a entrada no menu. As
   * alternativas eram pior — uma quinta permissao "abrir relatorios" seria
   * mais uma caixa para o admin marcar e mais uma para alguem esquecer, e
   * repetir o item quatro vezes no menu e absurdo.
   *
   * Vale JUNTO com `permissao`, e nao no lugar dela: um item pode exigir uma e
   * qualquer uma das outras. Nenhum item faz isso hoje, mas o `filtrarItens`
   * trata os dois campos separadamente, entao a combinacao nao vira surpresa.
   */
  permissaoQualquerUma?: string[]
}

export type NavGroup = {
  to: string
  label: string
  descricao: string
  children: NavLeaf[]
}

export type NavEntry = NavLeaf | NavGroup

export function isGroup(entry: NavEntry): entry is NavGroup {
  return 'children' in entry
}

export const cadastrosItems: NavLeaf[] = [
  {
    to: '/pacientes',
    label: 'Pacientes',
    descricao:
      'Quem recebe o atendimento. Guarda nome, CPF, carteirinha e o convênio a que o paciente pertence.',
    metricKey: 'pacientes',
    permissao: 'dashboard.pacientes',
  },
  {
    to: '/profissionais',
    label: 'Profissionais',
    descricao:
      'Quem executa as sessões na clínica. Cada profissional tem uma especialidade e um percentual de repasse.',
    metricKey: 'profissionais',
    permissao: 'dashboard.profissionais',
  },
  {
    to: '/especialidades',
    label: 'Especialidades',
    descricao:
      'As terapias oferecidas (fisioterapia, fonoaudiologia, ...). Classificam profissionais, solicitações e guias.',
    metricKey: 'especialidades',
    permissao: 'dashboard.especialidades',
  },
  {
    to: '/cids',
    label: 'CIDs',
    descricao:
      'Os códigos CID-10 usados nas solicitações — cada terapia precisa de um diagnóstico vinculado.',
  },
  {
    to: '/medicos',
    label: 'Médicos',
    descricao:
      'Os médicos externos que assinam o pedido. É o solicitante que aparece na guia enviada ao convênio.',
    metricKey: 'medicos',
    permissao: 'dashboard.medicos',
  },
  {
    to: '/convenios',
    label: 'Convênios',
    descricao:
      'As operadoras atendidas e as regras de cada uma: validade de senha, limites e o conector de automação.',
    metricKey: 'convenios',
    permissao: 'dashboard.convenios',
  },
  {
    to: '/usuarios',
    label: 'Usuários',
    descricao:
      'Quem entra no sistema e com qual papel. O papel define as telas visíveis e o que cada um pode alterar.',
    metricKey: 'usuarios',
    permissao: 'dashboard.usuarios',
  },
]

export const operacaoItems: NavLeaf[] = [
  {
    to: '/pacientes',
    label: 'Pacientes',
    descricao:
      'Ponto de partida: confirme que o paciente existe e que a carteirinha está correta antes de abrir o pedido.',
    metricKey: 'pacientes',
    permissao: 'dashboard.pacientes',
  },
  {
    to: '/solicitacoes',
    label: 'Solicitações',
    descricao:
      'Registre o pedido médico, com uma ou mais especialidades e os anexos. É o documento que origina as guias.',
    metricKey: 'solicitacoes',
    permissao: 'dashboard.solicitacoes',
  },
  {
    to: '/guias',
    label: 'Guias',
    descricao:
      'A autorização por especialidade. É aqui que a senha do convênio e a validade ficam registradas.',
    metricKey: 'guias',
    permissao: 'dashboard.guias',
  },
  {
    to: '/antecipacoes',
    label: 'Antecipações',
    descricao:
      'Guias perto da data de gerar o próximo ciclo, e o histórico do que já foi antecipado manualmente.',
    permissao: 'dashboard.antecipacoes',
  },
  {
    to: '/lancamentos',
    label: 'Sessões',
    descricao:
      'O atendimento de fato realizado, baixado contra a guia. Alimenta o repasse do profissional.',
    metricKey: 'lancamentos',
    permissao: 'dashboard.lancamentos',
  },
  {
    to: '/analiticos',
    label: 'Analíticos',
    descricao:
      'Importa o demonstrativo de pagamento do convênio, com os valores pagos e as glosas de cada linha.',
    metricKey: 'analiticos',
    permissao: 'dashboard.analiticos',
  },
  {
    to: '/conciliacao',
    label: 'Conciliações',
    descricao:
      'Fecha o ciclo: confronta o que foi executado com o que o convênio pagou e aponta as divergências.',
    metricKey: 'conciliacoes',
    permissao: 'dashboard.conciliacoes',
  },
]

export const configuracoesItems: NavLeaf[] = [
  {
    to: '/configuracoes/globais',
    label: 'Globais',
    descricao:
      'Parâmetros de comportamento do sistema: tempo de sessão, antecedência do aviso de senha vencendo, sessões sugeridas por especialidade e itens por página.',
    permissao: 'configuracoes.manage',
  },
  {
    to: '/configuracoes/emails',
    label: 'Envio de E-mails',
    descricao:
      'Servidor SMTP que dispara os e-mails do sistema: host, porta, credenciais e remetente. Enquanto estiver vazio, nenhum e-mail sai.',
    permissao: 'configuracoes.manage',
  },
  {
    to: '/configuracoes/ia',
    label: 'Configurações de IA',
    descricao:
      'Credenciais da OpenAI usadas na leitura automática de documentos: chave, base URL, organização e projeto.',
    permissao: 'configuracoes.manage',
  },
  {
    to: '/configuracoes/ia/prompts',
    label: 'Prompts Operacionais',
    descricao:
      'As instruções que a IA recebe para transformar cada tipo de documento em dados. Permite criar, editar e excluir prompts.',
    permissao: 'configuracoes.manage',
  },
  {
    to: '/configuracoes/templates-emails',
    label: 'Templates de E-mails',
    descricao:
      'O texto de cada mensagem que o sistema envia. Define assunto, corpo e as variáveis substituídas no disparo.',
    permissao: 'configuracoes.manage',
  },
  {
    to: '/configuracoes/convenios',
    label: 'Convênios e credenciais',
    descricao:
      'Credencial de automação de cada convênio, e o de-para de especialidades e profissionais que a automação usa para gerar guias.',
    // A permissão nova basta no menu: a migration de sincronização a concedeu a
    // todo papel que tinha `configuracoes.unimed.manage`, então ninguém perde o
    // item na virada. A rota ainda aceita as duas, por um ciclo.
    permissao: 'configuracoes.convenios.manage',
  },
  {
    to: '/configuracoes/clinica',
    label: 'Sincronização com o clinica',
    descricao:
      'Status da sincronização automática de profissionais e pacientes com o clinica.gestaonossa.com.br, e o botão para rodar na hora.',
    permissao: 'configuracoes.clinica.manage',
  },
  {
    to: '/alertas/configuracoes',
    label: 'Regras de Alerta',
    descricao:
      'O que dispara alerta e com que limiar. Ligar, desligar e ajustar o corte de cada regra sem precisar de deploy.',
    permissao: 'alertas.manage',
  },
  {
    to: '/permissoes',
    label: 'Perfis e Permissões',
    descricao:
      'Os papéis da clínica e o que cada um enxerga e altera. Definir papel é configuração do sistema; cadastrar a pessoa continua em Cadastros → Usuários.',
    permissao: 'permissoes.manage',
  },
  {
    to: '/auditoria',
    label: 'Logs de Auditoria',
    descricao:
      'Linha do tempo de quem alterou o quê, para revisar mudanças críticas depois que elas acontecem.',
    permissao: 'dashboard.auditoria',
  },
]

export const automacoesItems: NavLeaf[] = [
  {
    to: '/automacoes',
    label: 'Execuções',
    descricao: 'Histórico de execuções automáticas no portal do convênio, com reprocessamento das que falharam.',
  },
  {
    to: '/automacoes/configuracoes',
    label: 'Configurações',
    descricao: 'Prazos de reconsulta do status Unimed — normal e após falha técnica.',
    permissao: 'configuracoes.manage',
  },
]

export const automacoesGroup: NavGroup = {
  to: '/automacoes',
  label: 'Automações',
  descricao: 'Execuções automáticas no portal do convênio.',
  children: automacoesItems,
}

export const configuracoesGroup: NavGroup = {
  to: '/configuracoes',
  label: 'Configurações',
  descricao: 'Aparência e integrações do sistema.',
  children: configuracoesItems,
}

export const cadastrosGroup: NavGroup = {
  to: '/cadastros',
  label: 'Cadastros',
  descricao:
    'As bases fixas do sistema. São os dados que mudam pouco e que todas as telas de operação consultam.',
  children: cadastrosItems,
}

export const operacaoGroup: NavGroup = {
  // A rota continua /operacao-convenios: so o rotulo do menu encurtou.
  to: '/operacao-convenios',
  label: 'Operação',
  descricao:
    'O dia a dia com as operadoras, do pedido médico até a conferência do pagamento.',
  children: operacaoItems,
}

/** As quatro permissões de relatório — uma por aba. */
export const permissoesDeRelatorio = [
  'relatorios.operacao',
  'relatorios.financeiro',
  'relatorios.automacoes',
  'relatorios.uso',
]

export const gestaoConveniosItems: NavLeaf[] = [
  {
    to: '/dashboard',
    label: 'Painel',
    descricao:
      'O agora da clínica: guias pendentes, senhas vencendo, alertas abertos e os números do seu papel.',
  },
  {
    to: '/relatorios',
    label: 'Relatórios',
    descricao:
      'Os números por período, com comparação: aprovação e negação, dinheiro, automações e uso do sistema.',
    permissaoQualquerUma: permissoesDeRelatorio,
  },
]

export const gestaoConveniosGroup: NavGroup = {
  to: '/inicio',
  label: 'Gestão de Convênios',
  descricao: 'O painel do dia a dia e os relatórios por período.',
  children: gestaoConveniosItems,
}

export const navEntries: NavEntry[] = [
  // Grupo, e não link direto para o painel: o painel responde "o que está
  // aberto agora" e os relatórios respondem "como foi o mês" — duas perguntas
  // diferentes que merecem duas entradas. Custa um clique a mais para chegar
  // ao painel, e é uma escolha consciente pela consistência com Cadastros e
  // Operação. A HomePage continua levando direto ao painel.
  gestaoConveniosGroup,
  cadastrosGroup,
  operacaoGroup,
  // Entrada própria, e não dentro de Automações: senha vencendo e guia negada
  // são operação, não automação. A CONFIGURAÇÃO das regras é que fica também
  // alcançável por Automações → Configurações.
  {
    to: '/alertas',
    label: 'Alertas',
    descricao:
      'O que precisa de você agora: senha vencendo, guia negada, automação falhando e componente fora do ar.',
    permissao: 'alertas.view',
  },
  automacoesGroup,
  configuracoesGroup,
  { to: '/manual', label: 'Manual', descricao: 'Documentação de uso do sistema.' },
  // Novidades saiu do menu em 16/09/2026: quem chega nelas vem do card do
  // painel, que já mostra as últimas e marca as não lidas. Um item fixo no
  // topo, para um conteúdo que se lê uma vez por deploy, competia por espaço
  // com o que se usa todo dia. A rota `/novidades` continua existindo.
]

/** Item visível apenas para quem administra clínicas. */
export const clinicasEntry: NavLeaf = {
  to: '/clinicas',
  label: 'Clínicas',
  descricao: 'Cadastro das clínicas atendidas pelo sistema.',
}

/** Mantém só os itens que o usuário pode acessar. */
export function filtrarItens(itens: NavLeaf[], pode: (permissao?: string) => boolean): NavLeaf[] {
  return itens.filter((item) => {
    if (!pode(item.permissao)) {
      return false
    }

    // Lista vazia ou ausente não restringe nada — quem não usa o campo não
    // muda de comportamento.
    return (item.permissaoQualquerUma ?? []).length === 0
      ? true
      : item.permissaoQualquerUma!.some((permissao) => pode(permissao))
  })
}

/**
 * Menu final: entradas do papel do usuário, mais `Clínicas` para super admin.
 *
 * Esconder item é conveniência, não segurança — quem barra de fato é o
 * middleware `permission:` da API. Grupo que fica sem nenhum filho sai do menu
 * inteiro: um grupo que abre um painel vazio é pior do que grupo nenhum.
 */
export function montarMenu(
  superAdmin: boolean,
  pode: (permissao?: string) => boolean,
): NavEntry[] {
  const entradas = superAdmin ? [...navEntries, clinicasEntry] : navEntries

  return entradas.flatMap((entrada) => {
    if (!isGroup(entrada)) {
      return pode(entrada.permissao) ? [entrada] : []
    }

    const children = filtrarItens(entrada.children, pode)

    return children.length > 0 ? [{ ...entrada, children }] : []
  })
}
