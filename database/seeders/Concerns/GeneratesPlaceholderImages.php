<?php

namespace Database\Seeders\Concerns;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Generates simple, deterministic-colour placeholder JPEGs (via GD) so the
 * seeded catalogue has product imagery without needing real product photos
 * or network access. Real product photos can be uploaded later from the
 * Filament admin - this only exists to make the seeded storefront/admin
 * look populated for testing (TOR - "seeders for initial testing").
 */
trait GeneratesPlaceholderImages
{
    /**
     * Create a placeholder image on the "public" disk and return its
     * relative path (suitable for product_images.path / categories.image),
     * or null if the GD extension isn't available so seeding never fails
     * on an unusual PHP build.
     */
    protected function generatePlaceholderImage(string $label, string $folder = 'products'): ?string
    {
        if (! extension_loaded('gd')) {
            return null;
        }

        $width = 800;
        $height = 800;

        $image = imagecreatetruecolor($width, $height);

        // Deterministic-but-varied background colour per label so the
        // catalogue doesn't look monotone.
        $hash = crc32($label);
        $r = 60 + ($hash % 130);
        $g = 90 + ((int) ($hash / 7) % 110);
        $b = 55 + ((int) ($hash / 13) % 110);

        $bg = imagecolorallocate($image, $r, $g, $b);
        imagefilledrectangle($image, 0, 0, $width, $height, $bg);

        // Soft inner panel for contrast behind the label text.
        $panelColor = imagecolorallocate($image, (int) max($r - 30, 0), (int) max($g - 30, 0), (int) max($b - 30, 0));
        imagefilledrectangle($image, 30, (int) ($height / 2) - 70, $width - 30, (int) ($height / 2) + 70, $panelColor);

        $white = imagecolorallocate($image, 255, 255, 255);

        $font = 5; // built-in GD font - no TTF font file required
        $lines = explode("\n", wordwrap($label, 24, "\n"));
        $lineHeight = imagefontheight($font) + 8;
        $startY = (int) (($height / 2) - ((count($lines) * $lineHeight) / 2));

        foreach ($lines as $i => $line) {
            $textWidth = imagefontwidth($font) * strlen($line);
            $x = (int) (($width - $textWidth) / 2);
            $y = $startY + ($i * $lineHeight);
            imagestring($image, $font, $x, $y, $line, $white);
        }

        $brand = 'CY-Market';
        $brandWidth = imagefontwidth(3) * strlen($brand);
        imagestring($image, 3, (int) (($width - $brandWidth) / 2), $height - 40, $brand, $white);

        $path = trim($folder, '/').'/'.Str::slug($label).'-'.Str::random(6).'.jpg';
        $fullPath = Storage::disk('public')->path($path);

        $directory = dirname($fullPath);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        imagejpeg($image, $fullPath, 82);
        imagedestroy($image);

        return $path;
    }
}
