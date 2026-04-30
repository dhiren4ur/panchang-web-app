<?php
/**
 * FreeHoroscopeAPI - Daily Horoscope Test
 * No API key, no credits, no auth needed!
 * Docs: https://freehoroscopeapi.com/
 */

// ─── CONFIG ──────────────────────────────────────────────────────────────────

$sign = 'aries'; // Change to any sign to test

// Available signs:
// aries, taurus, gemini, cancer, leo, virgo,
// libra, scorpio, sagittarius, capricorn, aquarius, pisces

// ─── FETCH ───────────────────────────────────────────────────────────────────

$url = "https://freehoroscopeapi.com/api/v1/get-horoscope/daily?sign=" . urlencode($sign);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_HTTPHEADER     => ['Accept: application/json'],
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// ─── DISPLAY ─────────────────────────────────────────────────────────────────

echo "=== FreeHoroscopeAPI - Daily Horoscope Test ===\n\n";
echo "URL      : $url\n";
echo "HTTP Code: $httpCode\n\n";

if ($httpCode !== 200) {
    die("Request failed (HTTP $httpCode):\n$response\n");
}

$result = json_decode($response, true);

if (empty($result['data'])) {
    echo "Unexpected response:\n";
    print_r($result);
    exit(1);
}

$data = $result['data'];

echo "--- Result ---\n";
echo "Sign  : {$data['sign']}\n";
echo "Period: {$data['period']}\n";
echo "Date  : {$data['date']}\n\n";
echo "Prediction:\n";
echo wordwrap($data['horoscope'], 80, "\n", true) . "\n";

echo "\n--- Raw JSON ---\n";
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
