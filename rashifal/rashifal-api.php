<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

$CLIENT_ID = '9dd3b43d-2d38-482b-a2eb-7aa8e970a6a2';
$CLIENT_SECRET = '2eHjEQT6hCUOYGN94YUOfkCTMu9xS8dKm5UQraNO';

$sign = strtolower(trim($_POST['sign'] ?? 'aries'));

// BUG FIX 1: HTML sends prediction type (general/career/health/love) as 'category',
// NOT as 'type'. The old code was reading $_POST['type'] which receives the period
// (daily/weekly/monthly/yearly) — wrong field entirely.
$prediction_type = strtolower(trim($_POST['category'] ?? 'general'));

$valid_signs = ['aries','taurus','gemini','cancer','leo','virgo','libra','scorpio','sagittarius','capricorn','aquarius','pisces'];

// BUG FIX 2: YAML spec for /horoscope/daily/advanced says:
// Enum `all` `general` `health` `career` `love` — 'all' was missing from the original list.
$valid_types = ['all','general','career','health','love'];

if (!in_array($sign, $valid_signs, true)) {
    echo json_encode(['error' => 'Invalid sign', 'sign' => $sign, 'valid_signs' => $valid_signs]);
    exit;
}

if (!in_array($prediction_type, $valid_types, true)) {
    echo json_encode([
        'error'       => 'Invalid prediction type',
        'type'        => $prediction_type,
        'valid_types' => $valid_types
    ]);
    exit;
}

// BUG FIX 3: Correct namespace is Prokerala\Api\Prediction\Service (not Horoscope\Service).
// The YAML tags this endpoint under "Prediction", which mirrors the SDK namespace.
use GuzzleHttp\Client as PsrHttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Prokerala\Common\Api\Authentication\Oauth2;
use Prokerala\Common\Api\Client;
use Prokerala\Api\Prediction\Service\DailyPredictionAdvanced; // ← fixed namespace

$psr17Factory = new Psr17Factory();
$httpClient   = new PsrHttpClient();

$authClient = new Oauth2($CLIENT_ID, $CLIENT_SECRET, $httpClient, $psr17Factory, $psr17Factory);
$client     = new Client($authClient, $httpClient, $psr17Factory);

$timezone = 'Asia/Kolkata';
$tz       = new DateTimeZone($timezone);

// BUG FIX 4: YAML requires ISO 8601 datetime (YYYY-MM-DDTHH:MM:SSZ).
// DateTimeImmutable with a DateTimeZone already satisfies this via the SDK,
// but we store the formatted string for the response for clarity.
$datetime          = new DateTimeImmutable('now', $tz);
$datetime_formatted = $datetime->format('Y-m-d\TH:i:sP'); // e.g. 2025-04-01T14:30:00+05:30

try {
    $horoscopeClass = new DailyPredictionAdvanced($client);

    // process() takes: DateTimeImmutable $datetime, string $sign, string $type
    // $prediction_type = general|career|health|love|all  (per YAML)
    $result = $horoscopeClass->process($datetime, $sign, $prediction_type);

    $dailyPredictions  = $result->getDailyPredictions();
    $matchedPrediction = null;

    foreach ($dailyPredictions as $dailyPrediction) {
        if (strtolower($dailyPrediction->getSign()->getName()) === $sign) {
            foreach ($dailyPrediction->getPredictions() as $prediction) {
                if ($prediction_type === 'all' || strtolower($prediction->getType()) === $prediction_type) {
                    $matchedPrediction = [
                        'sign'        => $dailyPrediction->getSign()->getName(),
                        'type'        => $prediction->getType(),
                        'prediction'  => $prediction->getPrediction()  ?? '',
                        'seek'        => $prediction->getSeek()        ?? '',
                        'challenge'   => $prediction->getChallenge()   ?? '',
                        'insight'     => $prediction->getInsight()     ?? '',
                    ];
                    if ($prediction_type !== 'all') break 2;
                }
            }
        }
    }

    echo json_encode([
        'success'          => true,
        'sign'             => $sign,
        'prediction_type'  => $prediction_type,
        'datetime'         => $datetime_formatted,
        'rashifal'         => $matchedPrediction ?: 'No matching prediction found'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
        'code'    => $e->getCode(),
    ], JSON_PRETTY_PRINT);
}
?>
