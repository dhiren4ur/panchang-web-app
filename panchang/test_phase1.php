<?php

require 'core/Julian.php';
require 'core/DeltaT.php';

$jd = Julian::toJD(2026, 4, 22, 12, 0, 0);
$jdn = Julian::toJDN($jd);
$T = Julian::centuriesSinceJ2000($jd);

$deltaT = DeltaT::estimate(2026, 4);

echo "JD: $jd\n";
echo "JDN: $jdn\n";
echo "T: $T\n";
echo "DeltaT (sec): $deltaT\n";
echo "DeltaT (days): " . DeltaT::toDays($deltaT) . "\n";