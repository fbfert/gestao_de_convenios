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
  },
  {
    to: '/profissionais',
    label: 'Profissionais',
    descricao:
      'Quem executa as sessões na clínica. Cada profissional tem uma especialidade e um percentual de repasse.',
    metricKey: 'profissionais',
  },
  {
    to: '/especialidades',
    label: 'Especialidades',
    descricao:
      'As terapias oferecidas (fisioterapia, fonoaudiologia, ...). Classificam profissionais, solicitações e guias.',
    metricKey: 'especialidades',
  },
  {
    to: '/medicos',
    label: 'Médicos',
    descricao:
      'Os médicos externos que assinam o pedido. É o solicitante que aparece na guia enviada ao convênio.',
    metricKey: 'medicos',
  },
  {
    to: '/convenios',
    label: 'Convênios',
    descricao:
      'As operadoras atendidas e as regras de cada uma: validade de senha, limites e o conector de automação.',
    metricKey: 'convenios',
  },
  {
    to: '/usuarios',
    label: 'Usuários',
    descricao:
      'Quem entra no sistema e com qual papel. O papel define as telas visíveis e o que cada um pode alterar.',
    metricKey: 'usuarios',
  },
]

export const operacaoItems: NavLeaf[] = [
  {
    to: '/pacientes',
    label: 'Pacientes',
    descricao:
      'Ponto de partida: confirme que o paciente existe e que a carteirinha está correta antes de abrir o pedido.',
    metricKey: 'pacientes',
  },
  {
    to: '/solicitacoes',
    label: 'Solicitações',
    descricao:
      'Registre o pedido médico, com uma ou mais especialidades e os anexos. É o documento que origina as guias.',
    metricKey: 'solicitacoes',
  },
  {
    to: '/guias',
    label: 'Guias',
    descricao:
      'A autorização por especialidade. É aqui que a senha do convênio e a validade ficam registradas.',
    metricKey: 'guias',
  },
  {
    to: '/lancamentos',
    label: 'Sessões',
    descricao:
      'O atendimento de fato realizado, baixado contra a guia. Alimenta o repasse do profissional.',
    metricKey: 'lancamentos',
  },
  {
    to: '/antecipacoes',
    label: 'Antecipações',
    descricao:
      'Agrupa sessões já executadas em um lote de pagamento adiantado ao profissional, antes do convênio pagar.',
    metricKey: 'antecipacoes',
  },
  {
    to: '/analiticos',
    label: 'Analíticos',
    descricao:
      'Importa o demonstrativo de pagamento do convênio, com os valores pagos e as glosas de cada linha.',
    metricKey: 'analiticos',
  },
  {
    to: '/conciliacao',
    label: 'Conciliações',
    descricao:
      'Fecha o ciclo: confronta o que foi executado com o que o convênio pagou e aponta as divergências.',
    metricKey: 'conciliacoes',
  },
]

export const configuracoesItems: NavLeaf[] = [
  {
    to: '/configuracoes/emails',
    label: 'Envio de E-mails',
    descricao:
      'Servidor SMTP que dispara os e-mails do sistema: host, porta, credenciais e remetente. Enquanto estiver vazio, nenhum e-mail sai.',
  },
  {
    to: '/configuracoes/ia',
    label: 'Configurações de IA',
    descricao:
      'Credenciais da OpenAI usadas na leitura automática de documentos: chave, base URL, organização e projeto.',
  },
  {
    to: '/configuracoes/ia/prompts',
    label: 'Prompts Operacionais',
    descricao:
      'As instruções que a IA recebe para transformar cada tipo de documento em dados. Permite criar, editar e excluir prompts.',
  },
  {
    to: '/configuracoes/templates-emails',
    label: 'Templates de E-mails',
    descricao:
      'O texto de cada mensagem que o sistema envia. Define assunto, corpo e as variáveis substituídas no disparo.',
  },
  {
    to: '/configuracoes/unimed',
    label: 'Unimed RDA',
    descricao:
      'Credenciais do portal da Unimed e o de-para de especialidades e profissionais que a automação usa para gerar guias.',
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
