<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

$client_id = '9dd3b43d-2d38-482b-a2eb-7aa8e970a6a2';
$client_secret = '2eHjEQT6hCUOYGN94YUOfkCTMu9xS8dKm5UQraNO';

$token_url = 'https://api.prokerala.com/token';
$api_base  = 'https://api.prokerala.com/v2/astrology';

$date      = $_POST['date'] ?? '';
$time      = $_POST['time'] ?? '';
$latitude  = $_POST['latitude'] ?? '21.1702';
$longitude = $_POST['longitude'] ?? '72.8311';
$type      = $_POST['type'] ?? 'panchang';

if (!$date || !$time) {
    echo json_encode(['error' => 'Date and time are required']);
    exit;
}

$token_data = [
    'grant_type' => 'client_credentials',
    'client_id' => $client_id,
    'client_secret' => $client_secret
];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $token_url,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($token_data),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded']
]);

$token_response = curl_exec($ch);
$token_info = json_decode($token_response, true);

if (!isset($token_info['access_token'])) {
    echo json_encode([
        'error' => 'Token failed',
        'debug' => $token_response
    ]);
    curl_close($ch);
    exit;
}

$access_token = $token_info['access_token'];
curl_close($ch);

$datetime = $date . 'T' . $time . ':00+05:30';
$params = [
    'datetime' => $datetime,
    'coordinates' => $latitude . ',' . $longitude,
    'ayanamsa' => 1
];

$url = $api_base . '/' . $type . '?' . http_build_query($params);

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $access_token,
        'Accept: application/json'
    ]
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo json_encode([
    'http_code' => $http_code,
    'response' => json_decode($response, true),
    'raw' => $response
], JSON_PRETTY_PRINT);
?>