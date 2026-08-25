<?php

declare(strict_types=1);

/*
 * This file is part of the PHPColor library.
 *
 * (c) 2024-present Simon André & Raphaêl Geffroy
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace PhpColor\Color\Tests\Name;

use PhpColor\Color\Color;
use PhpColor\Color\Exception\ParseException;
use PhpColor\Color\Name\ColorNamer;
use PhpColor\Color\OklchColor;
use PhpColor\Color\Palette\ColorPalette;
use PhpColor\Color\SrgbColor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ColorNamer::class)]
final class ColorNamerTest extends TestCase
{
    /** @var array<string, array{float, float}> */
    private const array FAMILY_RANGES = [
        'red' => [15.0, 45.0],
        'orange' => [45.0, 85.0],
        'yellow' => [85.0, 125.0],
        'green' => [125.0, 190.0],
        'blue' => [190.0, 285.0],
        'purple' => [285.0, 335.0],
        'pink' => [335.0, 375.0],
    ];

    /** @var array<string, array{float, float}> */
    private const array TONE_REPRESENTATIVES = [
        'saturated' => [0.55, 0.50],
        'neutral' => [0.55, 0.03],
        'pastel' => [0.75, 0.03],
        'pale' => [0.90, 0.0201],
        'dark' => [0.20, 0.03],
    ];

    /** @var array<string, array{list<float>, list<float>}> */
    private const array SRGB_SEARCH_SAMPLES = [
        'saturated' => [[0.40, 0.50, 0.60, 0.66, 0.75], [0.70, 0.80, 0.90, 0.97]],
        'neutral' => [[0.40, 0.48, 0.55, 0.62, 0.66], [0.08, 0.15, 0.25, 0.40, 0.55]],
        'pastel' => [[0.68, 0.72, 0.76, 0.80, 0.83], [0.08, 0.20, 0.35, 0.50, 0.65]],
        'pale' => [[0.85, 0.88, 0.91, 0.94, 0.97], [0.04, 0.12, 0.25, 0.40, 0.55]],
        'dark' => [[0.12, 0.18, 0.24, 0.30, 0.34], [0.08, 0.25, 0.50, 0.75, 0.95]],
    ];

    #[DataProvider('providePerceptualExamples')]
    public function testReturnsDeterministicPerceptualNames(string $input, string $expected): void
    {
        self::assertSame($expected, ColorNamer::name($input));
        self::assertSame($expected, ColorNamer::name(Color::parse($input)));
    }

    public function testRejectsInvalidColorString(): void
    {
        $this->expectException(ParseException::class);

        ColorNamer::name('not-a-color');
    }

    /** @return iterable<string, array{string, string}> */
    public static function providePerceptualExamples(): iterable
    {
        yield 'red' => ['#ff0000', 'Fever'];
        yield 'orange' => ['#ff8c00', 'Mango'];
        yield 'yellow' => ['#ffff00', 'Yolk'];
        yield 'green' => ['#00ff00', 'Frog'];
        yield 'cyan' => ['#00ffff', 'Cyan'];
        yield 'blue' => ['#0000ff', 'Sapphire'];
        yield 'purple' => ['#663399', 'Shade'];
        yield 'magenta' => ['#ff00ff', 'Bloom'];
        yield 'earth tone' => ['#3b210f', 'Whiskey'];
        yield 'black' => ['#000000', 'Black'];
        yield 'gray' => ['#808080', 'Gray'];
        yield 'white' => ['#ffffff', 'White'];
    }

    public function testVocabularyContainsExactly360UniqueOneWordNames(): void
    {
        $names = ColorNamer::getAllPossibleNames();

        self::assertSame(360, ColorNamer::getTotalPossibleNames());
        self::assertCount(360, $names);
        self::assertCount(360, array_unique(array_map('strtolower', $names)));
        self::assertSame([], array_values(array_filter(
            $names,
            static fn (string $name): bool => 1 !== str_word_count($name),
        )));
    }

    public function testEveryNameIsReachableFromOklch(): void
    {
        $reachable = [];

        foreach (self::FAMILY_RANGES as [$start, $end]) {
            foreach (self::TONE_REPRESENTATIVES as [$lightness, $chroma]) {
                for ($index = 0; $index < 10; ++$index) {
                    $hue = fmod($start + ($index + 0.5) * (($end - $start) / 10.0), 360.0);
                    $reachable[] = ColorNamer::name(new OklchColor($lightness, $chroma, $hue));
                }
            }
        }

        for ($index = 0; $index < 10; ++$index) {
            $reachable[] = ColorNamer::name(new OklchColor(($index + 0.5) / 10.0, 0.0, 0.0));
        }

        self::assertSame(ColorNamer::getAllPossibleNames(), $reachable);
    }

    public function testEveryNameIsReachableFromA24BitSrgbColor(): void
    {
        $expected = ColorNamer::getAllPossibleNames();
        $maxChromaMethod = new \ReflectionMethod(ColorNamer::class, 'getMaxSrgbChroma');
        $offset = 0;

        foreach (self::FAMILY_RANGES as [$start, $end]) {
            $cellWidth = ($end - $start) / 10.0;

            foreach (self::SRGB_SEARCH_SAMPLES as $tone => [$lightnessSamples, $chromaSamples]) {
                for ($index = 0; $index < 10; ++$index) {
                    $target = $expected[$offset++];
                    $found = false;

                    foreach ([0.15, 0.30, 0.50, 0.70, 0.85] as $huePosition) {
                        $hue = fmod($start + ($index + $huePosition) * $cellWidth, 360.0);

                        foreach ($lightnessSamples as $lightness) {
                            $maxChroma = $maxChromaMethod->invoke(null, $lightness, $hue);
                            self::assertIsFloat($maxChroma);
                            $availableChroma = max(0.0, $maxChroma - 0.02);

                            foreach ($chromaSamples as $chromaPosition) {
                                $chroma = 0.02 + $chromaPosition * $availableChroma;
                                $chroma = match ($tone) {
                                    'pastel' => min(0.14, $chroma),
                                    'pale' => min(0.07, $chroma),
                                    default => $chroma,
                                };
                                $candidate = self::to24BitSrgb(new OklchColor($lightness, $chroma, $hue));

                                if ($target === ColorNamer::name($candidate)) {
                                    $found = true;
                                    break 3;
                                }
                            }
                        }
                    }

                    self::assertTrue($found, \sprintf('No 24-bit sRGB representative found for "%s".', $target));
                }
            }
        }

        for ($index = 0; $index < 10; ++$index) {
            $target = $expected[$offset++];
            $found = false;

            for ($step = 1; $step < 20; ++$step) {
                $candidate = self::to24BitSrgb(new OklchColor(($index + $step / 20.0) / 10.0, 0.0, 0.0));

                if ($target === ColorNamer::name($candidate)) {
                    $found = true;
                    break;
                }
            }

            self::assertTrue($found, \sprintf('No 24-bit sRGB representative found for "%s".', $target));
        }

        self::assertSame(360, $offset);
    }

    #[DataProvider('provideToneRepresentatives')]
    public function testToneClassification(float $lightness, float $chroma, string $expected): void
    {
        self::assertSame($expected, ColorNamer::name(new OklchColor($lightness, $chroma, 30.0)));
    }

    /** @return iterable<string, array{float, float, string}> */
    public static function provideToneRepresentatives(): iterable
    {
        yield 'dark' => [0.20, 0.10, 'Henna'];
        yield 'saturated' => [0.55, 0.20, 'Pulse'];
        yield 'neutral' => [0.55, 0.04, 'Wine'];
        yield 'pastel' => [0.75, 0.04, 'Apple'];
        yield 'pale' => [0.90, 0.0201, 'Petal'];
    }

    #[DataProvider('provideHueBoundaries')]
    public function testHueBoundaries(float $hue, string $expected): void
    {
        self::assertSame($expected, ColorNamer::name(new OklchColor(0.55, 0.04, $hue)));
    }

    /** @return iterable<string, array{float, string}> */
    public static function provideHueBoundaries(): iterable
    {
        yield 'pink before red' => [14.999, 'Suede'];
        yield 'red' => [15.0, 'Forge'];
        yield 'orange' => [45.0, 'Dune'];
        yield 'yellow' => [85.0, 'Toast'];
        yield 'green' => [125.0, 'Mold'];
        yield 'blue' => [190.0, 'Teal'];
        yield 'purple' => [285.0, 'Midnight'];
        yield 'pink' => [335.0, 'Swan'];
    }

    public function testPinkWrapsAroundZeroDegrees(): void
    {
        self::assertSame('Ribbon', ColorNamer::name(new OklchColor(0.55, 0.04, 350.0)));
        self::assertSame('Taupe', ColorNamer::name(new OklchColor(0.55, 0.04, 5.0)));
    }

    public function testNeutralBoundaryAndAlpha(): void
    {
        self::assertSame('Gray', ColorNamer::name(new OklchColor(0.55, 0.0199, 30.0)));
        self::assertSame('Wine', ColorNamer::name(new OklchColor(0.55, 0.02, 30.0)));
        self::assertSame(
            ColorNamer::name('#ff0000'),
            ColorNamer::name('#ff000080'),
        );
    }

    public function testOutOfGamutInputReceivesADeterministicTone(): void
    {
        self::assertSame('Petal', ColorNamer::name(new OklchColor(1.0, 0.03, 30.0)));
    }

    #[DataProvider('providePaletteColors')]
    public function testShadeAndTintScalesUseSeveralNames(string $source): void
    {
        $color = Color::parse($source);
        $shades = ColorPalette::shades($color, 5)->all();
        $tints = ColorPalette::tints($color, 5)->all();
        $scale = [$shades[3], $shades[2], $shades[1], $shades[0], $tints[1], $tints[2], $tints[3]];
        $names = array_map(ColorNamer::name(...), $scale);

        self::assertGreaterThanOrEqual(3, \count(array_unique($names)));
    }

    /** @return iterable<string, array{string}> */
    public static function providePaletteColors(): iterable
    {
        yield 'crimson' => ['crimson'];
        yield 'orange' => ['darkorange'];
        yield 'gold' => ['gold'];
        yield 'green' => ['seagreen'];
        yield 'blue' => ['dodgerblue'];
        yield 'purple' => ['rebeccapurple'];
    }

    private static function to24BitSrgb(OklchColor $color): SrgbColor
    {
        $srgb = $color->toSrgb();

        return new SrgbColor(
            round($srgb->r * 255.0) / 255.0,
            round($srgb->g * 255.0) / 255.0,
            round($srgb->b * 255.0) / 255.0,
        );
    }
}
