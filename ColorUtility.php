<?php

/**
 * Copyright 2014 Matt Acosta
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Provides functions to convert between different color models and compute
 * the difference between two colors as percieved by the human eye.
 */
class ColorUtility {
  /**
   * Returns a CIE hue value (in degrees).
   */
  protected static function cielab2hue($cie_a, $cie_b) {
    $bias = 0;
    if ($cie_a >= 0 && $cie_b == 0) {
      return 0;
    }
    if ($cie_a < 0 && $cie_b == 0) {
      return 180;
    }
    if ($cie_a == 0 && $cie_b > 0) {
      return 90;
    }
    if ($cie_a == 0 && $cie_b < 0) {
      return 270;
    }
    if ($cie_a > 0 && $cie_b > 0) {
      $bias = 0;
    }
    if ($cie_a < 0) {
      $bias = 180;
    }
    if ($cie_a > 0 && $cie_b < 0) {
      $bias = 360;
    }
    return rad2deg(atan2($cie_b, $cie_a)) + $bias;
  }
  
  /**
   * Converts a CIELAB color to a CIELCH representation.
   */
  public static function cielab2cielch($cielab) {
    $h = atan2($cielab['b'], $cielab['a']);
    $h = $h > 0 ? ($h / M_PI) * 180 : 360 - (abs($h) / M_PI) * 180;
    return array(
      'l' => $cielab['l'],
      'c' => sqrt(pow($cielab['a'], 2) + pow($cielab['b'], 2)),
      'h' => $h
    );
  }
  
  /**
   * Calculate the chroma difference of a color.
   *
   * Use of this function is not recommended.
   */
  public static function deltaC($cielab1, $cielab2) {
    return sqrt(pow($cielab2['a'], 2) + pow($cielab2['b'], 2)) - sqrt(pow($cielab1['a'], 2) + pow($cielab1['b'], 2));
  }
  
  /**
   * Calculate a delta E using the CMC l:c metric.
   *
   * The lightness and chroma weights should represent a ratio appropriate for
   * an application. For "acceptability" use a ratio of 2:1 and for
   * "imperceptability" use a ratio of 1:1.
   *
   * @param array $cielab1
   *   The first CIELAB color to compare.
   * @param array $cielab2
   *   The second CIELAB color to compare.
   * @param float $kl
   *   (optional) The weight given to the lightness of a color. Defaults to 2.
   * @param float $kc
   *   (optional) The weight given to the chroma of a color. Defaults to 1.
   */
  public static function deltaCMC($cielab1, $cielab2, $kl = 2, $kc = 1) {
    $c1 = sqrt(pow($cielab1['a'], 2) + pow($cielab1['b'], 2));
    $c2 = sqrt(pow($cielab2['a'], 2) + pow($cielab2['b'], 2));
    $ff = sqrt(pow($c1, 4) / (pow($c1, 4) + 1900));
    $h1 = self::cielab2hue($cielab1['a'], $cielab1['b']);
    
    if ($h1 < 164 || $h1 > 345) {
      $tt = 0.36 + abs(0.4 * cos(deg2rad($h1) + 35));
    }
    else {
      $tt = 0.56 + abs(0.2 * cos(deg2rad($h1) + 168));
    }
    
    $sl = ($cielab1['l'] < 16) ? 0.511 : (0.040975 * $cielab1['l']) / (1 + (0.01765 * $cielab1['l']));
    $sc = ((0.0638 * $c1) / (1 + (0.0131 * $c1))) + 0.638;
    $sh = (($ff * $tt) + 1 - $ff) * $sc;
    $dh = sqrt(pow($cielab2['a'] - $cielab1['a'], 2) + pow($cielab2['b'] - $cielab1['b'], 2) - pow($c2 - $c1, 2));
    
    $sl = ($cielab2['l'] - $cielab1['l']) / ($kl * $sl);
    $sc = ($c2 - $c1) / ($kc * $sc);
    $sh = $dh / $sh;
    
    return sqrt(pow($sl, 2) + pow($sc, 2) + pow($sh, 2));
  }
  
  /**
   * Calculate a delta E using simple euclidian geometry (CIE76).
   *
   * NOTE: Since the CIELAB color space is not as perceptually uniform as
   * intended (especially in saturated regions) this formula rates those colors
   * too highly and is not recommended.
   *
   * @param array $cielab1
   *   The first CIELAB color to compare.
   * @param array $cielab2
   *   The second CIELAB color to compare.
   */
  public static function deltaE($cielab1, $cielab2) {
    return sqrt(pow($cielab1['l'] - $cielab2['l'], 2) + pow($cielab1['a'] - $cielab2['a'], 2) + pow($cielab1['b'] - $cielab2['b'], 2));
  }
  
  /**
   * Calculate a delta E using the CIEDE2000 formula.
   *
   * This formula improves upon the CIE94 formula in the following areas:
   * - Adds a hue rotational term which improves "blues that turn into purple."
   * - Adds compensation for neutral colors.
   * - Adds further compensations for lightness, chroma, and hues.
   *
   * @param array $cielab1
   *   The first CIELAB color to compare.
   * @param array $cielab2
   *   The second CIELAB color to compare.
   *
   * @link http://www.ece.rochester.edu/~gsharma/ciede2000/ The CIEDE2000 Color-Difference Formula @endlink
   */
  public static function deltaE2000($cielab1, $cielab2, $kl = 1, $kc = 1, $kh = 1) {
    $c1 = sqrt(pow($cielab1['a'], 2) + pow($cielab1['b'], 2));
    $c2 = sqrt(pow($cielab2['a'], 2) + pow($cielab2['b'], 2));
    
    $cavg = ($c1 + $c2) / 2.0;
    $g = (1 - sqrt(pow($cavg, 7) / (pow($cavg, 7) + pow(25, 7)))) / 2.0;
    $a1 = (1 + $g) * $cielab1['a'];
    $a2 = (1 + $g) * $cielab2['a'];
    $c1 = sqrt(pow($a1, 2) + pow($cielab1['b'], 2));
    $c2 = sqrt(pow($a2, 2) + pow($cielab2['b'], 2));
    $h1 = ($a1 == 0 && $cielab1['b'] == 0) ? 0 : rad2deg(atan2($cielab1['b'], $a1)) + ($cielab1['b'] >= 0 ? 0 : 360);
    $h2 = ($a2 == 0 && $cielab2['b'] == 0) ? 0 : rad2deg(atan2($cielab2['b'], $a2)) + ($cielab2['b'] >= 0 ? 0 : 360);
    
    $lavg = ($cielab1['l'] + $cielab2['l']) / 2.0;
    $cavg = ($c1 + $c2) / 2.0;
    $havg = ($h1 + $h2) / 2.0;
    $havg = ($c1 * $c2) == 0 ? $h1 + $h2 : (abs($h2 - $h1) <= 180 ? $havg : ($h2 + $h1 < 360 ? $havg + 180 : $havg - 180));
    $lavg_sq = pow($lavg - 50, 2);
    $sl = 1 + (0.015 * $lavg_sq / sqrt(20 + $lavg_sq));
    $sc = 1 + 0.045 * $cavg;
    $t = 1 -
      0.17 * cos(deg2rad($havg - 30)) +
      0.24 * cos(deg2rad(2 * $havg)) +
      0.32 * cos(deg2rad(3 * $havg + 6)) -
      0.20 * cos(deg2rad(4 * $havg - 63));
    $sh = 1 + 0.015 * $cavg * $t;
    $dt = 30 * exp(-1 * pow(($havg - 275) / 25.0, 2));
    $rc = 2 * sqrt(pow($cavg, 7) / (pow($cavg, 7) + pow(25, 7)));
    $rt = -sin(deg2rad(2 * $dt)) * $rc;
    
    $dl = ($cielab2['l'] - $cielab1['l']) / $sl / $kl;
    $dc = ($c2 - $c1) / $sc / $kc;
    $dh = ($h2 - $h1) > 180 ? $h2 - $h1 - 360 : (($h2 - $h1) < -180 ? $h2 - $h1 + 360 : $h2 - $h1);
    $dh = 2 * sqrt($c1 * $c2) * sin(deg2rad($dh / 2.0));
    $dh = $dh / $sh / $kh;
    
    return sqrt(pow($dl, 2) + pow($dc, 2) + pow($dh, 2) + $rt * $dc * $dh);
  }
  
  /**
   * Calculate the delta E using application specific weights (CIE94).
   *
   * @param array $cielab1
   *   The first CIELAB color to compare.
   * @param array $cielab2
   *   The second CIELAB color to compare.
   * @param float $k1
   *   (optional) The application weight given to the chroma of a color.
   *   Graphic arts: 0.045 (default)
   *   Textiles: 0.048
   * @param float $k2
   *   (optional) The application weight given to the hue of a color.
   *   Graphic arts: 0.015 (default)
   *   Textiles: 0.014
   * @param float $kl
   *   (optional) The weight given to the lightness of a color.
   *   Graphic arts: 1 (default)
   *   Textiles: 2
   *
   * @see ColorUtility::deltaE2000()
   */
  public static function deltaE94($cielab1, $cielab2, $k1 = 0.045, $k2 = 0.015, $kl = 1, $kc = 1, $kh = 1) {
    $c1 = sqrt(pow($cielab1['a'], 2) + pow($cielab1['b'], 2));
    $c2 = sqrt(pow($cielab2['a'], 2) + pow($cielab2['b'], 2));
    $dl = $cielab1['l'] - $cielab2['l'];
    $dc = $c1 - $c2;
    $dh = sqrt(pow($cielab1['a'] - $cielab2['a'], 2) + pow($cielab1['b'] - $cielab2['b'], 2) - pow($dc, 2));
    $sl = 1;
    $sc = 1 + ($k1 * $c1);
    $sh = 1 + ($k2 * $c1);
    $sl = $dl / ($kl * $sl);
    $sc = $dc / ($kc * $sc);
    $sh = $dh / ($kh * $sh);
    return sqrt(pow($sl, 2) + pow($sc, 2) + pow($sh, 2));
  }
  
  /**
   * Calculate the hue difference of a color.
   *
   * Use of this function is not recommended.
   */
  public static function deltaH($cielab1, $cielab2) {
    $dc = sqrt(pow($cielab2['a'], 2) + pow($cielab2['b'], 2)) - sqrt(pow($cielab1['a'], 2) + pow($cielab1['b'], 2));
    return sqrt(pow($cielab2['a'] - $cielab1['a'], 2) + pow($cielab2['b'] - $cielab1['b'], 2) - pow($dc, 2));
  }
  
  /**
   * Converts a RGB color to a HSL representation.
   */
  public static function rgb2hsl($color) {
    $color['r'] = $color['r'] / 255;
    $color['g'] = $color['g'] / 255;
    $color['b'] = $color['b'] / 255;
    
    $min = min($color['r'], $color['g'], $color['b']);
    $max = max($color['r'], $color['g'], $color['b']);
    $d = $max - $min;
    
    $h = 0;
    $s = 0;
    $l = ($min + $max) / 2;
    
    if ($d != 0) {
      $s = $l < 0.5 ? $d / ($min + $max) : $d / (2 - $max - $min);
      $dr = ((($max - $color['r']) / 6) + ($d / 2)) / $d;
      $dg = ((($max - $color['g']) / 6) + ($d / 2)) / $d;
      $db = ((($max - $color['b']) / 6) + ($d / 2)) / $d;
      
      $h = ($color['r'] == $max) ? $db - $dg : ($color['g'] == $max) ? (1.0 / 3.0) + $dr - $db : (2.0 / 3.0) + $dg - $dr;
      if ($h < 0) {
        $h += 1;
      }
      if ($h > 1) {
        $h -= 1;
      }
    }
    
    return array('h' => $h, 's' => $s, 'l' => $l);
  }
  
  /**
   * Converts a RGB color to the XYZ color space.
   *
   * http://en.wikipedia.org/wiki/SRGB
   */
  public static function rgb2xyz($color) {
    $color['r'] = $color['r'] / 255;
    $color['g'] = $color['g'] / 255;
    $color['b'] = $color['b'] / 255;
    
    $color['r'] = ($color['r'] > 0.04045) ? pow(($color['r'] + 0.055) / 1.055, 2.4) : $color['r'] / 12.92;
    $color['g'] = ($color['g'] > 0.04045) ? pow(($color['g'] + 0.055) / 1.055, 2.4) : $color['g'] / 12.92;
    $color['b'] = ($color['b'] > 0.04045) ? pow(($color['b'] + 0.055) / 1.055, 2.4) : $color['b'] / 12.92;
    
    $color['r'] = $color['r'] * 100;
    $color['g'] = $color['g'] * 100;
    $color['b'] = $color['b'] * 100;
    
    return array(
      'x' => $color['r'] * 0.4124 + $color['g'] * 0.3576 + $color['b'] * 0.1805,
      'y' => $color['r'] * 0.2126 + $color['g'] * 0.7152 + $color['b'] * 0.0722,
      'z' => $color['r'] * 0.0193 + $color['g'] * 0.1192 + $color['b'] * 0.9505
    );
  }
  
  /**
   * Converts a XYZ color to the CIELAB color space.
   *
   * Unlike RGB and CMYK color models, CIELAB is designed to approximate human
   * vision and serve as a device-independent model.
   */
  public static function xyz2cielab($color) {
    $color['x'] = $color['x'] / 95.047;
    $color['y'] = $color['y'] / 100.000;
    $color['z'] = $color['z'] / 108.883;
    
    $color['x'] = ($color['x'] > 0.008856) ? pow($color['x'], 1.0 / 3.0) : (7.787 * $color['x']) + (16.0 / 116.0);
    $color['y'] = ($color['y'] > 0.008856) ? pow($color['y'], 1.0 / 3.0) : (7.787 * $color['y']) + (16.0 / 116.0);
    $color['z'] = ($color['z'] > 0.008856) ? pow($color['z'], 1.0 / 3.0) : (7.787 * $color['z']) + (16.0 / 116.0);
    
    return array(
      'l' => (116.0 * $color['y']) - 16.0,
      'a' => 500 * ($color['x'] - $color['y']),
      'b' => 200 * ($color['y'] - $color['z'])
    );
  }
}
