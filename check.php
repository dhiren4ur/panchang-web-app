<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'PanchangCalculator.php';

$pc   = new PanchangCalculator();
$data = $pc->calculate();

echo "<h3>Panchang output keys:</h3><pre>";
print_r(array_keys($data));
echo "</pre>";

echo "<h3>Full panchang data:</h3><pre>";
print_r($data);
echo "</pre>";
?>