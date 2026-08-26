import { useEffect, useRef, useState, type FormEvent } from 'react'
import { ColunaOrdenavel } from '../../components/ui/ColunaOrdenavel'
import { useOrdenacao } from '../../lib/useOrdenacao'
import { useMatch, useNavigate } from 'react-router-dom'
import { getHttpErrorMessage, useAtualizarCid, useCriarCid, useCidsCrud } from './useCids'
import type { Cid, CidForm } from './types'
import { Indicadores } from '../../components/ui/Indicadores'
import { Botao } from '../../components/ui/Botao'
import { Badge, type BadgeProps } from '../../components/ui/Badge'

const emptyForm: CidForm = {
  codigo: '',
  descricao: '',
  ativo: true,
}

type StatusFiltro = 'todos' | 'ativos' | 'inativos'

function fieldClasses() {
  return 'w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20'
}

function statusTone(ativo: boolean): NonNullable<BadgeProps['tone']> {
  return ativo ? 'sucesso' : 'perigo'
}

function toForm(cid: Cid): CidForm {
  return {
    codigo: cid.codigo,
    descricao: cid.descricao,
    ativo: cid.ativo,
  }
}

export function CidsPage() {
  const navigate = useNavigate()
  const isCreateRoute = useMatch('/cids/novo') !== null
  const editRouteMatch = useMatch('/cids/:id/editar')
  const editingId = editRouteMatch ? Number(editRouteMatch.params.id) : null
  const isEditRoute = editingId !== null && Number.isInteger(editingId)
  const isFormRoute = isCreateRoute || isEditRoute
  const [busca, setBusca] = useState('')
  const [draftBusca, setDraftBusca] = useState('')
  const [statusFiltro, setStatusFiltro] = useState<StatusFiltro>('todos')
  const [draftStatusFiltro, setDraftStatusFiltro] = useState<StatusFiltro>('todos')
  const [form, setForm] = useState<CidForm>(emptyForm)
  const [formError, setFormError] = useState<string | null>(null)
  const carregadoRef = useRef<number | null>(null)

  const { ordenacao, ordenarPor } = useOrdenacao({
    ordenar_por: 'codigo',
    direcao: 'asc',
  })

  const cidsQuery = useCidsCrud(busca, ordenacao)
  const criarCid = useCriarCid()
  const atualizarCid = useAtualizarCid()

  const cids = cidsQuery.data ?? []
  const cidsFiltrados =
    statusFiltro === 'ativos'
      ? cids.filter((cid) => cid.ativo)
      : statusFiltro === 'inativos'
        ? cids.filter((cid) => !cid.ativo)
        : cids
  const totalAtivos = cids.filter((cid) => cid.ativo).length
  const totalInativos = cids.length - totalAtivos
  const cidEmEdicao = isEditRoute ? cids.find((cid) => cid.id === editingId) ?? null : null

  // Carrega o registro no formulário uma única vez por id: evita que um refetch
  // em background sobrescreva o que o usuário já digitou.
  useEffect(() => {
    if (!isEditRoute) {
      carregadoRef.current = null
      return
    }

    if (!cidEmEdicao || carregadoRef.current === cidEmEdicao.id) {
      return
    }

    carregadoRef.current = cidEmEdicao.id
    setForm(toForm(cidEmEdicao))
    setFormError(null)
  }, [isEditRoute, cidEmEdicao])

  const handleFilterSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setBusca(draftBusca.trim())
    setStatusFiltro(draftStatusFiltro)
  }

  const handleNew = () => {
    setForm(emptyForm)
    setFormError(null)
    carregadoRef.current = null
    navigate('/cids/novo')
  }

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setFormError(null)

    try {
      const payload = { codigo: form.codigo.trim(), descricao: form.descricao.trim(), ativo: form.ativo }

      if (isEditRoute && editingId) {
        await atualizarCid.mutateAsync({ id: editingId, payload })
      } else {
        await criarCid.mutateAsync(payload)
      }

      setForm(emptyForm)
      carregadoRef.current = null
      navigate('/cids')
    } catch (error) {
      setFormError(getHttpErrorMessage(error, 'Não foi possível salvar o CID.'))
    }
  }

  const handleEdit = (cid: Cid) => {
    setFormError(null)
    navigate(`/cids/${cid.id}/editar`)
  }

  const handleToggleAtivo = async (cid: Cid) => {
    setFormError(null)

    try {
      await atualizarCid.mutateAsync({ id: cid.id, payload: { ativo: !cid.ativo } })
    } catch (error) {
      setFormError(getHttpErrorMessage(error, 'Não foi possível atualizar o status do CID.'))
    }
  }

  const handleCancel = () => {
    setForm(emptyForm)
    setFormError(null)
    carregadoRef.current = null
    navigate('/cids')
  }

  return (
    <div className="space-y-8" data-testid="cids-page">
      {!isFormRoute ? (
        <section className="space-y-4">
          <div className="flex flex-col gap-4 sm:items-start lg:flex-row lg:items-end lg:justify-between">
            <div>
              <p className="text-meta uppercase tracking-[0.3em] text-cyan-300/80">CIDs</p>
              <h2 className="mt-2 text-display font-semibold text-white">
                Códigos CID-10 usados nas solicitações
              </h2>
            </div>

            <div className="flex items-center gap-3">
              <Botao type="button" onClick={handleNew} data-testid="cid-novo">
                Novo CID
              </Botao>
            </div>
          </div>

          <Indicadores
            itens={[
              { rotulo: 'Total', valor: cids.length },
              { rotulo: 'Ativos', valor: totalAtivos },
              { rotulo: 'Inativos', valor: totalInativos },
            ]}
          />
        </section>
      ) : null}

      {isFormRoute && (!isEditRoute || cidEmEdicao) ? (
        <section className="rounded-janela border border-linha bg-superficie-elevada shadow-e2 p-6">
          <form onSubmit={handleSubmit} className="space-y-4" data-testid="cid-form">
            <div className="flex items-start justify-between gap-4">
              <div>
                <h3 className="text-subtitulo font-semibold text-white">
                  {editingId ? 'Editar CID' : 'Novo CID'}
                </h3>
              </div>
              {editingId ? (
                <span className="rounded-full border border-cyan-300/20 bg-cyan-400/10 px-3 py-1 text-meta font-semibold text-cyan-100">
                  Editando #{editingId}
                </span>
              ) : null}
            </div>

            <label className="block space-y-2">
              <span className="text-corpo font-medium text-slate-200">Código</span>
              <input
                value={form.codigo}
                onChange={(event) => setForm((current) => ({ ...current, codigo: event.target.value }))}
                className={fieldClasses()}
                placeholder="F84.0"
                required
                data-testid="cid-codigo"
              />
            </label>

            <label className="block space-y-2">
              <span className="text-corpo font-medium text-slate-200">Descrição</span>
              <input
                value={form.descricao}
                onChange={(event) => setForm((current) => ({ ...current, descricao: event.target.value }))}
                className={fieldClasses()}
                placeholder="Autismo infantil"
                required
                data-testid="cid-descricao"
              />
            </label>

            <label className="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
              <input
                type="checkbox"
                checked={form.ativo}
                onChange={(event) => setForm((current) => ({ ...current, ativo: event.target.checked }))}
                className="h-4 w-4 rounded border-white/20 bg-white/10 text-cyan-300 focus:ring-cyan-300/20"
                data-testid="cid-ativo"
              />
              <span className="text-corpo font-medium text-slate-200">Ativo</span>
            </label>

            {formError ? (
              <p className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-corpo text-rose-100">
                {formError}
              </p>
            ) : null}

            <div className="flex gap-3">
              <Botao
                type="submit"
                className="flex-1"
                carregando={criarCid.isPending || atualizarCid.isPending}
                data-testid="cid-submit"
              >
                {editingId
                  ? atualizarCid.isPending
                    ? 'Salvando...'
                    : 'Salvar alterações'
                  : criarCid.isPending
                    ? 'Salvando...'
                    : 'Criar CID'}
              </Botao>
              {editingId ? (
                <Botao type="button" variante="secundario" onClick={handleCancel} data-testid="cid-cancelar">
                  Cancelar
                </Botao>
              ) : (
                <Botao type="button" variante="secundario" onClick={handleCancel} data-testid="cid-fechar">
                  Fechar
                </Botao>
              )}
            </div>
          </form>
        </section>
      ) : null}

      {isEditRoute && !cidEmEdicao ? (
        <section
          className="rounded-janela border border-linha bg-superficie-elevada shadow-e2 p-6"
          data-testid="cid-edicao-indisponivel"
        >
          {cidsQuery.isLoading ? (
            <p className="inline-flex min-h-6 items-center text-corpo text-slate-300">Carregando CID...</p>
          ) : (
            <div className="space-y-4">
              <p className="text-corpo text-rose-100">
                CID não encontrado. Ele pode ter sido removido ou o endereço está incorreto.
              </p>
              <Botao type="button" variante="secundario" onClick={handleCancel} data-testid="cid-voltar">
                Voltar para a lista
              </Botao>
            </div>
          )}
        </section>
      ) : null}

      {!isFormRoute ? (
        <section className="space-y-4 rounded-janela border border-linha bg-superficie-elevada shadow-e2 p-6">
          <div className="flex flex-col gap-4 sm:items-start lg:flex-row lg:items-end lg:justify-between">
            <form className="flex flex-wrap gap-3" onSubmit={handleFilterSubmit}>
              <label className="min-w-56 space-y-2">
                <span className="text-meta uppercase tracking-[0.25em] text-slate-400">Busca</span>
                <input
                  value={draftBusca}
                  onChange={(event) => setDraftBusca(event.target.value)}
                  className={fieldClasses()}
                  placeholder="Código ou descrição"
                  data-testid="cid-busca"
                />
              </label>
              <label className="min-w-48 space-y-2">
                <span className="text-meta uppercase tracking-[0.25em] text-slate-400">Status</span>
                <select
                  value={draftStatusFiltro}
                  onChange={(event) => setDraftStatusFiltro(event.target.value as StatusFiltro)}
                  className={fieldClasses()}
                  data-testid="cid-status-filtro"
                >
                  <option value="todos">Todos</option>
                  <option value="ativos">Ativos</option>
                  <option value="inativos">Inativos</option>
                </select>
              </label>
              <Botao type="submit" variante="secundario" data-testid="cid-busca-submit">
                Filtrar
              </Botao>
            </form>
          </div>

          {cidsQuery.isLoading ? (
            <div className="rounded-superficie border border-linha bg-fundo p-4 shadow-e1 text-corpo text-slate-300">
              Carregando CIDs...
            </div>
          ) : cidsQuery.isError ? (
            <div className="rounded-2xl border border-rose-400/20 bg-rose-500/10 p-4 text-corpo text-rose-100">
              Não foi possível carregar os CIDs.
            </div>
          ) : (
            <div className="overflow-x-auto rounded-superficie border border-linha">
              <table className="w-full border-collapse text-left text-corpo" data-cartoes="md">
                <thead className="bg-fundo text-meta uppercase tracking-[0.25em] text-texto-suave">
                  <tr>
                    <ColunaOrdenavel
                      titulo="Código"
                      coluna="codigo"
                      ordenacao={ordenacao}
                      onOrdenar={ordenarPor}
                      className="px-4 py-3"
                    />
                    <ColunaOrdenavel
                      titulo="Descrição"
                      coluna="descricao"
                      ordenacao={ordenacao}
                      onOrdenar={ordenarPor}
                      className="w-full px-4 py-3"
                    />
                    <ColunaOrdenavel
                      titulo="Status"
                      coluna="status"
                      ordenacao={ordenacao}
                      onOrdenar={ordenarPor}
                    />
                    <ColunaOrdenavel titulo="Ações" className="w-px px-4 py-3" />
                  </tr>
                </thead>
                <tbody className="divide-y divide-linha bg-superficie">
                  {cidsFiltrados.map((cid) => (
                    <tr key={cid.id} data-testid={`cid-row-${cid.id}`}>
                      <td data-rotulo="Código" className="px-4 py-4 font-medium text-slate-100">{cid.codigo}</td>
                      <td data-rotulo="Descrição" data-rotulo-bloco className="px-4 py-4 text-slate-100">{cid.descricao}</td>
                      <td data-rotulo="Status" className="px-4 py-4">
                        <Badge tone={statusTone(cid.ativo)} data-testid={`cid-status-${cid.id}`}>
                          {cid.ativo ? 'Ativo' : 'Inativo'}
                        </Badge>
                      </td>
                      <td data-rotulo="Ações" data-rotulo-bloco className="w-px whitespace-nowrap px-4 py-4">
                        <div className="flex flex-nowrap gap-2">
                          <button
                            type="button"
                            onClick={() => handleEdit(cid)}
                            className="rounded-full border border-cyan-300/30 bg-cyan-400/10 px-3 py-1.5 text-meta font-semibold text-cyan-100 transition hover:bg-cyan-400/20"
                            data-testid={`cid-editar-${cid.id}`}
                          >
                            Editar
                          </button>
                          <button
                            type="button"
                            onClick={() => handleToggleAtivo(cid)}
                            className="rounded-full border border-white/10 bg-white/5 px-3 py-1.5 text-meta font-semibold text-white transition hover:bg-white/10 disabled:opacity-60"
                            disabled={atualizarCid.isPending}
                            data-testid={`cid-toggle-${cid.id}`}
                          >
                            {cid.ativo ? 'Inativar' : 'Reativar'}
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                  {cidsFiltrados.length === 0 ? (
                    <tr>
                      <td colSpan={4} className="px-4 py-8 text-center text-slate-300">
                        Nenhum CID encontrado.
                      </td>
                    </tr>
                  ) : null}
                </tbody>
              </table>
            </div>
          )}
        </section>
      ) : null}
    </div>
  )
}
