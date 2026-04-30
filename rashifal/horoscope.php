<?php
/**
 * Prokerala Astrology API v2 - Daily Horoscope Test
 * Endpoint: GET /v2/horoscope/daily
 *
 * Replace CLIENT_ID and CLIENT_SECRET with your actual credentials.
 * Get them from: https://api.prokerala.com/account/client/create
 */

// ─── CONFIG ──────────────────────────────────────────────────────────────────

define('CLIENT_ID',     '9dd3b43d-2d38-482b-a2eb-7aa8e970a6a2');
define('CLIENT_SECRET', '2eHjEQT6hCUOYGN94YUOfkCTMu9xS8dKm5UQraNO');

define('TOKEN_URL', 'https://api.prokerala.com/token');
define('API_BASE',  'https://api.prokerala.com/v2');

// ─── INPUT PARAMS ────────────────────────────────────────────────────────────

// Zodiac sign (lowercase):
// aries, taurus, gemini, cancer, leo, virgo,
// libra, scorpio, sagittarius, capricorn, aquarius, pisces
$sign = 'aries';

// Date for which you want the horoscope (ISO 8601, with timezone offset)
// For today (India timezone):
$datetime = (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d\T00:00:00P');
// Or hardcode a specific date:
// $datetime = '2026-04-04T00:00:00+05:30';

// ─── STEP 1: GET ACCESS TOKEN ─────────────────────────────────────────────────

function getAccessToken(): string {
    $ch = curl_init(TOKEN_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'grant_type'    => 'client_credentials',
            'client_id'     => CLIENT_ID,
            'client_secret' => CLIENT_SECRET,
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        die("Token request failed (HTTP $httpCode):\n$response\n");
    }

    $data = json_decode($response, true);
    if (empty($data['access_token'])) {
        die("Token response missing access_token:\n$response\n");
    }

    return $data['access_token'];
}

// ─── STEP 2: CALL DAILY HOROSCOPE ENDPOINT ───────────────────────────────────

function getDailyHoroscope(string $token, string $sign, string $datetime): array {
    $url = API_BASE . '/horoscope/daily?' . http_build_query([
        'sign'     => $sign,
        'datetime' => $datetime,
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer $token",
            'Accept: application/json',
        ],
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        die("API request failed (HTTP $httpCode):\n$response\n");
    }

    return json_decode($response, true);
}

// ─── STEP 3: RUN & DISPLAY ───────────────────────────────────────────────────

echo "=== Prokerala Daily Horoscope Test ===\n\n";
echo "Sign     : $sign\n";
echo "DateTime : $datetime\n\n";

$token  = getAccessToken();
echo "Access Token: " . substr($token, 0, 30) . "...\n\n";

$result = getDailyHoroscope($token, $sign, $datetime);

if (($result['status'] ?? '') !== 'ok') {
    echo "Unexpected response:\n";
    print_r($result);
    exit(1);
}

$prediction = $result['data']['daily_prediction'];

echo "--- Result ---\n";
echo "Sign      : {$prediction['sign_name']} (ID: {$prediction['sign_id']})\n";
echo "Date      : {$prediction['date']}\n";
echo "Prediction:\n\n";
echo wordwrap($prediction['prediction'], 80, "\n", true) . "\n";

echo "\n--- Raw JSON ---\n";
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
