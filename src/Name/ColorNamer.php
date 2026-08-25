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

namespace PhpColor\Color\Name;

use PhpColor\Color\ColorInterface;
use PhpColor\Color\OklchColor;

/**
 * Maps colors to a deterministic vocabulary of 360 short English nouns.
 *
 * Chromatic colors are classified by Oklch hue, lightness, and local sRGB
 * chroma. Near-achromatic colors use ten neutral names ordered by lightness.
 * Every returned value is a unique, one-word member of the vocabulary.
 */
final class ColorNamer
{
    private const float MAX_CHROMA = 0.5;
    private const float NEUTRAL_CHROMA_LIMIT = 0.02;
    private const float DARK_LIGHTNESS_LIMIT = 0.35;
    private const float PASTEL_LIGHTNESS_LIMIT = 0.67;
    private const float PASTEL_CHROMA_LIMIT = 0.145;
    private const float PALE_LIGHTNESS_LIMIT = 0.84;
    private const float PALE_CHROMA_LIMIT = 0.075;
    private const float SATURATED_CHROMA_POSITION = 0.68;

    /** @var list<string> */
    private const array NEUTRAL_NAMES = [
        'Black', 'Onyx', 'Ebony', 'Charcoal', 'Graphite',
        'Gray', 'Ash', 'Silver', 'Pearl', 'White',
    ];

    /** @var list<string> */
    private const array TONES = ['saturated', 'neutral', 'pastel', 'pale', 'dark'];

    /**
     * Names inside each tone follow the hue family from its lower edge to its
     * upper edge. Pink wraps around zero degrees and spans 335 to 375 degrees.
     *
     * @var array<string, array{names: array<string, list<string>>}>
     */
    private const array HUE_FAMILIES = [
        'red' => [
            'names' => [
                'saturated' => ['Flame', 'Lava', 'Magma', 'Heat', 'Fever', 'Pulse', 'Scarlet', 'Crimson', 'Chili', 'Poppy'],
                'neutral' => ['Forge', 'Iron', 'Brick', 'Mars', 'Garnet', 'Wine', 'Vein', 'Blood', 'Gore', 'Wound'],
                'pastel' => ['Silk', 'Rouge', 'Kiss', 'Heart', 'Stain', 'Apple', 'Cherry', 'Ruby', 'Beet', 'Tulip'],
                'pale' => ['Prawn', 'Radish', 'Veal', 'Meat', 'Tongue', 'Petal', 'Mallow', 'Carnation', 'Bacon', 'Soap'],
                'dark' => ['Cinder', 'Cardinal', 'Merlot', 'Maroon', 'Auburn', 'Henna', 'Cedar', 'Leather', 'Bark', 'Chestnut'],
            ],
        ],
        'orange' => [
            'names' => [
                'saturated' => ['Koi', 'Zest', 'Tang', 'Mango', 'Spark', 'Flare', 'Tiger', 'Blaze', 'Fire', 'Paprika'],
                'neutral' => ['Dune', 'Clay', 'Ochre', 'Copper', 'Bronze', 'Brass', 'Spice', 'Maple', 'Cider', 'Rust'],
                'pastel' => ['Guava', 'Peach', 'Melon', 'Apricot', 'Papaya', 'Sorbet', 'Sherbet', 'Begonia', 'Cantaloupe', 'Taffy'],
                'pale' => ['Chiffon', 'Conch', 'Shrimp', 'Flesh', 'Cream', 'Biscuit', 'Sand', 'Chamois', 'Squash', 'Tile'],
                'dark' => ['Cocoa', 'Cinnamon', 'Whiskey', 'Caramel', 'Toffee', 'Brandy', 'Umber', 'Sienna', 'Mahogany', 'Liver'],
            ],
        ],
        'yellow' => [
            'names' => [
                'saturated' => ['Sun', 'Star', 'Shine', 'Light', 'Beam', 'Glint', 'Yolk', 'Lemon', 'Canary', 'Finch'],
                'neutral' => ['Toast', 'Honey', 'Gold', 'Coin', 'Crown', 'Mustard', 'Corn', 'Straw', 'Flax', 'Maize'],
                'pastel' => ['Dawn', 'Aura', 'Halo', 'Blond', 'Butter', 'Custard', 'Vanilla', 'Pollen', 'Daffodil', 'Bee'],
                'pale' => ['Ivory', 'Parchment', 'Linen', 'Oat', 'Rice', 'Curd', 'Whey', 'Primrose', 'Daisy', 'Celery'],
                'dark' => ['Turmeric', 'Curry', 'Khaki', 'Tobacco', 'Resin', 'Tea', 'Olive', 'Lichen', 'Algae', 'Tarnish'],
            ],
        ],
        'green' => [
            'names' => [
                'saturated' => ['Lime', 'Slime', 'Frog', 'Toad', 'Grass', 'Snake', 'Emerald', 'Jade', 'Beryl', 'Mint'],
                'neutral' => ['Mold', 'Moss', 'Brush', 'Grove', 'Oak', 'Elm', 'Pine', 'Kelp', 'Scale', 'Sage'],
                'pastel' => ['Pear', 'Shoot', 'Bud', 'Weed', 'Vine', 'Stem', 'Flora', 'Leaf', 'Fern', 'Thorn'],
                'pale' => ['Sprout', 'Endive', 'Fennel', 'Leek', 'Cucumber', 'Cabbage', 'Bamboo', 'Jadeite', 'Glass', 'Foam'],
                'dark' => ['Pickle', 'Ivy', 'Laurel', 'Holly', 'Forest', 'Fir', 'Bottle', 'Malachite', 'Yew', 'Petrol'],
            ],
        ],
        'blue' => [
            'names' => [
                'saturated' => ['Cyan', 'Aqua', 'Sea', 'Wave', 'Gulf', 'Azure', 'Cobalt', 'Sapphire', 'Lapis', 'Indigo'],
                'neutral' => ['Teal', 'Bay', 'Pond', 'Lake', 'Tide', 'Denim', 'Steel', 'Navy', 'Storm', 'Ink'],
                'pastel' => ['Frost', 'Chill', 'Wind', 'Mist', 'Tear', 'Drop', 'Rain', 'Pool', 'Sky', 'Periwinkle'],
                'pale' => ['Agave', 'Air', 'Vapor', 'Snow', 'Cloud', 'Haze', 'Moon', 'Robin', 'Glacier', 'Powder'],
                'dark' => ['Ocean', 'Whale', 'Shark', 'Raven', 'Crow', 'Night', 'Abyss', 'Squid', 'Beetle', 'Mallard'],
            ],
        ],
        'purple' => [
            'names' => [
                'saturated' => ['Royal', 'Violet', 'Iris', 'Pansy', 'Crocus', 'Aster', 'Amethyst', 'Gem', 'Bloom', 'Orchid'],
                'neutral' => ['Midnight', 'Cloak', 'Shadow', 'Shade', 'Bruise', 'Plum', 'Fig', 'Grape', 'Berry', 'Jam'],
                'pastel' => ['Dream', 'Spell', 'Heather', 'Thistle', 'Lupine', 'Lilac', 'Mauve', 'Velvet', 'Lavender', 'Dusk'],
                'pale' => ['Wisteria', 'Hydrangea', 'Viola', 'Opal', 'Mica', 'Onion', 'Turnip', 'Blossom', 'Quartz', 'Talc'],
                'dark' => ['Aubergine', 'Prune', 'Raisin', 'Damson', 'Mulberry', 'Eggplant', 'Currant', 'Sloe', 'Acai', 'Elderberry'],
            ],
        ],
        'pink' => [
            'names' => [
                'saturated' => ['Magenta', 'Fuchsia', 'Love', 'Lip', 'Bubble', 'Gum', 'Candy', 'Flamingo', 'Coral', 'Peony'],
                'neutral' => ['Swan', 'Lace', 'Bow', 'Ribbon', 'Lychee', 'Skin', 'Salmon', 'Taupe', 'Ham', 'Suede'],
                'pastel' => ['Cotton', 'Floss', 'Lotus', 'Blush', 'Rose', 'Icing', 'Macaron', 'Ballet', 'Pig', 'Tulle'],
                'pale' => ['Shell', 'Organza', 'Voile', 'Milk', 'Almond', 'Champagne', 'Meringue', 'Satin', 'Feather', 'Down'],
                'dark' => ['Cerise', 'Raspberry', 'Cranberry', 'Pomegranate', 'Dragonfruit', 'Claret', 'Burgundy', 'Carmine', 'Cordovan', 'Sangria'],
            ],
        ],
    ];

    /**
     * Returns the vocabulary name assigned to a color's Oklch region.
     */
    public static function name(ColorInterface|string $color): string
    {
        $oklch = OklchColor::from($color);

        if ($oklch->c < self::NEUTRAL_CHROMA_LIMIT) {
            return self::NEUTRAL_NAMES[self::quantize($oklch->l, \count(self::NEUTRAL_NAMES))];
        }

        $family = self::getHueFamily($oklch->h);
        $tone = self::getTone($oklch);
        $position = self::getHuePosition($family, $oklch->h);

        return self::HUE_FAMILIES[$family]['names'][$tone][self::quantize($position, 10)];
    }

    /**
     * Returns the complete vocabulary in family, tone, and hue order.
     *
     * @return list<string>
     */
    public static function getAllPossibleNames(): array
    {
        $names = [];

        foreach (self::HUE_FAMILIES as $family) {
            foreach (self::TONES as $tone) {
                array_push($names, ...$family['names'][$tone]);
            }
        }

        array_push($names, ...self::NEUTRAL_NAMES);

        return $names;
    }

    /**
     * Returns the fixed size of the public vocabulary.
     */
    public static function getTotalPossibleNames(): int
    {
        return \count(self::HUE_FAMILIES) * \count(self::TONES) * 10
            + \count(self::NEUTRAL_NAMES);
    }

    /**
     * Selects a tone using perceptual lightness and chroma relative to the
     * maximum sRGB chroma available at the same lightness and hue.
     */
    private static function getTone(OklchColor $color): string
    {
        if ($color->l < self::DARK_LIGHTNESS_LIMIT) {
            return 'dark';
        }

        $maxChroma = self::getMaxSrgbChroma($color->l, $color->h);
        $availableChroma = max(0.0, $maxChroma - self::NEUTRAL_CHROMA_LIMIT);
        $chromaPosition = $availableChroma > 0.0
            ? ($color->c - self::NEUTRAL_CHROMA_LIMIT) / $availableChroma
            : 1.0;

        if ($color->l >= self::PALE_LIGHTNESS_LIMIT && $color->c < self::PALE_CHROMA_LIMIT) {
            return 'pale';
        }

        if ($color->l >= self::PASTEL_LIGHTNESS_LIMIT && $color->c < self::PASTEL_CHROMA_LIMIT) {
            return 'pastel';
        }

        if ($chromaPosition >= self::SATURATED_CHROMA_POSITION) {
            return 'saturated';
        }

        return 'neutral';
    }

    /**
     * @return 'red'|'orange'|'yellow'|'green'|'blue'|'purple'|'pink'
     */
    private static function getHueFamily(float $hue): string
    {
        return match (true) {
            $hue < 15.0 || $hue >= 335.0 => 'pink',
            $hue < 45.0 => 'red',
            $hue < 85.0 => 'orange',
            $hue < 125.0 => 'yellow',
            $hue < 190.0 => 'green',
            $hue < 285.0 => 'blue',
            default => 'purple',
        };
    }

    /**
     * @param 'red'|'orange'|'yellow'|'green'|'blue'|'purple'|'pink' $family
     */
    private static function getHuePosition(string $family, float $hue): float
    {
        return match ($family) {
            'red' => ($hue - 15.0) / 30.0,
            'orange' => ($hue - 45.0) / 40.0,
            'yellow' => ($hue - 85.0) / 40.0,
            'green' => ($hue - 125.0) / 65.0,
            'blue' => ($hue - 190.0) / 95.0,
            'purple' => ($hue - 285.0) / 50.0,
            'pink' => (($hue < 15.0 ? $hue + 360.0 : $hue) - 335.0) / 40.0,
        };
    }

    /**
     * Finds the largest chroma that remains inside sRGB for an Oklch ray.
     */
    private static function getMaxSrgbChroma(float $lightness, float $hue): float
    {
        $radians = deg2rad($hue);
        $cosine = cos($radians);
        $sine = sin($radians);
        $lower = 0.0;
        $upper = self::MAX_CHROMA;

        for ($iteration = 0; $iteration < 12; ++$iteration) {
            $chroma = ($lower + $upper) / 2.0;

            if (self::isInSrgbGamut($lightness, $chroma, $cosine, $sine)) {
                $lower = $chroma;
            } else {
                $upper = $chroma;
            }
        }

        return $lower;
    }

    /**
     * Tests an Oklch point against the linear sRGB cube without clipping it.
     */
    private static function isInSrgbGamut(float $lightness, float $chroma, float $cosine, float $sine): bool
    {
        $okA = $chroma * $cosine;
        $okB = $chroma * $sine;
        $lRoot = $lightness + 0.3963377774 * $okA + 0.2158037573 * $okB;
        $mRoot = $lightness - 0.1055613458 * $okA - 0.0638541728 * $okB;
        $sRoot = $lightness - 0.0894841775 * $okA - 1.2914855480 * $okB;
        $l = $lRoot ** 3.0;
        $m = $mRoot ** 3.0;
        $s = $sRoot ** 3.0;
        $red = +4.0767416621 * $l - 3.3077115913 * $m + 0.2309699292 * $s;
        $green = -1.2684380046 * $l + 2.6097574011 * $m - 0.3413193965 * $s;
        $blue = -0.0041960863 * $l - 0.7034186147 * $m + 1.7076147010 * $s;

        return $red >= 0.0 && $red <= 1.0
            && $green >= 0.0 && $green <= 1.0
            && $blue >= 0.0 && $blue <= 1.0;
    }

    /**
     * Converts a normalized position to a safe zero-based vocabulary index.
     */
    private static function quantize(float $position, int $size): int
    {
        return min($size - 1, max(0, (int) floor($position * $size)));
    }
}
