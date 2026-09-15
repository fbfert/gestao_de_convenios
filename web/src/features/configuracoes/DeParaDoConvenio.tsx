import { useEffect, useState, type FormEvent, type ReactNode } from 'react'
import { Botao } from '../../components/ui/Botao'
import { Tabela } from '../../components/ui/Tabela'
import { Tooltip } from '../../components/ui/Tooltip'
import { useEspecialidades, useProfissionais } from '../../lib/queries/useReferenceData'
import {
  getHttpErrorMessage,
  useEspecialidadeMapeamentos,
  useProfissionalMapeamentos,
  useSalvarEspecialidadeMapeamento,
  useSalvarProfissionalMapeamento,
  type EspecialidadeMapeamentoForm,
  type ProfissionalMapeamentoForm,
} from './useConvenioCredenciais'

/**
 * Os dois de-para do convênio: especialidade e profissional.
 *
 * Saiu da `ConfiguracoesConveniosPage` por tamanho, e do formato antigo por
 * outra razão: lá eram seis campos empilhados SEM rótulo nenhum — só
 * placeholder, que some assim que se digita — e a lista era uma linha de texto
 * corrida separada por pontos. O campo de quantidade era um `number` mudo.
 *
 * Aqui cada campo tem rótulo, a lista é tabela com colunas, e o que é
 * consequência de erro está no tooltip, não descoberto na recusa da guia.
 */

function inputClasses() {
  return 'w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-corpo text-white outline-none transition placeholder:text-texto-suave focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20'
}

/** Rótulo com a dica ao lado — o par que substituiu os placeholders. */
function Campo({
  htmlFor,
  rotulo,
  dica,
  dicaTitulo,
  children,
}: {
  htmlFor: string
  rotulo: string
  dica: ReactNode
  dicaTitulo: string
  children: ReactNode
}) {
  return (
    <div className="space-y-2">
      <div className="flex items-center gap-1.5">
        <label className="text-meta uppercase tracking-[0.2em] text-slate-300" htmlFor={htmlFor}>
          {rotulo}
        </label>
        <Tooltip rotulo={dicaTitulo}>{dica}</Tooltip>
      </div>
      {children}
    </div>
  )
}

const especialidadeVazia: EspecialidadeMapeamentoForm = {
  especialidade_id: '',
  codigo_procedimento: '',
  descricao_operadora: '',
  quantidade_padrao: '10',
  usa_descricao_generica: false,
  valor_generico: '',
  ativo: true,
}

const profissionalVazio: ProfissionalMapeamentoForm = {
  profissional_id: '',
  codigo_operadora: '',
  nome_operadora: '',
  ativo: true,
}

export function DeParaDoConvenio({
  convenioId,
  onMensagem,
  onErro,
}: {
  convenioId: number
  onMensagem: (texto: string) => void
  onErro: (texto: string) => void
}) {
  return (
    <section className="space-y-6" data-testid="convenios-credenciais-de-para">
      <DeParaEspecialidade convenioId={convenioId} onMensagem={onMensagem} onErro={onErro} />
      <DeParaProfissional convenioId={convenioId} onMensagem={onMensagem} onErro={onErro} />
    </section>
  )
}

function DeParaEspecialidade({
  convenioId,
  onMensagem,
  onErro,
}: {
  convenioId: number
  onMensagem: (texto: string) => void
  onErro: (texto: string) => void
}) {
  const especialidadesQuery = useEspecialidades()
  const mapeamentos = useEspecialidadeMapeamentos(convenioId)
  const salvar = useSalvarEspecialidadeMapeamento()

  const [form, setForm] = useState<EspecialidadeMapeamentoForm>(especialidadeVazia)
  const [editandoId, setEditandoId] = useState<number | undefined>()
  const [aberto, setAberto] = useState(false)
  // A descrição alternativa fica escondida por padrão: ela só existe para
  // sobrepor a primeira, e obrigar todo mundo a entender essa precedência era
  // parte do que tornava o painel confuso.
  const [mostrarAlternativa, setMostrarAlternativa] = useState(false)

  const especialidades = especialidadesQuery.data ?? []
  const linhas = mapeamentos.data ?? []

  // Trocar de convênio abandona a edição: um de-para é daquele convênio, e
  // salvar o formulário aberto no convênio seguinte gravaria no lugar errado.
  useEffect(() => {
    setForm(especialidadeVazia)
    setEditandoId(undefined)
    setAberto(false)
    setMostrarAlternativa(false)
  }, [convenioId])

  const editar = (linha: (typeof linhas)[number]) => {
    setEditandoId(linha.id)
    setAberto(true)
    setMostrarAlternativa(Boolean(linha.valor_generico))
    setForm({
      especialidade_id: String(linha.especialidade_id),
      codigo_procedimento: linha.codigo_procedimento,
      descricao_operadora: linha.descricao_operadora ?? '',
      quantidade_padrao: String(linha.quantidade_padrao),
      usa_descricao_generica: linha.usa_descricao_generica,
      valor_generico: linha.valor_generico ?? '',
      ativo: linha.ativo,
    })
  }

  const fechar = () => {
    setEditandoId(undefined)
    setAberto(false)
    setMostrarAlternativa(false)
    setForm(especialidadeVazia)
  }

  const submeter = async (event: FormEvent) => {
    event.preventDefault()

    try {
      await salvar.mutateAsync({ convenioId, id: editandoId, payload: form })
      onMensagem('De-para de especialidade salvo.')
      fechar()
    } catch (erro) {
      onErro(getHttpErrorMessage(erro, 'Não foi possível salvar o de-para de especialidade.'))
    }
  }

  const nomeEmEdicao = especialidades.find(
    (especialidade) => String(especialidade.id) === form.especialidade_id,
  )?.nome

  return (
    <div className="rounded-janela border border-linha bg-superficie-elevada p-6 shadow-e2">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h3 className="text-subtitulo font-semibold text-white">Especialidade × Convênio</h3>
          <p className="mt-1 text-meta text-slate-400">
            Como cada especialidade da clínica é identificada neste convênio.
          </p>
        </div>
        {!aberto ? (
          <Botao type="button" onClick={() => setAberto(true)} data-testid="de-para-especialidade-novo">
            Acrescentar
          </Botao>
        ) : null}
      </div>

      <div className="mt-4">
        {linhas.length === 0 ? (
          <p className="text-corpo text-slate-400">
            Nenhuma especialidade configurada neste convênio.
          </p>
        ) : (
          <Tabela
            legenda={`De-para de especialidades do convênio (${linhas.length})`}
            densidade="compacta"
            cartoes="md"
          >
            <Tabela.Cabecalho>
              <Tabela.Linha>
                <Tabela.CelulaCabecalho>Especialidade</Tabela.CelulaCabecalho>
                <Tabela.CelulaCabecalho>Código</Tabela.CelulaCabecalho>
                <Tabela.CelulaCabecalho>Sessões</Tabela.CelulaCabecalho>
                <Tabela.CelulaCabecalho>Descrição</Tabela.CelulaCabecalho>
                <Tabela.CelulaCabecalho>
                  <span className="sr-only">Ações</span>
                </Tabela.CelulaCabecalho>
              </Tabela.Linha>
            </Tabela.Cabecalho>
            <Tabela.Corpo>
              {linhas.map((linha) => {
                const descricao = linha.valor_generico || linha.descricao_operadora
                // Item genérico sem descrição é a configuração que derruba a
                // guia no portal. A tabela precisa mostrar isso de relance.
                const faltaDescricao = linha.usa_descricao_generica && !descricao

                return (
                  <Tabela.Linha key={linha.id} selecionada={editandoId === linha.id}>
                    <Tabela.Celula rotulo="Especialidade">
                      {linha.especialidade?.nome ?? linha.especialidade_id}
                    </Tabela.Celula>
                    <Tabela.Celula rotulo="Código" numerica>
                      {linha.codigo_procedimento}
                    </Tabela.Celula>
                    <Tabela.Celula rotulo="Sessões" numerica>
                      {linha.quantidade_padrao}
                    </Tabela.Celula>
                    <Tabela.Celula rotulo="Descrição">
                      {faltaDescricao ? (
                        <span className="text-alerta-texto" data-testid={`de-para-alerta-${linha.id}`}>
                          ⚠ item genérico sem descrição
                        </span>
                      ) : (
                        (descricao ?? '—')
                      )}
                    </Tabela.Celula>
                    <Tabela.Celula>
                      <Botao
                        type="button"
                        variante="secundario"
                        tamanho="sm"
                        onClick={() => editar(linha)}
                        data-testid={`de-para-especialidade-editar-${linha.id}`}
                      >
                        Editar
                      </Botao>
                    </Tabela.Celula>
                  </Tabela.Linha>
                )
              })}
            </Tabela.Corpo>
          </Tabela>
        )}
      </div>

      {aberto ? (
        <form onSubmit={submeter} className="mt-6 space-y-4 border-t border-linha pt-6">
          <p className="text-corpo font-semibold text-white">
            {editandoId ? `Editando ${nomeEmEdicao ?? 'especialidade'}` : 'Nova especialidade'}
          </p>

          <Campo
            htmlFor="de-para-especialidade"
            rotulo="Especialidade"
            dicaTitulo="Qual especialidade"
            dica="A especialidade cadastrada na clínica. Cada uma precisa de uma linha por convênio: sem ela, a automação recusa o item com “Mapeamento Especialidade x Convênio não configurado”."
          >
            <select
              id="de-para-especialidade"
              value={form.especialidade_id}
              onChange={(event) =>
                setForm((atual) => ({ ...atual, especialidade_id: event.target.value }))
              }
              className={inputClasses()}
              data-testid="de-para-especialidade"
            >
              <option value="">Selecione…</option>
              {especialidades.map((especialidade) => (
                <option key={especialidade.id} value={especialidade.id}>
                  {especialidade.nome}
                </option>
              ))}
            </select>
          </Campo>

          <Campo
            htmlFor="de-para-codigo-procedimento"
            rotulo="Código do procedimento"
            dicaTitulo="Código do procedimento"
            dica="O código que ESTE convênio usa para a especialidade — cada operadora tem o seu. A Psicologia ABA é 2250005286 na Unimed. Código errado não é conferido aqui: aparece na recusa da guia pela operadora."
          >
            <input
              id="de-para-codigo-procedimento"
              value={form.codigo_procedimento}
              onChange={(event) =>
                setForm((atual) => ({ ...atual, codigo_procedimento: event.target.value }))
              }
              className={inputClasses()}
              data-testid="de-para-codigo-procedimento"
            />
          </Campo>

          <Campo
            htmlFor="de-para-quantidade"
            rotulo="Sessões por guia"
            dicaTitulo="Sessões por guia"
            dica="Quantas sessões a automação pede por guia quando a solicitação não informa uma quantidade própria. A quantidade do item, quando existe, tem prioridade sobre esta."
          >
            <input
              id="de-para-quantidade"
              type="number"
              min="1"
              value={form.quantidade_padrao}
              onChange={(event) =>
                setForm((atual) => ({ ...atual, quantidade_padrao: event.target.value }))
              }
              className={inputClasses()}
              data-testid="de-para-quantidade"
            />
          </Campo>

          <div className="space-y-3 rounded-2xl border border-white/10 bg-white/5 p-4">
            <div className="flex items-center gap-1.5">
              <label className="flex items-center gap-2 text-corpo text-slate-200">
                <input
                  type="checkbox"
                  checked={form.usa_descricao_generica}
                  onChange={(event) =>
                    setForm((atual) => ({ ...atual, usa_descricao_generica: event.target.checked }))
                  }
                  data-testid="de-para-usa-descricao-generica"
                />
                Item genérico
              </label>
              <Tooltip rotulo="Item genérico">
                Alguns códigos da operadora não têm procedimento fixo: o portal abre um campo
                exigindo a descrição por extenso. Ligado isto e sem descrição preenchida, a guia
                falha com CONFIGURATION_INVALID_ITEM — foi o que a Unimed recusou em 15/09/2026 no
                código 2250005286, da Psicologia ABA.
              </Tooltip>
            </div>

            {/*
              Os campos de descrição só aparecem quando o item é genérico: fora
              disso o portal nem mostra o campo, e pedi-los seria ruído.
            */}
            {form.usa_descricao_generica ? (
              <div className="space-y-3">
                <Campo
                  htmlFor="de-para-descricao-operadora"
                  rotulo="Descrição do procedimento"
                  dicaTitulo="Descrição do procedimento"
                  dica="O texto que a automação digita no campo de descrição do portal — o procedimento por extenso, nunca o código. Ex.: “Sessão de Psicologia ABA”."
                >
                  <input
                    id="de-para-descricao-operadora"
                    value={form.descricao_operadora}
                    onChange={(event) =>
                      setForm((atual) => ({ ...atual, descricao_operadora: event.target.value }))
                    }
                    className={inputClasses()}
                    data-testid="de-para-descricao-operadora"
                  />
                </Campo>

                {mostrarAlternativa ? (
                  <Campo
                    htmlFor="de-para-valor-generico"
                    rotulo="Descrição alternativa"
                    dicaTitulo="Descrição alternativa"
                    dica="Quando preenchida, é ESTA que a automação usa — a descrição acima fica ignorada. Existe para corrigir um caso pontual sem apagar o texto padrão. Deixe vazia se não precisar."
                  >
                    <input
                      id="de-para-valor-generico"
                      value={form.valor_generico}
                      onChange={(event) =>
                        setForm((atual) => ({ ...atual, valor_generico: event.target.value }))
                      }
                      className={inputClasses()}
                      data-testid="de-para-valor-generico"
                    />
                  </Campo>
                ) : (
                  <button
                    type="button"
                    onClick={() => setMostrarAlternativa(true)}
                    className="text-corpo font-semibold text-acento underline-offset-2 hover:underline"
                    data-testid="de-para-mostrar-alternativa"
                  >
                    Usar uma descrição alternativa
                  </button>
                )}
              </div>
            ) : null}
          </div>

          <div className="flex flex-wrap gap-3">
            <Botao
              type="submit"
              disabled={!form.especialidade_id || !form.codigo_procedimento}
              carregando={salvar.isPending}
              data-testid="de-para-especialidade-salvar"
            >
              {editandoId ? 'Salvar alteração' : 'Acrescentar'}
            </Botao>
            <Botao
              type="button"
              variante="secundario"
              onClick={fechar}
              data-testid="de-para-especialidade-cancelar"
            >
              Cancelar
            </Botao>
          </div>
        </form>
      ) : null}
    </div>
  )
}

function DeParaProfissional({
  convenioId,
  onMensagem,
  onErro,
}: {
  convenioId: number
  onMensagem: (texto: string) => void
  onErro: (texto: string) => void
}) {
  const profissionaisQuery = useProfissionais({ incluir_inativos: true })
  const mapeamentos = useProfissionalMapeamentos(convenioId)
  const salvar = useSalvarProfissionalMapeamento()

  const [form, setForm] = useState<ProfissionalMapeamentoForm>(profissionalVazio)
  const [editandoId, setEditandoId] = useState<number | undefined>()
  const [aberto, setAberto] = useState(false)

  const profissionais = profissionaisQuery.data ?? []
  const linhas = mapeamentos.data ?? []

  useEffect(() => {
    setForm(profissionalVazio)
    setEditandoId(undefined)
    setAberto(false)
  }, [convenioId])

  const fechar = () => {
    setEditandoId(undefined)
    setAberto(false)
    setForm(profissionalVazio)
  }

  const submeter = async (event: FormEvent) => {
    event.preventDefault()

    try {
      await salvar.mutateAsync({ convenioId, id: editandoId, payload: form })
      onMensagem('De-para de profissional salvo.')
      fechar()
    } catch (erro) {
      onErro(getHttpErrorMessage(erro, 'Não foi possível salvar o de-para de profissional.'))
    }
  }

  const nomeEmEdicao = profissionais.find(
    (profissional) => String(profissional.id) === form.profissional_id,
  )?.nome

  return (
    <div className="rounded-janela border border-linha bg-superficie-elevada p-6 shadow-e2">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h3 className="text-subtitulo font-semibold text-white">Profissional × Convênio</h3>
          <p className="mt-1 text-meta text-slate-400">
            Como cada profissional da clínica é identificado neste convênio.
          </p>
        </div>
        {!aberto ? (
          <Botao type="button" onClick={() => setAberto(true)} data-testid="de-para-profissional-novo">
            Acrescentar
          </Botao>
        ) : null}
      </div>

      <div className="mt-4">
        {linhas.length === 0 ? (
          <p className="text-corpo text-slate-400">
            Nenhum profissional configurado neste convênio.
          </p>
        ) : (
          <Tabela
            legenda={`De-para de profissionais do convênio (${linhas.length})`}
            densidade="compacta"
            cartoes="md"
          >
            <Tabela.Cabecalho>
              <Tabela.Linha>
                <Tabela.CelulaCabecalho>Profissional</Tabela.CelulaCabecalho>
                <Tabela.CelulaCabecalho>Código na operadora</Tabela.CelulaCabecalho>
                <Tabela.CelulaCabecalho>Nome na operadora</Tabela.CelulaCabecalho>
                <Tabela.CelulaCabecalho>
                  <span className="sr-only">Ações</span>
                </Tabela.CelulaCabecalho>
              </Tabela.Linha>
            </Tabela.Cabecalho>
            <Tabela.Corpo>
              {linhas.map((linha) => (
                <Tabela.Linha key={linha.id} selecionada={editandoId === linha.id}>
                  <Tabela.Celula rotulo="Profissional">
                    {linha.profissional?.nome ?? linha.profissional_id}
                  </Tabela.Celula>
                  <Tabela.Celula rotulo="Código na operadora" numerica>
                    {linha.codigo_operadora}
                  </Tabela.Celula>
                  <Tabela.Celula rotulo="Nome na operadora">
                    {linha.nome_operadora ?? '—'}
                  </Tabela.Celula>
                  <Tabela.Celula>
                    <Botao
                      type="button"
                      variante="secundario"
                      tamanho="sm"
                      onClick={() => {
                        setEditandoId(linha.id)
                        setAberto(true)
                        setForm({
                          profissional_id: String(linha.profissional_id),
                          codigo_operadora: linha.codigo_operadora,
                          nome_operadora: linha.nome_operadora ?? '',
                          ativo: linha.ativo,
                        })
                      }}
                      data-testid={`de-para-profissional-editar-${linha.id}`}
                    >
                      Editar
                    </Botao>
                  </Tabela.Celula>
                </Tabela.Linha>
              ))}
            </Tabela.Corpo>
          </Tabela>
        )}
      </div>

      {aberto ? (
        <form onSubmit={submeter} className="mt-6 space-y-4 border-t border-linha pt-6">
          <p className="text-corpo font-semibold text-white">
            {editandoId ? `Editando ${nomeEmEdicao ?? 'profissional'}` : 'Novo profissional'}
          </p>

          <Campo
            htmlFor="de-para-profissional"
            rotulo="Profissional"
            dicaTitulo="Qual profissional"
            dica="O profissional cadastrado na clínica. Sem esta linha, a automação recusa o item com “Mapeamento Profissional x Convênio não configurado”."
          >
            <select
              id="de-para-profissional"
              value={form.profissional_id}
              onChange={(event) =>
                setForm((atual) => ({ ...atual, profissional_id: event.target.value }))
              }
              className={inputClasses()}
              data-testid="de-para-profissional"
            >
              <option value="">Selecione…</option>
              {profissionais.map((profissional) => (
                <option key={profissional.id} value={profissional.id}>
                  {profissional.nome}
                </option>
              ))}
            </select>
          </Campo>

          <Campo
            htmlFor="de-para-codigo-operadora"
            rotulo="Código na operadora"
            dicaTitulo="Código na operadora"
            dica="O número que identifica o profissional no cadastro DESTE convênio — não é o conselho (CRP, CREFITO). É o que a automação preenche no campo de executante do portal."
          >
            <input
              id="de-para-codigo-operadora"
              value={form.codigo_operadora}
              onChange={(event) =>
                setForm((atual) => ({ ...atual, codigo_operadora: event.target.value }))
              }
              className={inputClasses()}
              data-testid="de-para-codigo-operadora"
            />
          </Campo>

          <Campo
            htmlFor="de-para-nome-operadora"
            rotulo="Nome na operadora (opcional)"
            dicaTitulo="Nome na operadora"
            dica="Preencha só quando o nome no cadastro da operadora for diferente do nome na clínica — casos de nome de solteira, abreviação ou grafia antiga. Vazio significa que são iguais."
          >
            <input
              id="de-para-nome-operadora"
              value={form.nome_operadora}
              onChange={(event) =>
                setForm((atual) => ({ ...atual, nome_operadora: event.target.value }))
              }
              className={inputClasses()}
              data-testid="de-para-nome-operadora"
            />
          </Campo>

          <div className="flex flex-wrap gap-3">
            <Botao
              type="submit"
              disabled={!form.profissional_id || !form.codigo_operadora}
              carregando={salvar.isPending}
              data-testid="de-para-profissional-salvar"
            >
              {editandoId ? 'Salvar alteração' : 'Acrescentar'}
            </Botao>
            <Botao
              type="button"
              variante="secundario"
              onClick={fechar}
              data-testid="de-para-profissional-cancelar"
            >
              Cancelar
            </Botao>
          </div>
        </form>
      ) : null}
    </div>
  )
}
