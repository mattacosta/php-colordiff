php-colorutility
================

Implements utility functions to compute the difference between colors and to
convert between different color models, such as [RGB] [1] and [CIELAB] [2].

For more information on delta E functions, see [Color difference] [3].

## Example
```php
$color1 = array('r' => 255, 'g' => 0, 'b' => 255);  // Magenta
$color2 = array('r' => 220, 'g' => 20, 'b' => 60);  // Crimson

$color1 = ColorUtility::xyz2cielab(ColorUtility::rgb2xyz($color1));
$color2 = ColorUtility::xyz2cielab(ColorUtility::rgb2xyz($color2));
$difference = ColorUtility::deltaE2000($color1, $color2);
```

[1]: http://en.wikipedia.org/wiki/SRGB
[2]: http://en.wikipedia.org/wiki/Lab_color_space
[3]: http://en.wikipedia.org/wiki/Color_difference
