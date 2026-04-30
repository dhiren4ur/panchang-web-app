<?php

require 'core/Julian.php';
require 'core/Sun.php';
require 'core/Moon.php';

$jd = Julian::toJD(2026, 4, 22, 12, 0, 0);

$sun = Sun::apparentLongitude($jd);
$moon = Moon::apparentLongitude($jd);

echo "Sun Longitude: $sun\n";
echo "Moon Longitude: $moon\n";