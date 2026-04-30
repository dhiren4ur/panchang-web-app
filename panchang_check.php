<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'PanchangCalculator.php';

$pc   = new PanchangCalculator();

// Passing today's date as 3 arguments
$year  = (int)date('Y');
$month = (int)date('m');
$day   = (int)date('d');

$data = $pc->calculate($year, $month, $day);

echo "<h3>Panchang output keys:</h3><pre>";
print_r(array_keys($data));
echo "</pre>";

echo "<h3>Full panchang data:</h3><pre>";
print_r($data);
echo "</pre>";
?>