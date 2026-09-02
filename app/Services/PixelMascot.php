<?php

namespace App\Services;

/**
 * The pixel creature this app draws everywhere.
 *
 * He is defined once as a character grid and rasterised three ways: a macOS
 * template PNG for the menu bar, SVG rectangles in the dropdown panel, and
 * artwork for the marketing page. One grid keeps all three identical.
 *
 * He fills from the feet up as the session is spent: empty and pale at nothing
 * used, solid at the limit. The eyes never change, so he stays recognisable at
 * every level, including 16pt in the menu bar where each cell is one pixel.
 */
class PixelMascot
{
    /** Cells across and down. Divides evenly into 16pt and 32pt. */
    public const GRID = 16;

    /**
     * B is body, E is an eye, a dot is empty.
     *
     * @var list<string>
     */
    private const ART = [
        '................',
        '................',
        '...BBBBBBBBBB...',
        '...BBBBBBBBBB...',
        '...BEEBBBBEEB...',
        '...BEEBBBBEEB...',
        '.BBBBBBBBBBBBBB.',
        '.BBBBBBBBBBBBBB.',
        '.BBBBBBBBBBBBBB.',
        '...BBBBBBBBBB...',
        '...BBBBBBBBBB...',
        '...BB.BB.BB.BB..',
        '...BB.BB.BB.BB..',
        '...BB.BB.BB.BB..',
        '................',
        '................',
    ];

    /**
     * Every drawn cell.
     *
     * @return list<array{x: int, y: int, eye: bool}>
     */
    public function cells(): array
    {
        $cells = [];

        foreach (self::ART as $y => $line) {
            foreach (str_split($line) as $x => $character) {
                if ($character === '.') {
                    continue;
                }

                $cells[] = ['x' => $x, 'y' => $y, 'eye' => $character === 'E'];
            }
        }

        return $cells;
    }

    /**
     * The rows he actually occupies, so the fill spans the character rather
     * than the whole canvas.
     *
     * @return array{0: int, 1: int}
     */
    public function bodyBounds(): array
    {
        $rows = [];

        foreach (self::ART as $y => $line) {
            if ($line !== str_repeat('.', self::GRID)) {
                $rows[] = $y;
            }
        }

        return [min($rows), max($rows)];
    }

    /**
     * Whether a given row has been consumed yet. The fill climbs from his feet
     * to his head, so the row nearest the bottom lights first.
     */
    public function rowIsLit(?float $percent, int $row): bool
    {
        if ($percent === null || $percent <= 0) {
            return false;
        }

        [$top, $bottom] = $this->bodyBounds();
        $height = $bottom - $top + 1;
        $litRows = (int) round((max(0.0, min(100.0, $percent)) / 100) * $height);

        return $row > $bottom - $litRows;
    }
}
