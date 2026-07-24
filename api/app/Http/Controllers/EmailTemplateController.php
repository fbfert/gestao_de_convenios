<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmailTemplateRequest;
use App\Http\Resources\EmailTemplateResource;
use App\Models\EmailTemplate;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class EmailTemplateController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return EmailTemplateResource::collection(
            EmailTemplate::query()
                ->where('tenant_id', (int) request()->user()->tenant_id)
                ->orderBy('nome')
                ->get()
        );
    }

    public function store(StoreEmailTemplateRequest $request): EmailTemplateResource
    {
        $template = EmailTemplate::query()->create([
            ...$request->validated(),
            'tenant_id' => (int) $request->user()->tenant_id,
        ]);

        return new EmailTemplateResource($template);
    }

    public function update(
        StoreEmailTemplateRequest $request,
        EmailTemplate $emailTemplate,
    ): EmailTemplateResource {
        $this->authorizeTenant($emailTemplate, (int) $request->user()->tenant_id);

        $emailTemplate->fill($request->validated());
        $emailTemplate->save();

        return new EmailTemplateResource($emailTemplate->refresh());
    }

    public function destroy(EmailTemplate $emailTemplate): Response
    {
        $this->authorizeTenant($emailTemplate, (int) request()->user()->tenant_id);

        $emailTemplate->delete();

        return response()->noContent();
    }

    private function authorizeTenant(EmailTemplate $emailTemplate, int $tenantId): void
    {
        abort_if((int) $emailTemplate->tenant_id !== $tenantId, 404);
    }
}
