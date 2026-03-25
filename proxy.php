<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/src/Database.php';              // <- Database class
require_once __DIR__ . '/src/Repositories/DeviceRepository.php';
require_once __DIR__ . '/src/Repositories/SleepReportRepository.php';
require_once __DIR__ . '/src/Repositories/UserDeviceRepository.php';
require_once __DIR__ . '/src/Services/SleepReportService.php';

use App\Database;
use App\Repositories\DeviceRepository;
use App\Repositories\SleepReportRepository;
use App\Repositories\UserDeviceRepository;
use App\Services\SleepReportService;

/* ---------------- ENV LOADER ---------------- */

function loadEnv($path)
{
    if (!file_exists($path)) return;

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        [$name, $value] = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
        putenv("$name=$value");
    }
}
loadEnv(__DIR__ . '/.env');

/* ---------------- HEADERS ---------------- */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

/* ---------------- CONFIG ---------------- */
$config = [
    'app_id' => getenv('APP_ID'),
    'app_secret' => getenv('SECRET'),
    'username' => getenv('HOBACARE_USERNAME'),
    'password' => getenv('HOBACARE_PASSWORD'),
    'api_base' => getenv('BASE_URL'),
    'credentials_file' => __DIR__ . '/credentials.json'
];

/* ---------------- UTIL ---------------- */
function jsonError($msg, $code = 500)
{
    http_response_code($code);
    echo json_encode(['error' => $msg]);
    exit();
}

function httpRequest($url, $headers = [], $post = null)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CUSTOMREQUEST => $post ? 'POST' : 'GET',
        CURLOPT_POSTFIELDS => $post ? json_encode($post) : null
    ]);

    $response = curl_exec($ch);
    if (curl_errno($ch)) jsonError(curl_error($ch));

    return $response;
}

/* ---------------- AUTH ---------------- */
function login($config)
{
    $payload = [
        'username' => $config['username'],
        'password' => $config['password'],
        'pattern' => 'monitor',
        'grantType' => 'password'
    ];

    $login_url = "{$config['api_base']}/login";
    $response = httpRequest($login_url, ['Content-Type: application/json'], $payload);
    $json = json_decode($response, true);

    if (!isset($json['data']['access_token'])) jsonError('Login failed');

    $credentials = [
        'appId' => $config['app_id'],
        'appSecret' => $config['app_secret'],
        'access_token' => $json['data']['access_token'],
        'refresh_token' => $json['data']['refresh_token'] ?? null,
        'token_type' => $json['data']['token_type'] ?? 'bearer',
        'expires_in' => $json['data']['expires_in'] ?? 3600
    ];

    file_put_contents($config['credentials_file'], json_encode([
        'credentials' => $credentials,
        'timestamp' => time()
    ]));

    return $credentials;
}

function refreshToken($config, $credentials)
{
    if (empty($credentials['refresh_token'])) {
        return login($config);
    }

    $payload = [
        'refresh_token' => $credentials['refresh_token'],
        'grantType' => 'refresh_token',
        'pattern' => 'monitor'
    ];

    $login_url = "{$config['api_base']}/login";
    $response = httpRequest($login_url, ['Content-Type: application/json'], $payload);
    $json = json_decode($response, true);

    if (!isset($json['data']['access_token'])) {
        return login($config);
    }

    $newCredentials = [
        'appId' => $config['app_id'],
        'appSecret' => $config['app_secret'],
        'access_token' => $json['data']['access_token'],
        'refresh_token' => $json['data']['refresh_token'] ?? $credentials['refresh_token'],
        'token_type' => $json['data']['token_type'] ?? 'bearer',
        'expires_in' => $json['data']['expires_in'] ?? 3600
    ];

    file_put_contents($config['credentials_file'], json_encode([
        'credentials' => $newCredentials,
        'timestamp' => time()
    ]));

    return $newCredentials;
}

function getCredentials($config)
{
    if (file_exists($config['credentials_file'])) {
        $cache = json_decode(file_get_contents($config['credentials_file']), true);
        if ($cache && isset($cache['credentials'])) {
            $credentials = $cache['credentials'];
            $expires_in = $credentials['expires_in'] ?? 3600;
            $timestamp = $cache['timestamp'] ?? 0;

            if (time() - $timestamp < $expires_in - 60) {
                return $credentials;
            }

            return refreshToken($config, $credentials);
        }
    }

    return login($config);
}

/* ---------------- SIGNATURE ---------------- */
function flattenParams($data)
{
    $pairs = [];
    foreach ($data as $key => $value) {
        if (is_array($value)) $pairs[] = $key . '=' . implode('=', $value);
        elseif (is_object($value)) foreach (flattenParams((array)$value) as $v) $pairs[] = $key . '=' . $v;
        else $pairs[] = "$key=$value";
    }
    return $pairs;
}

function generateHeaders($credentials, $params)
{
    $timestamp = time();
    $serialized = '';
    if ($params) {
        $flat = flattenParams($params);
        sort($flat);
        $serialized = implode('#', $flat) . '#';
    }

    $signature = strtoupper(sha1($credentials['appSecret'] . "#$timestamp#$serialized"));

    return [
        "appid: {$credentials['appId']}",
        "timestamp: $timestamp",
        "signature: $signature",
        "Authorization: " . ucfirst($credentials['token_type']) . " {$credentials['access_token']}",
        "Content-Type: application/json"
    ];
}

function apiRequest($config, $endpoint, $params = [])
{
    $credentials = getCredentials($config);

    $url = $config['api_base'] . $endpoint;
    if ($params) $url .= '?' . http_build_query($params);

    $headers = generateHeaders($credentials, $params);
    $response = httpRequest($url, $headers);
    $decoded = json_decode($response, true);

    // Check for invalid token or unauthorized response
    if (
        isset($decoded['error']) && in_array($decoded['error'], ['invalid_token', 'unauthorized'])
        || http_response_code() === 401
    ) {

        // Refresh token or full login
        $credentials = refreshToken($config, $credentials);

        // Re-generate headers and retry
        $headers = generateHeaders($credentials, $params);
        $response = httpRequest($url, $headers);
    }

    return $response;
}

$pdo = Database::connection();

$sleepService = new SleepReportService(
    new SleepReportRepository(),
    new DeviceRepository(),
    new UserDeviceRepository(),
    $pdo,
    $config
);

$endpoint = $_GET['endpoint'] ?? null;
if (!$endpoint) jsonError('Missing endpoint', 400);

$params = $_GET;
unset($params['endpoint']);

if ($endpoint === 'radar/monitor/report' && isset($params['uid'], $params['date'])) {
    try {
        $result = $sleepService->get($params['uid'], $params['date']);
        echo json_encode($result);
    } catch (Exception $e) {
        jsonError($e->getMessage(), 500);
    }
    exit;
}

$response = apiRequest($config, $endpoint, $params);
echo $response;
