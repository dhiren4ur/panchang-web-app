<?php
/**
 * test_forecast.php
 * Tests updated ForecastCalculator (Nakshatra + Chandra Bala + Surya Bala + Tithi)
 * Run: php test_forecast.php
 */

require_once 'PanchangCalculator.php';
require_once 'ForecastCalculator.php';

// ── Get today's panchang ──────────────────────────────────────────────────────
$pc       = new PanchangCalculator();
$today    = getdate();
$panchang = $pc->calculate($today['year'], $today['mon'], $today['mday']);

// ── Generate forecast ─────────────────────────────────────────────────────────
$fc     = new ForecastCalculator();
$result = $fc->generate($panchang);

// ── Header ────────────────────────────────────────────────────────────────────
echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║          VEDIC DAILY FORECAST — 4 LAYER TEST            ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n\n";

echo "Date      : {$result['date']}\n";
echo "Nakshatra : {$result['nakshatra']} ({$result['nakshatra_gu']}) — Lord: {$result['nakshatra_lord']} — Quality: {$result['nakshatra_quality']}/4\n";
echo "Moon Rashi: {$result['moon_rashi']} ({$result['moon_rashi_gu']})\n";
echo "Sun Rashi : {$result['sun_rashi']} ({$result['sun_rashi_gu']})\n";
echo "Tithi     : {$result['paksha']} {$result['tithi_name']} ({$result['tithi_gu']}) — Lord: {$result['tithi_lord']} — Score: {$result['tithi_score']}/4\n";
echo "Yoga      : {$result['yoga']} ({$result['yoga_gu']}) — {$result['yoga_quality']}\n";
echo "Vara      : {$result['vara']} ({$result['vara_gu']})\n\n";

// ── Score breakdown table ─────────────────────────────────────────────────────
echo "┌──────────────────┬────────┬────────┬────────┬────────┬──────────────────┐\n";
echo "│ Rashi            │ Nak(15)│ CB(40) │ SB(30) │ Ti(15) │ SCORE            │\n";
echo "├──────────────────┼────────┼────────┼────────┼────────┼──────────────────┤\n";

foreach ($result['forecasts'] as $rashi => $f) {
    $bar = str_repeat('★', $f['score']) . str_repeat('☆', 4 - $f['score']);
    printf("│ %-16s │  %d/4   │  %d/4   │  %d/4   │  %d/4   │ %s %-9s │\n",
        $f['symbol'] . ' ' . $rashi,
        $f['nak_score'],
        $f['cb_score'],
        $f['sb_score'],
        $f['tithi_score'],
        $bar,
        $f['rating_en']
    );
}
echo "└──────────────────┴────────┴────────┴────────┴────────┴──────────────────┘\n\n";

// ── Full forecast for first 3 rashis ─────────────────────────────────────────
$show = array_slice($result['forecasts'], 0, 3, true);

foreach ($show as $rashi => $f) {
    echo "══════════════════════════════════════════════════════════\n";
    echo "{$f['symbol']} {$rashi} / {$f['rashi_gu']}  [{$f['rating_en']} — {$f['score']}/4]\n";
    echo "  CB: {$f['chandra_bala']} | SB: {$f['surya_bala']}\n";
    echo "══════════════════════════════════════════════════════════\n\n";

    $parts_en = array_values(array_filter(explode("\n\n", $f['forecast_en'])));
    $parts_gu = array_values(array_filter(explode("\n\n", $f['forecast_gu'])));

    $labels = ['[1] Nakshatra', '[2] Tithi', '[3] Chandra Bala', '[4] Surya Bala', '[5] Vara'];
    foreach ($labels as $i => $label) {
        if (!empty($parts_en[$i])) {
            echo "--- $label ---\n";
            echo "EN: " . wordwrap($parts_en[$i], 72, "\n    ", true) . "\n";
            if (!empty($parts_gu[$i])) {
                echo "GU: {$parts_gu[$i]}\n";
            }
            echo "\n";
        }
    }
}

echo "=== TEST COMPLETE ===\n";
echo "Check: 1) All 12 rashis have different CB/SB scores?\n";
echo "       2) Tithi score same for all? (should be)\n";
echo "       3) Nakshatra score same for all? (should be)\n";
echo "       4) 5 sections in each forecast?\n";
