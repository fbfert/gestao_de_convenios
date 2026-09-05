<?php

namespace App\Services\ClinicaSync;

use App\Models\ClinicaPacientePendente;
use App\Models\Convenio;
use App\Models\Paciente;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Sincroniza pacientes entre gescon e clinica — via mão dupla (design
 * confirmado em 20/08/2026, ver docs/clinica-sync.md e ProfissionalSyncService
 * pro mecanismo de anti-loop, idêntico aqui).
 *
 * Os dois cadastros de paciente têm propósitos diferentes: o gescon é
 * centrado em convênio/carteirinha (billing), o clinica é centrado em
 * prontuário clínico (responsável legal, necessidade, consentimento LGPD).
 * Só o subconjunto que EXISTE nos dois lados sincroniza: nome, cpf,
 * nascimento, telefone (como contato), ativo. O resto é exclusivo de cada
 * sistema e nunca é sobrescrito pelo outro lado.
 */
class PacienteSyncService
{
    private const TIPOS_TELEFONE = ['telefone', 'celular', 'whatsapp'];

    /** Mesmo corte de PedidoMedicoAiService/CarteirinhaAiService: abaixo disso não propomos vínculo. */
    private const CONFIANCA_MINIMA = 90;

    public function __construct(
        private readonly ClinicaApiClient $api,
        private readonly int $tenantId,
    ) {}

    public function executar(): array
    {
        $pull = $this->pull();
        $push = $this->push();

        return ['pull' => $pull, 'push' => $push];
    }

    private function pull(): array
    {
        $resumo = ['criados' => 0, 'atualizados' => 0, 'ignorados' => 0, 'pendentes' => []];

        $pagina = 1;
        $ultimaPagina = 1;

        do {
            $resposta = $this->api->listarPacientesPagina($pagina);
            $itens = $resposta['data'] ?? $resposta;
            $ultimaPagina = $resposta['meta']['last_page'] ?? 1;

            foreach ($itens as $resumido) {
                $this->pullUm((int) $resumido['id'], $resumo);
            }

            $pagina++;
        } while ($pagina <= $ultimaPagina);

        return $resumo;
    }

    private function pullUm(int $clinicaId, array &$resumo): void
    {
        // Índice não traz cpf/updated_at (T-pac-01, privacidade) — o detalhe é a
        // única fonte confiável. Em escala de uma clínica pequena isso é aceitável;
        // pra centenas de pacientes valeria um filtro `updated_since` no clinica.
        $remoto = $this->api->buscarPaciente($clinicaId);
        $remotoAtualizadoEm = CarbonImmutable::parse($remoto['updated_at']);

        $local = Paciente::where('tenant_id', $this->tenantId)->where('clinica_id', $clinicaId)->first();

        if ($local === null && ! empty($remoto['cpf'])) {
            $local = Paciente::where('tenant_id', $this->tenantId)
                ->whereNull('clinica_id')
                ->where('cpf', $this->somenteDigitos($remoto['cpf']))
                ->first();
        }

        if ($local !== null && $local->sincronizado_em !== null && $local->sincronizado_em->eq($remotoAtualizadoEm)) {
            $resumo['ignorados']++;

            return; // já sincronizado com essa mesma versão — nada a fazer
        }

        if ($local !== null && $local->updated_at !== null && $local->updated_at->gt($remotoAtualizadoEm)) {
            $resumo['ignorados']++;

            return; // edição local mais recente vence por ora; sai no push

        }

        if ($local === null && $this->interceptarSemMatchExato($clinicaId, $remoto, $remotoAtualizadoEm, $resumo)) {
            return;
        }

        $particular = Convenio::where('tenant_id', $this->tenantId)->where('nome', 'Particular')->first();

        if ($local === null && $particular === null) {
            $resumo['pendentes'][] = "Paciente '{$remoto['nome']}' (clinica_id={$clinicaId}): sem convênio 'Particular' cadastrado no gescon para servir de destino — configure um convênio com esse nome exato.";

            return;
        }

        $dados = [
            'nome' => $remoto['nome'],
            'cpf' => $remoto['cpf'] !== null ? $this->somenteDigitos($remoto['cpf']) : null,
            'data_nascimento' => $remoto['nascimento'] ?? null,
            'telefone' => $this->extrairTelefone($remoto['contatos_json'] ?? []),
            'ativo' => (bool) $remoto['ativo'],
        ];

        if ($local !== null) {
            $local->timestamps = false;
            $local->forceFill([
                ...$dados,
                'clinica_id' => $clinicaId,
                'updated_at' => $remotoAtualizadoEm,
                'sincronizado_em' => $remotoAtualizadoEm,
                'clinica_status' => null,
            ])->save();

            $resumo['atualizados']++;

            return;
        }

        $novo = new Paciente([
            ...$dados,
            'tenant_id' => $this->tenantId,
            // Paciente nascido no clinica não tem convênio — "Particular" é o
            // destino honesto até alguém da recepção associar o convênio real.
            'convenio_id' => $particular->id,
            'carteirinha' => 'SYNC-CLINICA-'.$clinicaId,
        ]);
        $novo->clinica_id = $clinicaId;
        $novo->timestamps = false;
        $novo->created_at = $remotoAtualizadoEm;
        $novo->updated_at = $remotoAtualizadoEm;
        $novo->sincronizado_em = $remotoAtualizadoEm;
        $novo->save();

        $resumo['criados']++;
    }

    /**
     * Sem clinica_id nem CPF batendo, não decide sozinho: se já existe uma
     * pendência aberta pra esse clinica_id, só atualiza o snapshot (evita
     * duplicar a pendência a cada rodada de 5 min). Se não existe, procura
     * candidato por nome parecido entre pacientes locais sem vínculo — se
     * achar, abre pendência pra humano confirmar em vez de criar Paciente
     * novo (foi assim que Abner virou dois cadastros: nome bateu ~90% mas
     * criamos sozinho). Só devolve false (segue criação normal) quando não
     * há pendência pendente nem candidato plausível.
     */
    private function interceptarSemMatchExato(int $clinicaId, array $remoto, CarbonImmutable $remotoAtualizadoEm, array &$resumo): bool
    {
        $pendencia = ClinicaPacientePendente::where('tenant_id', $this->tenantId)
            ->where('clinica_id', $clinicaId)
            ->first();

        if ($pendencia !== null && $pendencia->status === 'pendente') {
            $pendencia->forceFill([
                'dados_remoto' => $remoto,
                'remoto_atualizado_em' => $remotoAtualizadoEm,
            ])->save();

            $resumo['pendentes'][] = "Paciente '{$remoto['nome']}' (clinica_id={$clinicaId}): aguardando confirmação manual de vínculo em Configurações > Sincronização Clínica.";

            return true;
        }

        if ($pendencia !== null) {
            // já resolvida (confirmado deveria ter clinica_id setado no local,
            // então não devia cair aqui; rejeitado = "é gente diferente mesmo") — segue criação normal.
            return false;
        }

        $candidatos = $this->buscarCandidatos($remoto['nome']);

        if ($candidatos === []) {
            return false;
        }

        ClinicaPacientePendente::create([
            'tenant_id' => $this->tenantId,
            'clinica_id' => $clinicaId,
            'dados_remoto' => $remoto,
            'remoto_atualizado_em' => $remotoAtualizadoEm,
            'status' => 'pendente',
            'candidato_paciente_id' => $candidatos[0]['id'],
            'similaridade' => (int) round($candidatos[0]['similaridade']),
            'candidatos_json' => $candidatos,
        ]);

        $resumo['pendentes'][] = "Paciente '{$remoto['nome']}' (clinica_id={$clinicaId}): ".count($candidatos)." candidato(s) parecido(s) já cadastrado(s) no gescon — revisar em Configurações > Sincronização Clínica.";

        return true;
    }

    /**
     * Pacientes locais sem vínculo (clinica_id nulo) com nome ≥90% parecido,
     * mesmo idioma de PedidoMedicoAiService::rankByNome / CarteirinhaAiService.
     *
     * @return array<int, array{id: int, similaridade: float}>
     */
    private function buscarCandidatos(string $nome): array
    {
        $needle = mb_strtolower($this->semAcento(trim($nome)));

        return Paciente::where('tenant_id', $this->tenantId)
            ->whereNull('clinica_id')
            ->get(['id', 'nome'])
            ->map(function (Paciente $paciente) use ($needle) {
                similar_text($needle, mb_strtolower($this->semAcento($paciente->nome)), $percent);

                return ['id' => $paciente->id, 'similaridade' => round($percent, 1)];
            })
            ->filter(fn (array $item) => $item['similaridade'] >= self::CONFIANCA_MINIMA)
            ->sortByDesc('similaridade')
            ->take(5)
            ->values()
            ->all();
    }

    private function semAcento(string $valor): string
    {
        return iconv('UTF-8', 'ASCII//TRANSLIT', $valor) ?: $valor;
    }

    private function push(): array
    {
        $resumo = ['criados' => 0, 'atualizados' => 0, 'pendentes' => []];

        $pendentesDePush = Paciente::where('tenant_id', $this->tenantId)
            ->where(function ($q) {
                $q->whereNull('sincronizado_em')->orWhereColumn('updated_at', '>', 'sincronizado_em');
            })
            ->get();

        foreach ($pendentesDePush as $paciente) {
            $agora = CarbonImmutable::now();

            try {
                if ($paciente->clinica_id === null) {
                    $this->criar($paciente, $agora, $resumo);
                } else {
                    $this->atualizar($paciente, $agora, $resumo);
                }
            } catch (\Throwable $e) {
                if (ClinicaApiClient::eConflito($e)) {
                    $resumo['pendentes'][] = "Paciente '{$paciente->nome}' (id={$paciente->id}): conflito de edição concorrente no clinica — será tentado de novo na próxima sync.";

                    continue;
                }

                $resumo['pendentes'][] = "Paciente '{$paciente->nome}' (id={$paciente->id}): falha ao enviar pro clinica — ".$e->getMessage();
            }
        }

        return $resumo;
    }

    /**
     * Paciente NOVO pro clinica exige nascimento + necessidade (classificação clínica
     * que o gescon simplesmente não coleta — é billing, não prontuário). Nunca
     * inventamos esse dado: marcamos pendente e quem completar o cadastro direto no
     * clinica resolve — a próxima pull enxerga e vincula pelo CPF.
     */
    private function criar(Paciente $paciente, CarbonImmutable $agora, array &$resumo): void
    {
        if ($paciente->data_nascimento === null) {
            $this->marcarPendente($paciente, $agora, 'sem data de nascimento (obrigatória no clinica)');
            $resumo['pendentes'][] = "Paciente '{$paciente->nome}' (id={$paciente->id}): sem nascimento, não enviado.";

            return;
        }

        $this->marcarPendente(
            $paciente,
            $agora,
            'necessidade (classificação clínica) não existe no gescon — cadastro precisa ser completado direto no clinica',
        );
        $resumo['pendentes'][] = "Paciente '{$paciente->nome}' (id={$paciente->id}): precisa ser completado manualmente no clinica (necessidade/responsável são exclusivos de lá).";
    }

    private function marcarPendente(Paciente $paciente, CarbonImmutable $agora, string $motivo): void
    {
        $paciente->timestamps = false;
        $paciente->forceFill([
            'clinica_status' => 'pendente_manual: '.$motivo,
            'sincronizado_em' => $agora,
            'updated_at' => $agora,
        ])->save();
    }

    /**
     * Atualização: o clinica exige o corpo INTEIRO a cada PATCH (nascimento/sexo/
     * necessidade/responsaveis são sempre obrigatórios, não só na criação) — por
     * isso busca o registro atual e reenvia os campos que não são do gescon
     * inalterados, sobrescrevendo só nome/cpf/nascimento/telefone/ativo.
     */
    private function atualizar(Paciente $paciente, CarbonImmutable $agora, array &$resumo): void
    {
        $atual = $this->api->buscarPaciente($paciente->clinica_id);

        $contatos = $this->mesclarTelefone($atual['contatos_json'] ?? [], $paciente->telefone);

        $payload = [
            'nome' => $paciente->nome,
            'nome_social' => $atual['nome_social'] ?? null,
            'nascimento' => $paciente->data_nascimento?->toDateString()
                ?? CarbonImmutable::parse($atual['nascimento'])->toDateString(),
            'sexo' => $atual['sexo'],
            'cpf' => $paciente->cpf !== null ? $this->somenteDigitos($paciente->cpf) : null,
            'indicacao' => $atual['indicacao'] ?? null,
            'necessidade' => $atual['necessidade'],
            'estado_civil' => $atual['estado_civil'] ?? null,
            'profissao' => $atual['profissao'] ?? null,
            'obs' => $atual['obs'] ?? null,
            'ativo' => $paciente->ativo,
            'responsaveis_json' => $atual['responsaveis_json'] ?? [],
            'contatos_json' => $contatos,
            'endereco_json' => $atual['endereco_json'] ?? null,
            // consentimentos_json OMITIDO de propósito: reenviar o que o show()
            // devolveu bateria nas regras `missing` (aceito_em/termo/etc são
            // carimbados pelo servidor, não podem voltar no payload).
        ];

        $this->api->atualizarPaciente($paciente->clinica_id, $payload, CarbonImmutable::parse($atual['updated_at'])->toJSON());

        $paciente->timestamps = false;
        $paciente->forceFill(['sincronizado_em' => $agora, 'updated_at' => $agora, 'clinica_status' => null])->save();
        $resumo['atualizados']++;
    }

    private function extrairTelefone(array $contatosJson): ?string
    {
        foreach ($contatosJson as $contato) {
            if (in_array($contato['tipo'] ?? null, self::TIPOS_TELEFONE, true)) {
                return $contato['valor'] ?? null;
            }
        }

        return null;
    }

    /** Substitui (ou insere) a entrada 'telefone', preservando celular/whatsapp/email já cadastrados no clinica. */
    private function mesclarTelefone(array $contatosAtuais, ?string $telefone): array
    {
        $semTelefone = array_values(array_filter(
            $contatosAtuais,
            fn ($c) => ($c['tipo'] ?? null) !== 'telefone',
        ));

        if ($telefone === null || $telefone === '') {
            return $semTelefone;
        }

        return [...$semTelefone, ['tipo' => 'telefone', 'valor' => $telefone]];
    }

    private function somenteDigitos(string $valor): string
    {
        return preg_replace('/\D/', '', $valor) ?? '';
    }
}
