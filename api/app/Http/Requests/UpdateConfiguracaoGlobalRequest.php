<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateConfiguracaoGlobalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // 0 desliga a expiração. O teto de 43200 são 30 dias — acima disso
            // a configuração deixa de ser prazo e vira "nunca" mal escrito.
            'sessao_minutos' => ['required', 'integer', 'min:0', 'max:43200'],
            'senha_alerta_dias' => ['required', 'integer', 'min:1', 'max:180'],
            'sessoes_padrao' => ['required', 'integer', 'min:1', 'max:999'],
            'itens_por_pagina' => ['required', 'integer', 'min:5', 'max:200'],

            // Piso de 3 meses: prazo menor que isso esvazia a trilha antes de
            // qualquer conferencia de fechamento. Teto de 120 meses (10 anos)
            // porque acima disso a decisao e "guardar sempre", e ai o caminho
            // e desligar o expurgo, nao esticar o prazo.
            'auditoria_retencao_meses' => ['required', 'integer', 'min:3', 'max:120'],

            // Imagem de documento pessoal: o teto e baixo de proposito. Quem
            // precisa guardar por mais tempo deveria arquivar fora do sistema.
            'carteirinha_retencao_dias' => ['required', 'integer', 'min:1', 'max:365'],

            // Reagendamento da proxima consulta de status Unimed. "Sucesso" e o
            // caso normal (guia ainda em analise, sem novidade); "falha" e
            // quando a automacao quebrou por erro tecnico (timeout etc.) e deve
            // tentar de novo bem mais cedo.
            'unimed_recheck_horas_sucesso' => ['required', 'integer', 'min:1', 'max:168'],
            'unimed_recheck_horas_falha' => ['required', 'integer', 'min:1', 'max:168'],
        ];
    }

    public function messages(): array
    {
        return [
            'sessao_minutos.max' => 'O tempo de sessão não pode passar de 43200 minutos (30 dias).',
            'itens_por_pagina.min' => 'A listagem precisa mostrar ao menos 5 itens por página.',
            'auditoria_retencao_meses.min' => 'A auditoria precisa ser mantida por ao menos 3 meses.',
            'carteirinha_retencao_dias.max' => 'A imagem da carteirinha não pode ser guardada por mais de 365 dias.',
            'unimed_recheck_horas_falha.max' => 'O reagendamento após falha não pode passar de 168 horas (7 dias).',
        ];
    }
}
