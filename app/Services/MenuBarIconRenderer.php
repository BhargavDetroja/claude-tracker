<?php

namespace App\Services;

use GdImage;
use Illuminate\Support\Facades\File;

/**
 * Draws the menu bar mascot as a macOS template image.
 *
 * Template images carry no colour of their own: macOS reads only the alpha
 * channel and tints the result to match the menu bar, which is what makes the
 * icon work in both light and dark mode without shipping two of everything.
 * The convention that switches this on is purely the filename, which must end
 * in "Template" (Electron passes it through to NSImage).
 *
 * He is drawn as pixel art from {@see PixelMascot}. An earlier version drew a
 * smooth vector shape and downsampled it for antialiasing, which went soft at
 * 16pt. Snapping every cell to a whole device pixel keeps him crisp, and it is
 * the same grid the dropdown panel draws.
 */
class MenuBarIconRenderer
{
    /**
     * Bumped whenever the drawing below changes, so previously cached PNGs
     * are regenerated instead of lingering in the user's storage directory.
     */
    private const RENDER_VERSION = 4;

    /** GD alpha (0 opaque, 127 transparent) for a row not yet consumed. */
    private const COLD_ALPHA = 96;

    public function __construct(
        private readonly PixelMascot $mascot,
    ) {}

    /**
     * Render the mascot for a given percentage and return the absolute path to
     * the @1x PNG. The @2x variant is written alongside it; Electron picks
     * that up on its own for Retina displays.
     *
     * Passing null renders him empty, used before the first reading.
     */
    public function render(?float $percent): string
    {
        $bucket = $this->bucket($percent);
        $directory = $this->directory();

        File::ensureDirectoryExists($directory);

        $path = $directory.DIRECTORY_SEPARATOR."mascot-{$bucket}Template.png";
        $retinaPath = $directory.DIRECTORY_SEPARATOR."mascot-{$bucket}Template@2x.png";

        if (! File::exists($path) || ! File::exists($retinaPath)) {
            $size = (int) config('claude.icon.size');

            $this->write($path, $size, $percent);
            $this->write($retinaPath, $size * 2, $percent);
        }

        return $path;
    }

    /**
     * Percentages are rounded to a bucket so a busy session does not write a
     * new pair of PNGs on every single poll.
     */
    private function bucket(?float $percent): string
    {
        if ($percent === null) {
            return 'unknown';
        }

        $bucketSize = max(1, (int) config('claude.icon.bucket'));
        $bucket = (int) (round(max(0.0, min(100.0, $percent)) / $bucketSize) * $bucketSize);

        return (string) $bucket;
    }

    private function directory(): string
    {
        $signature = substr(md5(implode(':', [
            self::RENDER_VERSION,
            config('claude.icon.size'),
            config('claude.icon.bucket'),
            PixelMascot::GRID,
        ])), 0, 8);

        return rtrim(config('claude.icon.path'), '/').DIRECTORY_SEPARATOR.$signature;
    }

    private function write(string $path, int $pixels, ?float $percent): void
    {
        $image = $this->draw($pixels, $percent);

        imagepng($image, $path);
        imagedestroy($image);
    }

    /**
     * Paint one rectangle per grid cell.
     *
     * Cell edges are rounded to whole pixels so neighbouring cells meet
     * exactly, with no seam and no half covered pixel along the boundary.
     */
    private function draw(int $size, ?float $percent): GdImage
    {
        $image = imagecreatetruecolor($size, $size);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, $this->ink($image, 127));

        $cellSize = $size / PixelMascot::GRID;

        foreach ($this->mascot->cells() as $cell) {
            /** Eyes are holes punched clean through, at every fill level. */
            if ($cell['eye']) {
                continue;
            }

            $isLit = $this->mascot->rowIsLit($percent, $cell['y']);

            imagefilledrectangle(
                $image,
                (int) round($cell['x'] * $cellSize),
                (int) round($cell['y'] * $cellSize),
                (int) round(($cell['x'] + 1) * $cellSize) - 1,
                (int) round(($cell['y'] + 1) * $cellSize) - 1,
                $this->ink($image, $isLit ? 0 : self::COLD_ALPHA),
            );
        }

        return $image;
    }

    /**
     * Template images are black plus alpha; only the alpha carries meaning.
     */
    private function ink(GdImage $image, int $alpha): int
    {
        return imagecolorallocatealpha($image, 0, 0, 0, $alpha);
    }
}
