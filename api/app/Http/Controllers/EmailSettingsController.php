<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendTestEmailRequest;
use App\Http\Requests\UpdateEmailSettingsRequest;
use App\Http\Resources\EmailSettingsResource;
use App\Models\EmailSmtpSetting;
use App\Models\EmailTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

class EmailSettingsController extends Controller
{
    public function show(): EmailSettingsResource
    {
        $tenantId = (int) request()->user()->tenant_id;

        return new EmailSettingsResource([
            'smtp' => EmailSmtpSetting::query()->where('tenant_id', $tenantId)->first(),
            'templates' => EmailTemplate::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('nome')
                ->get(),
        ]);
    }

    public function update(UpdateEmailSettingsRequest $request): EmailSettingsResource
    {
        $tenantId = (int) $request->user()->tenant_id;
        $smtpPayload = $request->validated('smtp');
        $templatesPayload = $request->validated('templates', []);

        $smtp = EmailSmtpSetting::query()->firstOrNew(['tenant_id' => $tenantId]);
        $password = Arr::pull($smtpPayload, 'password');
        $smtp->fill($smtpPayload);

        if (filled($password)) {
            $smtp->password = $password;
        }

        $smtp->save();

        foreach ($templatesPayload as $templatePayload) {
            EmailTemplate::query()->updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'chave' => $templatePayload['chave'],
                ],
                $templatePayload
            );
        }

        return $this->show();
    }

    /**
     * Dispara um e-mail de teste para o endereco informado.
     *
     * O mailer e montado na hora com o SMTP salvo do tenant, e nao com o
     * mailer padrao do .env: em producao `MAIL_MAILER=log`, entao usar o
     * padrao daria "enviado com sucesso" sem nada sair da maquina — o oposto
     * do que este botao existe para provar.
     */
    public function enviarTeste(SendTestEmailRequest $request): JsonResponse
    {
        $tenantId = (int) $request->user()->tenant_id;
        $destino = $request->validated('email');

        $smtp = EmailSmtpSetting::query()->where('tenant_id', $tenantId)->first();

        if (! $smtp || blank($smtp->host) || blank($smtp->from_email)) {
            throw ValidationException::withMessages([
                'email' => 'Preencha e salve o servidor SMTP e o remetente antes de enviar o teste.',
            ]);
        }

        if (! $smtp->ativo) {
            throw ValidationException::withMessages([
                'email' => 'O envio de e-mails está desativado. Ative-o e salve antes de testar.',
            ]);
        }

        // Nome unico por tenant para nao reaproveitar um transporte que ficou
        // em cache no container com credenciais de outro tenant.
        $nomeMailer = "smtp_tenant_{$tenantId}";

        config([
            "mail.mailers.{$nomeMailer}" => [
                'transport' => 'smtp',
                'host' => $smtp->host,
                'port' => $smtp->port,
                'encryption' => $smtp->encryption ?: null,
                'username' => $smtp->username ?: null,
                'password' => $smtp->password ?: null,
                'timeout' => 15,
            ],
        ]);

        try {
            Mail::mailer($nomeMailer)->raw(
                "Este é um e-mail de teste enviado pelo Gestão de Convênios.\n\n"
                ."Se você recebeu esta mensagem, o servidor SMTP configurado está funcionando.",
                function ($mensagem) use ($destino, $smtp) {
                    $mensagem->to($destino)
                        ->subject('Teste de envio — Gestão de Convênios')
                        ->from($smtp->from_email, $smtp->from_name ?: null);
                },
            );
        } catch (Throwable $erro) {
            // A mensagem do transporte diz o que houve (auth, DNS, TLS, porta).
            // Sem ela o operador nao tem como saber o que corrigir.
            throw ValidationException::withMessages([
                'email' => 'Falha ao enviar: '.$erro->getMessage(),
            ]);
        }

        return response()->json([
            'data' => ['mensagem' => "E-mail de teste enviado para {$destino}."],
        ]);
    }
}
