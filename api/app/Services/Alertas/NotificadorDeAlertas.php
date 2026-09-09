<?php

namespace App\Services\Alertas;

use App\Models\Alerta;
use App\Models\AlertaDestinatario;
use App\Models\AlertaDestinatarioGlobal;
use App\Models\AlertaRegra;
use App\Models\EmailSmtpSetting;
use App\Models\EmailTemplate;
use App\Mail\AlertaEmail;
use App\Models\Tenant;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Manda o e-mail dos alertas.
 *
 * PRIMEIRO consumidor de `email_templates` — a tabela tinha CRUD completo e
 * nenhum codigo que a lesse. Modelo ausente cai em texto padrao daqui: uma
 * notificacao que nao sai por falta de modelo e falha silenciosa.
 */
class NotificadorDeAlertas
{
    /** Chaves dos modelos que esta fase consome, alem da chave de cada regra. */
    public const TEMPLATE_DIGEST = 'alertas.digest_diario';

    public const TEMPLATE_IMEDIATO = 'alertas.imediato';

    /** Aberta pelo proprio notificador — ver a nota no design.md. */
    public const CHAVE_DESTINATARIO_FALHANDO = 'email.destinatario_falhando';

    /**
     * Aviso imediato de um alerta recem-aberto.
     *
     * So vermelho, so regra critica, e so fora da janela de silencio. O
     * agrupamento ("N falhas viram 1 e-mail") sai de graca da deduplicacao: como
     * so existe um alerta aberto por chave e entidade, N ocorrencias ja sao um
     * alerta — e portanto um e-mail.
     */
    public function notificarImediato(Alerta $alerta, AlertaRegra $regra): bool
    {
        if ($alerta->nivel !== Alerta::NIVEL_VERMELHO || ! $regra->critica) {
            return false;
        }

        $janela = max(1, (int) ($regra->janela_silencio_horas ?: 24));

        if ($alerta->notificado_em && $alerta->notificado_em->gt(now()->subHours($janela))) {
            return false;
        }

        $modelo = $this->modelo($alerta->tenant_id, $alerta->chave)
            ?? $this->modelo($alerta->tenant_id, self::TEMPLATE_IMEDIATO);

        $assunto = $modelo?->assunto ?: "[Alerta] {$alerta->titulo}";
        $corpo = $modelo?->corpo ?: $this->corpoImediatoPadrao($alerta);

        $destinatarios = $this->destinatariosDoTenant($alerta->tenant_id)
            ->filter(fn (AlertaDestinatario $d) => $d->aceitaCanal(AlertaDestinatario::CANAL_IMEDIATO)
                && $d->querReceber($alerta->nivel, $alerta->chave));

        $enviou = false;

        foreach ($destinatarios as $destinatario) {
            $enviou = $this->enviar($destinatario, $assunto, $corpo, $alerta->tenant_id) || $enviou;
        }

        // Carimba mesmo sem destinatário: a janela de silêncio é do alerta, e
        // não da caixa de entrada de alguém.
        $alerta->forceFill(['notificado_em' => now()])->save();

        return $enviou;
    }

    /**
     * Digest do tenant, para quem tem `horario_digest` nesta hora.
     *
     * @return int quantos e-mails sairam
     */
    public function enviarDigestDoTenant(int $tenantId, int $hora): int
    {
        $alertas = $this->alertasAbertos($tenantId);
        $enviados = 0;

        foreach ($this->destinatariosDoTenant($tenantId) as $destinatario) {
            if ($destinatario->horario_digest !== $hora
                || ! $destinatario->aceitaCanal(AlertaDestinatario::CANAL_DIGEST)) {
                continue;
            }

            $doDestinatario = $alertas->filter(
                fn (Alerta $a) => $destinatario->querReceber($a->nivel, $a->chave)
            );

            // Digest vazio nao sai: um e-mail diario dizendo "nada aqui" treina
            // a pessoa a arquivar sem ler, e no dia em que houver algo ela
            // arquiva tambem.
            if ($doDestinatario->isEmpty()) {
                continue;
            }

            $modelo = $this->modelo($tenantId, self::TEMPLATE_DIGEST);
            $assunto = $modelo?->assunto ?: 'Resumo de alertas — Gestão de Convênios';
            $corpo = $modelo?->corpo ?: $this->corpoDigestPadrao($doDestinatario->all());

            if ($this->enviar($destinatario, $assunto, $corpo, $tenantId)) {
                $enviados++;
            }
        }

        return $enviados;
    }

    /**
     * Digest do suporte: UM e-mail com todos os tenants agrupados.
     *
     * Sai pelo mailer da aplicacao, e nao pelo SMTP de nenhum tenant — um dos
     * alertas que o suporte precisa receber e justamente "o SMTP do tenant esta
     * falhando", e manda-lo pelo canal quebrado seria garantir que nunca chegue.
     */
    public function enviarDigestGlobal(int $hora): int
    {
        $globais = AlertaDestinatarioGlobal::query()
            ->where('ativo', true)
            ->where('horario_digest', $hora)
            ->get();

        if ($globais->isEmpty()) {
            return 0;
        }

        $porTenant = [];

        foreach (Tenant::query()->orderBy('id')->get() as $tenant) {
            $abertos = $this->alertasAbertos((int) $tenant->id);

            if ($abertos->isNotEmpty()) {
                $porTenant[$tenant->nome] = $abertos;
            }
        }

        $enviados = 0;

        foreach ($globais as $destinatario) {
            if (! $destinatario->aceitaCanal(AlertaDestinatario::CANAL_DIGEST)) {
                continue;
            }

            $filtrado = [];

            foreach ($porTenant as $nomeTenant => $alertas) {
                $doDestinatario = $alertas->filter(
                    fn (Alerta $a) => $destinatario->querReceber($a->nivel, $a->chave)
                );

                if ($doDestinatario->isNotEmpty()) {
                    $filtrado[$nomeTenant] = $doDestinatario->all();
                }
            }

            if ($filtrado === []) {
                continue;
            }

            if ($this->enviar(
                $destinatario,
                'Resumo de alertas — todas as clínicas',
                $this->corpoDigestGlobalPadrao($filtrado),
                null,
            )) {
                $enviados++;
            }
        }

        return $enviados;
    }

    /** Alertas que interessam a notificação: abertos, não silenciados, sem verde. */
    private function alertasAbertos(int $tenantId)
    {
        return Alerta::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->pendente()
            ->whereIn('nivel', Alerta::NIVEIS_DO_CARD)
            ->orderByDesc('aberto_em')
            ->get();
    }

    private function destinatariosDoTenant(int $tenantId)
    {
        return AlertaDestinatario::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->where('ativo', true)
            ->get();
    }

    private function modelo(int $tenantId, string $chave): ?EmailTemplate
    {
        return EmailTemplate::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('chave', $chave)
            ->where('ativo', true)
            ->first();
    }

    /**
     * Envio de fato, com a higiene de destinatário.
     *
     * Bounce derruba a reputacao do SMTP e ninguem fica sabendo — por isso a
     * falha e contada, e o destinatario e desativado ao passar do limite, com um
     * alerta que aparece na central.
     */
    private function enviar(Model $destinatario, string $assunto, string $corpo, ?int $tenantId): bool
    {
        try {
            $mailer = $tenantId !== null ? $this->mailerDoTenant($tenantId) : Mail::mailer();

            $mailer->to($destinatario->email, $destinatario->nome ?: null)
                ->send(new AlertaEmail($assunto, $corpo));
        } catch (Throwable $erro) {
            $this->registrarFalha($destinatario, $tenantId, $erro->getMessage());

            return false;
        }

        $destinatario->forceFill([
            'falhas_consecutivas' => 0,
            // Verificado por envio bem-sucedido, e nao por link de confirmacao:
            // e mais fraco que double opt-in e resolve o objetivo real aqui, que
            // e distinguir endereco digitado errado de endereco que funciona.
            'verificado_em' => $destinatario->verificado_em ?? now(),
        ])->save();

        return true;
    }

    private function mailerDoTenant(int $tenantId)
    {
        $smtp = EmailSmtpSetting::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $smtp || blank($smtp->host) || ! $smtp->ativo) {
            // Sem SMTP proprio configurado, cai no mailer da aplicacao: e
            // melhor sair pelo remetente da Xiax do que nao sair.
            return Mail::mailer();
        }

        $nome = "smtp_tenant_{$tenantId}";

        config(["mail.mailers.{$nome}" => [
            'transport' => 'smtp',
            'host' => $smtp->host,
            'port' => $smtp->port,
            'encryption' => $smtp->encryption ?: null,
            'username' => $smtp->username ?: null,
            'password' => $smtp->password ?: null,
            'timeout' => 15,
        ]]);

        return Mail::mailer($nome);
    }

    private function registrarFalha(Model $destinatario, ?int $tenantId, string $mensagem): void
    {
        $falhas = (int) $destinatario->falhas_consecutivas + 1;
        $limite = AlertaDestinatario::LIMITE_FALHAS;
        $desativar = $falhas >= $limite;

        $destinatario->forceFill([
            'falhas_consecutivas' => $falhas,
            'ativo' => ! $desativar,
        ])->save();

        if (! $desativar || $tenantId === null) {
            return;
        }

        Alerta::query()->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'chave' => self::CHAVE_DESTINATARIO_FALHANDO,
                'entidade' => 'alerta_destinatarios',
                'entidade_id' => (int) $destinatario->id,
                'aberto_dedupe' => Alerta::DEDUPE_ABERTO,
            ],
            [
                'nivel' => Alerta::NIVEL_AMARELO,
                'titulo' => "Destinatário desativado — {$destinatario->email}",
                'descricao' => "{$falhas} falhas de envio consecutivas · {$mensagem}",
                'aberto_em' => now(),
            ],
        );
    }

    private function corpoImediatoPadrao(Alerta $alerta): string
    {
        return "{$alerta->titulo}\n\n"
            .($alerta->descricao ? $alerta->descricao."\n\n" : '')
            .'Aberto em '.$alerta->aberto_em?->format('d/m/Y H:i')."\n\n"
            .'Abra a central de alertas para tratar.';
    }

    /** @param array<int, Alerta> $alertas */
    private function corpoDigestPadrao(array $alertas): string
    {
        $linhas = ['Alertas abertos agora:', ''];

        foreach (Alerta::NIVEIS_DO_CARD as $nivel) {
            $doNivel = array_values(array_filter($alertas, fn (Alerta $a) => $a->nivel === $nivel));

            if ($doNivel === []) {
                continue;
            }

            $linhas[] = strtoupper($nivel).':';

            foreach ($doNivel as $alerta) {
                $linhas[] = '  - '.$alerta->titulo.($alerta->descricao ? ' — '.$alerta->descricao : '');
            }

            $linhas[] = '';
        }

        return implode("\n", $linhas);
    }

    /** @param array<string, array<int, Alerta>> $porTenant */
    private function corpoDigestGlobalPadrao(array $porTenant): string
    {
        $linhas = ['Alertas abertos por clínica:', ''];

        foreach ($porTenant as $nomeTenant => $alertas) {
            $linhas[] = $nomeTenant.':';

            foreach ($alertas as $alerta) {
                $linhas[] = '  ['.$alerta->nivel.'] '.$alerta->titulo;
            }

            $linhas[] = '';
        }

        return implode("\n", $linhas);
    }
}
