<?php
class DeltaT {

    public static function estimate($year, $month) {

        $y = $year + ($month - 0.5) / 12;

        if ($y < 948) {
            $t = ($y - 2000) / 100;
            return 2177 + 497*$t + 44.1*$t*$t;
        }

        if ($y >= 948 && $y < 1600) {
            $t = ($y - 2000) / 100;
            return 102 + 102*$t + 25.3*$t*$t;
        }

        if ($y >= 1600 && $y < 2000) {
            $t = $y - 2000;
            return 62.92 + 0.32217*$t + 0.005589*$t*$t;
        }

        if ($y >= 2000 && $y < 2100) {
            $t = $y - 2000;
            return 62.92 + 0.32217*$t + 0.005589*$t*$t;
        }

        // fallback
        $t = ($y - 1820) / 100;
        return -20 + 32 * $t * $t;
    }

    public static function toDays($deltaT_seconds) {
        return $deltaT_seconds / 86400.0;
    }
}