<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', 0);

// ---- CORS & JSON headers ----
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ---- Hobacare account info ----
define('APP_ID', $_ENV['APP_ID']);
define('APP_SECRET', $_ENV['SECRET']);
define('USERNAME', $_ENV['HOBACARE_USERNAME']);
define('PASSWORD', $_ENV['HOBACARE_PASSWORD']);
define('LOGIN_URL', $_ENV['HOBACARE_URL']);

define('CREDENTIALS_FILE', __DIR__ . '/credentials.json');
define('CREDENTIALS_TTL', 3600); // 1 hour

// ---- Login and cache credentials ----
function login()
{
    $data = [
        'username' => USERNAME,
        'password' => PASSWORD,
        'pattern'  => 'monitor',
        'grantType' => 'password'
    ];

    $ch = curl_init(LOGIN_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        http_response_code(500);
        echo json_encode(['error' => curl_error($ch)]);
        exit();
    }

    $respJson = json_decode($response, true);
    if (!isset($respJson['data']['access_token'])) {
        http_response_code(500);
        echo json_encode(['error' => 'Login failed', 'response' => $respJson]);
        exit();
    }

    $credentials = [
        'appId' => APP_ID,
        'appSecret' => APP_SECRET,
        'access_token' => $respJson['data']['access_token'],
    ];

    file_put_contents(CREDENTIALS_FILE, json_encode([
        'credentials' => $credentials,
        'timestamp' => time()
    ]));

    return $credentials;
}

// ---- Get cached credentials or login ----
function getCredentials()
{
    if (file_exists(CREDENTIALS_FILE)) {
        $cache = json_decode(file_get_contents(CREDENTIALS_FILE), true);
        if (time() - $cache['timestamp'] < CREDENTIALS_TTL) {
            return $cache['credentials'];
        }
    }
    return login();
}

// ---- Flatten data for signature ----
function flattenObject($data)
{
    $pairs = [];
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            $pairs[] = $key . "=" . implode("=", $value);
        } else if (is_object($value)) {
            $nested = flattenObject((array)$value);
            foreach ($nested as $v) {
                $pairs[] = $key . "=" . $v;
            }
        } else {
            $pairs[] = $key . "=" . $value;
        }
    }
    return $pairs;
}

function generateAuthHeaders($credentials, $data = [])
{
    $timestamp = time();
    $serialized = "";

    if (count($data) > 0) {
        $flattened = flattenObject($data);
        sort($flattened);
        $serialized = implode("#", $flattened) . "#";
    }

    $signatureString = $credentials['appSecret'] . "#" . $timestamp . "#" . $serialized;
    $signature = strtoupper(sha1($signatureString));

    return [
        'appid' => $credentials['appId'],
        'timestamp' => $timestamp,
        'signature' => $signature
    ];
}

// ---- Proxy request ----
$credentials = getCredentials();

// Read endpoint and params
$endpoint = $_GET['endpoint'] ?? null;
if (!$endpoint) {
    echo json_encode(['error' => 'Missing endpoint']);
    exit();
}

$params = $_GET;
unset($params['endpoint']); // remove endpoint key

$authHeaders = generateAuthHeaders($credentials, $params);

// Build Hobacare URL
$url = "https://radarconsole.com/prod-api/thirdparty/v2/$endpoint";
if (!empty($params)) {
    $url .= "?" . http_build_query($params);
}

// Execute cURL request
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// Set headers including Authorization
$headers = [];
foreach ($authHeaders as $k => $v) {
    $headers[] = "$k: $v";
}
$headers[] = "Content-Type: application/json";
$headers[] = "Authorization: Bearer " . $credentials['access_token'];

curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$response = curl_exec($ch);
if (curl_errno($ch)) {
    http_response_code(500);
    echo json_encode(['error' => curl_error($ch)]);
    exit();
}

// Decode response and wrap in standard structure
$decoded = json_decode($response, true);
if ($decoded === null) {
    echo json_encode(['data' => [], 'raw' => $response]);
    exit();
}

echo json_encode([
    'data' => $decoded['data'] ?? $decoded
]);
