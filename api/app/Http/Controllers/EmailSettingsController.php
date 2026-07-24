<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateEmailSettingsRequest;
use App\Http\Resources\EmailSettingsResource;
use App\Models\EmailSmtpSetting;
use App\Models\EmailTemplate;
use Illuminate\Support\Arr;

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
}
