<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateLancamentoPrintTemplateRequest;
use App\Http\Resources\LancamentoPrintTemplateResource;
use App\Models\LancamentoPrintTemplate;

class LancamentoPrintTemplateController extends Controller
{
    private const CHAVE_REGISTRO_SESSOES = 'registro_sessoes';

    public function show(): LancamentoPrintTemplateResource
    {
        $template = $this->firstOrCreateTemplate((int) request()->user()->tenant_id);
        $template->wasRecentlyCreated = false;

        return new LancamentoPrintTemplateResource($template);
    }

    public function update(UpdateLancamentoPrintTemplateRequest $request): LancamentoPrintTemplateResource
    {
        $template = $this->firstOrCreateTemplate((int) $request->user()->tenant_id);
        $template->fill($request->validated());
        $template->save();

        return new LancamentoPrintTemplateResource($template->refresh());
    }

    private function firstOrCreateTemplate(int $tenantId): LancamentoPrintTemplate
    {
        return LancamentoPrintTemplate::query()->firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'chave' => self::CHAVE_REGISTRO_SESSOES,
            ],
            [
                'nome' => 'Registro de sessões',
                'html' => self::defaultHtml(),
                'ativo' => true,
            ]
        );
    }

    public static function defaultHtml(): string
    {
        return <<<'HTML'
<style>
  body { font-family: Arial, sans-serif; color: #0f172a; }
  .header { display: flex; justify-content: space-between; gap: 24px; margin-bottom: 24px; }
  .eyebrow { font-size: 11px; letter-spacing: 0.18em; text-transform: uppercase; color: #64748b; }
  h1 { margin: 8px 0; font-size: 28px; }
  .meta { text-align: right; font-size: 13px; line-height: 1.6; }
  .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin: 24px 0; }
  .box { border: 1px solid #cbd5e1; border-radius: 12px; padding: 14px; min-height: 54px; }
  table { width: 100%; border-collapse: collapse; font-size: 13px; }
  th { text-align: left; font-size: 11px; letter-spacing: 0.14em; text-transform: uppercase; color: #64748b; border-bottom: 1px solid #94a3b8; padding: 10px 8px; }
  td { border-bottom: 1px solid #e2e8f0; padding: 14px 8px; vertical-align: top; }
</style>
<div class="header">
  <div>
    <div class="eyebrow">Registro de Sessões</div>
    <h1>Modelo em branco</h1>
    <p>Formulário para impressão e preenchimento manual antes da transcrição.</p>
  </div>
  <div class="meta">
    <div>Guia Nº {{guia_numero}}</div>
    <div>Clínica {{clinica}}</div>
    <div>Paciente {{paciente}}</div>
    <div>Cartão {{numero_cartao}}</div>
    <div>Impresso em {{data_impressao}}</div>
  </div>
</div>
<div class="grid">
  <div class="box">
    <div class="eyebrow">Profissional executante</div>
    <p>{{profissional_executante}}</p>
  </div>
  <div class="box">
    <div class="eyebrow">Terapia aplicada</div>
    <p>{{terapia_aplicada}}</p>
  </div>
</div>
<table>
  <thead>
    <tr>
      <th>#</th>
      <th>Data</th>
      <th>Hora início</th>
      <th>Hora fim</th>
      <th>Acompanhante</th>
      <th>Resumo das atividades</th>
    </tr>
  </thead>
  <tbody>
    {{#sessoes}}
    <tr>
      <td>{{numero}}</td>
      <td>{{data_sessao}}</td>
      <td>{{hora_inicio}}</td>
      <td>{{hora_fim}}</td>
      <td>{{acompanhante}}</td>
      <td>{{resumo_atividades}}</td>
    </tr>
    {{/sessoes}}
  </tbody>
</table>
HTML;
    }
}
