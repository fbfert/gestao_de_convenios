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
    to: '/lancamentos',
    label: 'Sessões',
    descricao:
      'O atendimento de fato realizado, baixado contra a guia. Alimenta o repasse do profissional.',
    metricKey: 'lancamentos',
    permissao: 'dashboard.lancamentos',
  },
  {
    to: '/antecipacoes',
    label: 'Antecipações',
    descricao:
      'A cota de sessões autorizadas por ciclo (ex.: 12/mês). Mostra quanto já foi usado e alerta quem está sem próxima sessão agendada.',
    metricKey: 'antecipacoes',
    permissao: 'dashboard.antecipacoes',
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
    to: '/configuracoes/unimed',
    label: 'Unimed RDA',
    descricao:
      'Credenciais do portal da Unimed e o de-para de especialidades e profissionais que a automação usa para gerar guias.',
    permissao: 'configuracoes.unimed.manage',
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

export const navEntries: NavEntry[] = [
  {
    to: '/dashboard',
    label: 'Gestão de Convênios',
    descricao: 'Visão geral com os números do seu papel e a auditoria recente.',
  },
  cadastrosGroup,
  operacaoGroup,
  { to: '/automacoes', label: 'Automações', descricao: 'Execuções automáticas no portal do convênio.' },
  configuracoesGroup,
  { to: '/manual', label: 'Manual', descricao: 'Documentação de uso do sistema.' },
]

/** Item visível apenas para quem administra clínicas. */
export const clinicasEntry: NavLeaf = {
  to: '/clinicas',
  label: 'Clínicas',
  descricao: 'Cadastro das clínicas atendidas pelo sistema.',
}

/** Mantém só os itens que o usuário pode acessar. */
export function filtrarItens(itens: NavLeaf[], pode: (permissao?: string) => boolean): NavLeaf[] {
  return itens.filter((item) => pode(item.permissao))
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
