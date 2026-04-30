<?php
declare(strict_types=1);

class Sun {

    public static function apparentLongitude(float $jd): float {

        $T = ($jd - 2451545.0) / 36525.0;

        // Mean longitude (deg)
        $L0 = 280.46646 + 36000.76983*$T + 0.0003032*$T*$T;
        $L0 = self::normalize($L0);

        // Mean anomaly (deg)
        $M = 357.52911 + 35999.05029*$T - 0.0001537*$T*$T;
        $M = deg2rad($M);

        // Equation of center
        $C = (1.914602 - 0.004817*$T - 0.000014*$T*$T) * sin($M)
           + (0.019993 - 0.000101*$T) * sin(2*$M)
           + 0.000289 * sin(3*$M);

        // True longitude
        $trueLong = $L0 + $C;

        // Omega (longitude of ascending node of Moon)
        $Omega = deg2rad(125.04 - 1934.136 * $T);

        // Apparent longitude (deg)
        $lambda = $trueLong - 0.00569 - 0.00478 * sin($Omega);

        return self::normalize($lambda);
    }

    private static function normalize(float $angle): float {
        $angle = fmod($angle, 360.0);
        return ($angle < 0) ? $angle + 360.0 : $angle;
    }
}