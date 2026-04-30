<?php
/**
 * forecast_api.php
 * Single API endpoint — returns forecast JSON based on user tier.
 *
 * GET  /forecast_api.php                    → today, user's rashi from session
 * GET  /forecast_api.php?date=2025-06-15    → specific date (paid only for future)
 * GET  /forecast_api.php?rashi=Mesh         → override rashi (for testing)
 * GET  /forecast_api.php?mock=guest|free|paid  → simulate tier (remove in production)
 *
 * Response: JSON { tier, data } always — never a PHP error page
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once 'ForecastCalculator_step4.php';
require_once 'UserTier.php';
require_once 'ForecastTiers.php';

// ── MOCK MODE (testing only — remove in production) ───────────────────────────
if (isset($_GET['mock'])) {
    $rashi = $_GET['rashi'] ?? 'Mesh';
    match($_GET['mock']) {
        'paid'  => UserTier::mockPaid($_GET['plan'] ?? 'monthly', $rashi),
        'free'  => UserTier::mockFree(1, $rashi),
        default => UserTier::mockGuest(),
    };
}

// ── Bootstrap ─────────────────────────────────────────────────────────────────
try {
    $tier   = new UserTier();
    $filter = new ForecastTiers($tier);

    // ── Build panchang input ─────────────────────────────────────────────────
    // TODO: Replace this mock panchang with your real PanchangCalculator output
    // $panchang = (new PanchangCalculator())->calculate();
    $panchang = buildMockPanchang();

    // ── Date for planet calculations ─────────────────────────────────────────
    $date  = $_GET['date'] ?? date('Y-m-d');
    // Paid users can request future dates; free/guest get today only
    if (!$tier->isPaid() && $date !== date('Y-m-d')) {
        $date = date('Y-m-d');
    }
    [$year, $month, $day] = array_map('intval', explode('-', $date));

    // ── Generate full forecast ────────────────────────────────────────────────
    $fc      = new ForecastCalculator();
    $full    = $fc->generate($panchang, $year, $month, $day);

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
    ]);
}

// ── MOCK PANCHANG (replace with real PanchangCalculator) ──────────────────────
// This provides sample panchang data so the API works standalone for testing.
// In production, remove this function and use your real PanchangCalculator.

function buildMockPanchang(): array {
    $weekdays = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
    $vara     = $weekdays[date('w')];

    return [
        // Nakshatra
        'nakshatra_name'   => 'Rohini',
        'nakshatra_gu'     => 'રોહિણી',
        // Moon & Sun rashi
        'moon_rashi'       => 'Vrishabha',
        'moon_rashi_gu'    => 'વૃષભ',
        'sun_rashi'        => 'Mesh',
        'sun_rashi_gu'     => 'મેષ',
        // Vara (weekday)
        'vara'             => $vara,
        'vara_gu'          => varaGu($vara),
        // Tithi
        'tithi_name'       => 'Panchami',
        'tithi_gu'         => 'પંચમી',
        'tithi_number'     => 5,
        'paksha'           => 'Shukla',
        'paksha_gu'        => 'શુક્લ',
        // Yoga
        'yoga'             => 'Siddhi',
        'yoga_gu'          => 'સિદ્ધિ',
    ];
}

function varaGu(string $vara): string {
    return [
        'Sunday'   =>'રવિવાર','Monday'   =>'સોમવાર','Tuesday'  =>'મંગળવાર',
        'Wednesday'=>'બુધવાર','Thursday' =>'ગુરુવાર','Friday'   =>'શુક્રવાર',
        'Saturday' =>'શનિવાર',
    ][$vara] ?? $vara;
}
