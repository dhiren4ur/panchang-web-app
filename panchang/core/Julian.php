<?php
class Julian {

    public static function toJD($year, $month, $day, $hour = 0, $min = 0, $sec = 0) {

        if ($month <= 2) {
            $year -= 1;
            $month += 12;
        }

        $A = floor($year / 100);
        $B = 2 - $A + floor($A / 4);

        $JD = floor(365.25 * ($year + 4716))
            + floor(30.6001 * ($month + 1))
            + $day + $B - 1524.5;

        $fraction = ($hour + $min/60 + $sec/3600) / 24;

        return $JD + $fraction;
    }

    public static function toJDN($jd) {
        return floor($jd + 0.5);
    }

    public static function centuriesSinceJ2000($jd) {
        return ($jd - 2451545.0) / 36525.0;
    }
}