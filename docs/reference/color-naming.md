# Color Naming

`ColorNamer` assigns any PHPColor value one deterministic, human-readable
English name. It generates names from perceptual regions rather than searching
a catalog such as CSS, SVG, or X11.

```php
use PhpColor\Color\Name\ColorNamer;

ColorNamer::name('#ff0000'); // Fever
ColorNamer::name('#0000ff'); // Sapphire
ColorNamer::name('#808080'); // Gray
```

`name()` accepts either a CSS color string or any `ColorInterface` instance.

## Vocabulary

The vocabulary contains exactly 360 case-insensitively unique one-word names:

```text
7 hue families x 5 tone regimes x 10 names + 10 achromatic names = 360
```

The hue families are Red, Orange, Yellow, Green, Blue, Purple, and Pink. Each
uses Saturated, Neutral, Pastel, Pale, and Dark tone regimes. The ten
achromatic names run from `Black` to `White` by Oklch lightness.

```php
ColorNamer::getTotalPossibleNames(); // 360
ColorNamer::getAllPossibleNames();   // list<string>
```

## Classification

The input is converted to Oklch and alpha is ignored:

1. Chroma below `0.02` selects an achromatic name by lightness.
2. Hue selects one of the seven broad families.
3. Lightness and chroma select a tone regime.
4. Position within the hue family selects one of ten ordered nouns.

Chroma is normalized against the maximum sRGB chroma available at the same
lightness and hue. This keeps saturation classification consistent between hue
regions with different gamut limits.

Generated names are descriptive output. They are not CSS keywords and should
not be used as parsing input, stable identifiers, or accessibility labels.
