<?php
/**
 * horoscope_api.php
 * Returns today's forecast as JSON for horoscope.html to consume
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once 'PanchangCalculator.php';
require_once 'ForecastCalculator.php';

try {
    $pc       = new PanchangCalculator();
    $today    = getdate();
    $panchang = $pc->calculate($today['year'], $today['mon'], $today['mday']);
    $fc       = new ForecastCalculator();
    $result   = $fc->generate($panchang);

    // Add extra panchang fields needed by display
    $result['tithi']     = $panchang['tithi_name'];
    $result['tithi_gu']  = $panchang['tithi_gu'];
    $result['paksha']    = $panchang['paksha'];
    $result['paksha_gu'] = $panchang['paksha_gu'];
    $result['sunrise']   = $panchang['sunrise'];
    $result['samvat']    = $panchang['samvat'];
    $result['lunar_month_gu'] = $panchang['lunar_month_gu'];
    $result['date_str']  = date('d M Y');
    $result['date_full'] = date('d F Y');

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
