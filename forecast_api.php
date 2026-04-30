<?php
/**
 * forecast_api.php — updated to use real PanchangCalculator
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'ForecastCalculator.php';
require_once 'UserTier.php';
require_once 'ForecastTiers.php';
require_once 'PanchangCalculator.php';

// ── MOCK MODE (testing only) ──────────────────────────────────────────────────
if (isset($_GET['mock'])) {
    $rashi = $_GET['rashi'] ?? 'Mesh';
    match($_GET['mock']) {
        'paid'  => UserTier::mockPaid($_GET['plan'] ?? 'monthly', $rashi),
        'free'  => UserTier::mockFree(1, $rashi),
        default => UserTier::mockGuest(),
    };
}

try {
    $tier   = new UserTier();
    $filter = new ForecastTiers($tier);

    // ── Date ─────────────────────────────────────────────────────────────────
    $date  = $_GET['date'] ?? date('Y-m-d');
    if (!$tier->isPaid() && $date !== date('Y-m-d')) {
        $date = date('Y-m-d');
    }
    [$year, $month, $day] = array_map('intval', explode('-', $date));

    // ── Real panchang ────────────────────────────────────────────────────────
    $pc       = new PanchangCalculator();
    $panchang = $pc->calculate($year, $month, $day);

    // ── Generate forecast ────────────────────────────────────────────────────
    $fc   = new ForecastCalculator();
    $full = $fc->generate($panchang, $year, $month, $day);

    // ── Filter by tier ────────────────────────────────────────────────────────
    $rashi  = $_GET['rashi'] ?? $tier->getUserRashi();
    $output = $filter->filter($full, $rashi);

    echo json_encode([
        'success' => true,
        'tier'    => $tier->getTier(),
        'data'    => $output,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
        'line'    => $e->getLine(),
        'file'    => basename($e->getFile()),
    ]);
}
?>