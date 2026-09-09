<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Manual e mapa mental: conteudo do PRODUTO, servido do repositorio.
 *
 * Deixou de ser conteudo do tenant. Enquanto era editavel por clinica, cada uma
 * tinha um manual diferente e ninguem sabia qual era o certo — e "atualizou o
 * manual, virou novidade" so faz sentido se a novidade for release note do
 * produto, e nao a edicao de texto de uma clinica.
 *
 * O texto que a NeuroKids escreveu NAO foi descartado: foi exportado da tabela
 * `manuais` para os arquivos abaixo antes do drop, e e ele que passa a ser
 * servido. As sementes originais do repositorio continuam versionadas como
 * `default.html` e `mapa-mental-default.html`.
 *
 * Trade-off aceito, registrado em ADR: correcao de texto passa a exigir deploy.
 */
class ManualController extends Controller
{
    private const ARQUIVOS = [
        'manual' => 'manual.html',
        'mapa-mental' => 'mapa-mental.html',
    ];

    public function show(string $tipo = 'manual'): JsonResponse
    {
        $arquivo = self::ARQUIVOS[$tipo] ?? null;

        if (! $arquivo) {
            throw new NotFoundHttpException('Documento não encontrado.');
        }

        $caminho = resource_path('manual/'.$arquivo);

        if (! is_file($caminho)) {
            throw new NotFoundHttpException('Documento não encontrado.');
        }

        return response()->json([
            'data' => [
                'tipo' => $tipo,
                'conteudo_html' => file_get_contents($caminho),
                // Sem `atualizado_por`: nao ha mais edicao pela interface, e
                // quem alterou o texto agora aparece no historico do git.
                'atualizado_em' => date(DATE_ATOM, filemtime($caminho)),
            ],
        ]);
    }
}
