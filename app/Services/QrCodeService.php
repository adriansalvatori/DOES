<?php

namespace App\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class QrCodeService
{
    /**
     * Generate pure SVG string for a given URL or text.
     */
    public function generateSvg(string $content, int $size = 240, int $margin = 1): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size, $margin),
            new SvgImageBackEnd
        );

        $writer = new Writer($renderer);

        return $writer->writeString($content);
    }

    /**
     * Generate a Base64 Data URI SVG for inline embedding.
     */
    public function generateDataUri(string $content, int $size = 240, int $margin = 1): string
    {
        $svg = $this->generateSvg($content, $size, $margin);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
