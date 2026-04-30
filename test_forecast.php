<?php
/**
 * test_forecast.php
 * Tests 5-layer ForecastCalculator:
 *   Nakshatra + Chandra Bala + Surya Bala + Tithi + Graha Transit
 * Run: php test_forecast.php
 */

require_once 'PanchangCalculator.php';
require_once 'ForecastCalculator.php';

$pc       = new PanchangCalculator();
$today    = getdate();
$panchang = $pc->calculate($today['year'], $today['mon'], $today['mday']);

$fc     = new ForecastCalculator();
$result = $fc->generate($panchang, $today['year'], $today['mon'], $today['mday']);

// ── Header ────────────────────────────────────────────────────────────────────
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║        VEDIC DAILY FORECAST — 5 LAYER TEST (+ GRAHA)        ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

echo "Date      : {$result['date']}\n";
echo "Nakshatra : {$result['nakshatra']} ({$result['nakshatra_gu']}) — Lord: {$result['nakshatra_lord']} — Q: {$result['nakshatra_quality']}/4\n";
echo "Moon      : {$result['moon_rashi']} ({$result['moon_rashi_gu']})\n";
echo "Sun       : {$result['sun_rashi']} ({$result['sun_rashi_gu']})\n";
echo "Tithi     : {$result['paksha']} {$result['tithi_name']} — Lord: {$result['tithi_lord']} — Score: {$result['tithi_score']}/4\n";
echo "Yoga      : {$result['yoga']} ({$result['yoga_gu']}) — {$result['yoga_quality']}\n";
echo "Vara      : {$result['vara']} ({$result['vara_gu']})\n\n";

// ── Planet positions ──────────────────────────────────────────────────────────
echo "┌─────────────────────────────────────────────────────┐\n";
echo "│  CURRENT PLANET POSITIONS (Geocentric Approximate)  │\n";
echo "├──────────────┬─────────────────────────────────────┤\n";
$pnames = ['Mangal'=>'Mars(♂)','Budh'=>'Mercury(☿)','Guru'=>'Jupiter(♃)','Shukra'=>'Venus(♀)','Shani'=>'Saturn(♄)'];
foreach ($result['planet_rashis'] as $planet => $pos) {
    printf("│ %-13s │ %-35s │\n", $pnames[$planet], "{$pos['rashi']} ({$pos['rashi_gu']})");
}
echo "└──────────────┴─────────────────────────────────────┘\n\n";

// ── Score breakdown table ─────────────────────────────────────────────────────
echo "┌────────────────────┬──────┬──────┬──────┬──────┬──────┬──────────────────┐\n";
echo "│ Rashi              │ Nak  │ CB   │ SB   │ Gra  │ Tit  │ SCORE            │\n";
echo "│                    │ (10%)│ (30%)│ (20%)│ (30%)│ (10%)│                  │\n";
echo "├────────────────────┼──────┼──────┼──────┼──────┼──────┼──────────────────┤\n";

foreach ($result['forecasts'] as $rashi => $f) {
    $bar = str_repeat('★', $f['score']) . str_repeat('☆', 4 - $f['score']);
    printf("│ %-18s │ %d/4  │ %d/4  │ %d/4  │ %d/4  │ %d/4  │ %s %-9s │\n",
        $f['symbol'] . ' ' . $rashi,
        $f['nak_score'],
        $f['cb_score'],
        $f['sb_score'],
        $f['graha_score'],
        $f['tithi_score'],
        $bar,
        $f['rating_en']
    );
}
echo "└────────────────────┴──────┴──────┴──────┴──────┴──────┴──────────────────┘\n\n";

// ── Full 6-section forecast for Mesh (first rashi) ───────────────────────────
$rashi_to_show = 'Mesh';
$f = $result['forecasts'][$rashi_to_show];

echo "══════════════════════════════════════════════════════════════\n";
echo "{$f['symbol']} {$rashi_to_show} / {$f['rashi_gu']}  [{$f['rating_en']} — {$f['score']}/4]\n";
echo "══════════════════════════════════════════════════════════════\n\n";

$parts_en = array_values(array_filter(explode("\n\n", $f['forecast_en'])));
$labels = ['[1] Nakshatra','[2] Tithi','[3] Chandra Bala','[4] Surya Bala','[5] Graha Transit','[6] Vara'];
foreach ($labels as $i => $label) {
    if (!empty($parts_en[$i])) {
        echo "--- $label ---\n";
        // For graha transit, preserve newlines within section
        $text = $parts_en[$i];
        if (strpos($label, 'Graha') !== false) {
            echo $text . "\n\n";
        } else {
            echo wordwrap($text, 72, "\n", true) . "\n\n";
        }
    }
}

echo "=== TEST COMPLETE ===\n";
echo "Verify: Planet positions look correct for today's date?\n";
echo "        Graha section shows all 5 planets with house numbers?\n";
echo "        Scores change with graha layer vs before?\n";
