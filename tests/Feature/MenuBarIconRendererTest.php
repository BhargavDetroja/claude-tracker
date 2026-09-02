<?php

use App\Services\MenuBarIconRenderer;
use Illuminate\Support\Facades\File;

/** Mean ink across a region, where 0 is fully transparent and 127 fully solid. */
function inkDensity(string $path, ?callable $within = null): float
{
    $image = imagecreatefrompng($path);
    $size = imagesx($image);
    $total = 0;
    $counted = 0;

    for ($x = 0; $x < $size; $x++) {
        for ($y = 0; $y < $size; $y++) {
            if ($within !== null && ! $within($x, $y, $size)) {
                continue;
            }

            $total += 127 - ((imagecolorat($image, $x, $y) >> 24) & 0x7F);
            $counted++;
        }
    }

    return $counted === 0 ? 0.0 : $total / $counted;
}

it('names the icon so macOS treats it as a template image', function () {
    $path = app(MenuBarIconRenderer::class)->render(60.0);

    expect($path)->toEndWith('Template.png')
        ->and(File::exists($path))->toBeTrue();
});

it('writes a retina variant alongside the standard one', function () {
    $path = app(MenuBarIconRenderer::class)->render(60.0);
    $retina = str_replace('Template.png', 'Template@2x.png', $path);

    expect(File::exists($retina))->toBeTrue()
        ->and(getimagesize($path))->toMatchArray([0 => 16, 1 => 16])
        ->and(getimagesize($retina))->toMatchArray([0 => 32, 1 => 32]);
});

it('leaves the corners transparent so only the pizza is tinted', function () {
    $path = app(MenuBarIconRenderer::class)->render(100.0);
    $image = imagecreatefrompng($path);

    /** The corners fall outside the pizza's circle whatever the usage. */
    expect((imagecolorat($image, 0, 0) >> 24) & 0x7F)->toBe(127)
        ->and((imagecolorat($image, 15, 15) >> 24) & 0x7F)->toBe(127);
});

it('grows more solid as usage climbs', function () {
    $renderer = app(MenuBarIconRenderer::class);

    $empty = inkDensity($renderer->render(0.0));
    $half = inkDensity($renderer->render(50.0));
    $full = inkDensity($renderer->render(100.0));

    expect($empty)->toBeLessThan($half)
        ->and($half)->toBeLessThan($full);
});

it('fills from the bottom upward', function () {
    $path = app(MenuBarIconRenderer::class)->render(50.0);

    /**
     * Half spent lights his lower half, so the bottom of the icon carries
     * more ink than the top.
     */
    $bottom = inkDensity($path, fn (int $x, int $y, int $size): bool => $y >= $size / 2);
    $top = inkDensity($path, fn (int $x, int $y, int $size): bool => $y < $size / 2);

    expect($bottom)->toBeGreaterThan($top);
});

it('reuses one file per bucket so polling does not churn the disk', function () {
    $renderer = app(MenuBarIconRenderer::class);

    expect($renderer->render(39.0))->toBe($renderer->render(41.0))
        ->and($renderer->render(39.0))->not->toBe($renderer->render(52.0));
});

it('renders him empty when there is no reading yet', function () {
    expect(app(MenuBarIconRenderer::class)->render(null))
        ->toContain('mascot-unknownTemplate.png');
});
