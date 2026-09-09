<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * O e-mail das notificacoes de alerta.
 *
 * Mailable, e nao `Mail::raw()`: raw nao e rastreado por `Mail::fake()`, entao
 * um envio feito assim e indistinguivel de nenhum envio dentro de um teste — e
 * "nao mandou e-mail" e exatamente o defeito que estes testes existem para pegar.
 *
 * Corpo em texto simples escapado: o conteudo vem de modelo editavel pela
 * clinica e de titulo de alerta, e nenhum dos dois deve conseguir injetar HTML
 * na caixa de entrada de quem recebe.
 */
class AlertaEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $assunto,
        public readonly string $texto,
    ) {
    }

    public function build(): self
    {
        return $this->subject($this->assunto)
            ->html('<pre style="font-family: inherit; white-space: pre-wrap;">'
                .e($this->texto)
                .'</pre>');
    }
}
