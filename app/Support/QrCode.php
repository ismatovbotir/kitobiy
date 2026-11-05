<?php

namespace App\Support;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Makes QR codes as inline SVG, which needs no PHP image extension and stays
 * sharp at any print size.
 */
class QrCode
{
    /** Blank modules around the code, so scanners can tell it from the label's text. */
    private const int QUIET_ZONE_MODULES = 2;

    private const int SVG_SIZE = 256;

    public static function svg(string $content): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(self::SVG_SIZE, self::QUIET_ZONE_MODULES),
            new SvgImageBackEnd,
        );

        $svg = (new Writer($renderer))->writeString(
            $content,
            Encoder::DEFAULT_BYTE_MODE_ENCODING,
            ErrorCorrectionLevel::M(),
        );

        return (string) preg_replace('/^<\?xml[^>]*\?>\s*/', '', $svg);
    }
}
