<?php

namespace App\Support;

use RuntimeException;

/**
 * Uma imagem (JPG ou PNG) vira um PDF de uma página.
 *
 * Existe para a folha de registro de sessões: a captura da webcam e a foto do
 * celular chegam como imagem, e a folha é guardada — e enviada à operadora —
 * como PDF. Sem biblioteca de PDF no projeto, e sem precisar de uma: um PDF com
 * uma única imagem JPEG é um punhado de objetos, e o JPEG entra como está
 * (`DCTDecode`), sem nova compressão além da que o `gd` já faz.
 *
 * A página é A4, deitada quando a imagem é mais larga que alta, com a imagem
 * inteira centralizada — nunca cortada nem esticada.
 */
class ImagemParaPdf
{
    private const A4_CURTO = 595;

    private const A4_LONGO = 842;

    private const MARGEM = 18;

    private const QUALIDADE_JPEG = 85;

    private const LADO_MAXIMO = 2400;

    public static function ehImagem(string $mime): bool
    {
        return in_array($mime, ['image/jpeg', 'image/png'], true);
    }

    /** Conteúdo binário do PDF gerado a partir do arquivo em `$caminho`. */
    public static function converter(string $caminho): string
    {
        $bytes = @file_get_contents($caminho);
        $imagem = $bytes === false ? false : @imagecreatefromstring($bytes);

        if ($imagem === false) {
            throw new RuntimeException('Não foi possível ler a imagem da folha.');
        }

        imagepalettetotruecolor($imagem);
        $imagem = self::reduzir($imagem);
        $imagem = self::endireitar($imagem, $caminho);

        // PNG com transparência: fundo branco, e não preto, depois do JPEG.
        $largura = imagesx($imagem);
        $altura = imagesy($imagem);
        $fundo = imagecreatetruecolor($largura, $altura);
        imagefill($fundo, 0, 0, imagecolorallocate($fundo, 255, 255, 255));
        imagecopy($fundo, $imagem, 0, 0, 0, 0, $largura, $altura);

        ob_start();
        imagejpeg($fundo, null, self::QUALIDADE_JPEG);
        $jpeg = (string) ob_get_clean();

        return self::montarPdf($jpeg, $largura, $altura);
    }

    /**
     * Foto de celular tem 12 MP ou mais, e o `gd` guarda 4 bytes por pixel:
     * sem reduzir, uma foto passa de 140 MB de memória na conversão. 2400 px
     * no lado maior é o que a captura da webcam já usa para a folha — cerca de
     * 290 dpi numa página A4, com a letra manuscrita legível.
     */
    private static function reduzir(\GdImage $imagem): \GdImage
    {
        $maior = max(imagesx($imagem), imagesy($imagem));

        if ($maior <= self::LADO_MAXIMO) {
            return $imagem;
        }

        $fator = self::LADO_MAXIMO / $maior;

        return imagescale(
            $imagem,
            (int) round(imagesx($imagem) * $fator),
            (int) round(imagesy($imagem) * $fator),
        ) ?: $imagem;
    }

    /**
     * Foto de celular guarda a rotação no EXIF em vez de girar os pixels. Sem
     * aplicar, a folha iria de lado para a operadora.
     */
    private static function endireitar(\GdImage $imagem, string $caminho): \GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $imagem;
        }

        $exif = @exif_read_data($caminho);
        $angulo = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        return $angulo === 0 ? $imagem : (imagerotate($imagem, $angulo, 0) ?: $imagem);
    }

    private static function montarPdf(string $jpeg, int $largura, int $altura): string
    {
        [$paginaL, $paginaA] = $largura > $altura
            ? [self::A4_LONGO, self::A4_CURTO]
            : [self::A4_CURTO, self::A4_LONGO];

        $escala = min(
            ($paginaL - 2 * self::MARGEM) / $largura,
            ($paginaA - 2 * self::MARGEM) / $altura,
        );
        $w = round($largura * $escala, 2);
        $h = round($altura * $escala, 2);
        $x = round(($paginaL - $w) / 2, 2);
        $y = round(($paginaA - $h) / 2, 2);

        $conteudo = "q {$w} 0 0 {$h} {$x} {$y} cm /Im0 Do Q";

        $objetos = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$paginaL} {$paginaA}]"
                .' /Resources << /XObject << /Im0 4 0 R >> >> /Contents 5 0 R >>',
            "<< /Type /XObject /Subtype /Image /Width {$largura} /Height {$altura}"
                .' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '.strlen($jpeg)
                ." >>\nstream\n{$jpeg}\nendstream",
            '<< /Length '.strlen($conteudo)." >>\nstream\n{$conteudo}\nendstream",
        ];

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $posicoes = [];

        foreach ($objetos as $indice => $objeto) {
            $posicoes[] = strlen($pdf);
            $pdf .= ($indice + 1)." 0 obj\n{$objeto}\nendobj\n";
        }

        $inicioXref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objetos) + 1)."\n0000000000 65535 f \n";

        foreach ($posicoes as $posicao) {
            $pdf .= sprintf("%010d 00000 n \n", $posicao);
        }

        return $pdf.'trailer << /Size '.(count($objetos) + 1)." /Root 1 0 R >>\nstartxref\n{$inicioXref}\n%%EOF\n";
    }
}
