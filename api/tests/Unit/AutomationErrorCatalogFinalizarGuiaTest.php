<?php

namespace Tests\Unit;

use App\Services\Automation\AutomationErrorCatalog;
use PHPUnit\Framework\TestCase;

/**
 * Cada código de erro da finalização tem tradução para o operador.
 *
 * Existe porque o portal real da Unimed nunca foi visto em desenvolvimento
 * (não há homologação): boa parte destes erros vai aparecer pela primeira vez
 * em produção, e um `error_code` cru na tela não diz a ninguém o que fazer.
 * Este teste é o que impede um código novo entrar sem rótulo.
 */
class AutomationErrorCatalogFinalizarGuiaTest extends TestCase
{
    /**
     * Os códigos que finalizarGuia.js pode devolver. Mexeu lá, mexe aqui.
     *
     * @return array<int, array{string}>
     */
    public static function codigosDaFinalizacao(): array
    {
        return array_map(fn (string $codigo) => [$codigo], [
            'NUMERO_GUIA_AUSENTE',
            'SEM_SESSOES_PARA_ENVIAR',
            'TELA_BUSCA_GUIA_NAO_ENCONTRADA',
            'GUIA_NAO_ENCONTRADA_NO_PORTAL',
            'TELA_EXECUCAO_NAO_ABRIU',
            'REGIME_ATENDIMENTO_RECUSADO',
            'TIPO_ATENDIMENTO_RECUSADO',
            'QT_AUTORIZADA_NAO_LIDA',
            'QT_AUTORIZADA_DIVERGENTE',
            'SESSOES_ACIMA_DO_AUTORIZADO',
            'SESSOES_ACIMA_DO_MAXIMO',
            'DT_SERIE_CAMPO_INDISPONIVEL',
            'DT_SERIE_FORMATO_RECUSADO',
            'ANEXO_SEM_CAMINHO',
            'ANEXO_BOTAO_NAO_ENCONTRADO',
            'ANEXO_NAO_CONFIRMADO',
            'BOTAO_GRAVAR_FINALIZAR_NAO_ENCONTRADO',
            'GRAVAR_FINALIZAR_RECUSADO',

            // Conferência de guias já finalizadas na operadora.
            'TELA_EXAMES_FINALIZADOS_NAO_ABRIU',
            'FILTRO_DATA_NAO_LIMPO',
        ]);
    }

    /**
     * @dataProvider codigosDaFinalizacao
     */
    public function test_cada_codigo_tem_rotulo_proprio(string $codigo): void
    {
        $rotulo = (new AutomationErrorCatalog)->label($codigo);

        $this->assertNotSame($codigo, $rotulo, "O código {$codigo} caiu no default do catálogo, sem rótulo próprio.");
        $this->assertNotSame('Erro não classificado', $rotulo);
    }

    /**
     * Nenhum deles é estrutural: falha de finalização é problema daquela guia,
     * não sinal de portal quebrado. Marcá-los como estruturais faria o
     * disjuntor pausar a credencial do convênio inteiro por causa de uma data
     * mal formatada.
     *
     * @dataProvider codigosDaFinalizacao
     */
    public function test_nenhum_codigo_pausa_a_credencial_do_convenio(string $codigo): void
    {
        $this->assertFalse(
            (new AutomationErrorCatalog)->isStructural($codigo),
            "O código {$codigo} não deveria pausar a credencial do convênio.",
        );
    }
}
