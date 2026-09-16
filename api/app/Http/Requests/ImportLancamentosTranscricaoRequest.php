<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportLancamentosTranscricaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'profissional_id' => ['required', 'integer', 'exists:profissionais,id'],
            // Nulo quando a sessão veio de leitura por imagem/PDF ou de
            // preenchimento manual da grade — só a "colar texto" tem
            // transcrição de verdade para reprocessar.
            'transcricao' => ['nullable', 'string'],
            'numero_cartao' => ['nullable', 'string'],
            'confirmar_envio' => ['sometimes', 'boolean'],
            'pdf_registro_sessoes' => ['nullable', 'file', 'mimes:pdf'],
            /*
             * O paciente da folha lida contradiz o da guia escolhida.
             *
             * Declarado pelo cliente, e não recalculado aqui: o servidor não
             * viu a folha — o que a IA leu vive na tela até a confirmação. A
             * API registra a decisão na auditoria; a guarda é de operação,
             * para a escolha não passar calada, não controle de acesso.
             *
             * A justificativa é obrigatória quando há divergência declarada:
             * é ela que diz, meses depois, por que a cota daquela guia foi
             * consumida assim.
             */
            'divergencia' => ['nullable', 'string', 'max:500'],
            'divergencia_justificativa' => ['required_with:divergencia', 'nullable', 'string', 'min:10', 'max:1000'],
            // Grade fixa de até 10 linhas (o máximo físico da folha); linhas em
            // branco podem vir no payload e são ignoradas na gravação.
            'sessoes' => ['required_if:confirmar_envio,true', 'array', 'max:10'],
            'sessoes.*.data_sessao' => ['nullable', 'date'],
            'sessoes.*.hora_inicio' => ['nullable', 'date_format:H:i'],
            'sessoes.*.hora_fim' => ['nullable', 'date_format:H:i'],
            'sessoes.*.acompanhante' => ['nullable', 'string'],
            'sessoes.*.resumo_atividades' => ['nullable', 'string'],
        ];
    }
}
