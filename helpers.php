<?php
function component($path, $data = [])
{
    extract($data, EXTR_SKIP);
    include __DIR__ . "/components/$path.php";
}

function modal($path, $data = [])
{
    extract($data, EXTR_SKIP);
    include __DIR__ . "/modals/$path.php";
}

function isCli(): bool
{
    return php_sapi_name() === 'cli';
}

function jsonError($msg, $code = 500)
{
    if (isCli()) {
        throw new Exception("API Error ($code): $msg");
    }
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
    if (empty($credentials['refresh_token'])) return login($config);

    $payload = [
        'refresh_token' => $credentials['refresh_token'],
        'grantType' => 'refresh_token',
        'pattern' => 'monitor'
    ];

    $login_url = "{$config['api_base']}/login";
    $response = httpRequest($login_url, ['Content-Type: application/json'], $payload);
    $json = json_decode($response, true);

    if (!isset($json['data']['access_token'])) return login($config);

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

            if ($credentials['appId'] !== $config['app_id'] || $credentials['appSecret'] !== $config['app_secret']) {
                return login($config);
            }

            $expires_in = $credentials['expires_in'] ?? 3600;
            $timestamp = $cache['timestamp'] ?? 0;

            if (time() - $timestamp < $expires_in - 60) return $credentials;
            return refreshToken($config, $credentials);
        }
    }
    return login($config);
}

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

    $isUnauthorized = (isset($decoded['error']) && in_array($decoded['error'], ['invalid_token', 'unauthorized']));

    // Check HTTP code only if not in CLI
    if (!$isUnauthorized && !isCli()) {
        $isUnauthorized = (http_response_code() === 401);
    }

    if ($isUnauthorized) {
        $credentials = refreshToken($config, $credentials);
        $headers = generateHeaders($credentials, $params);
        $response = httpRequest($url, $headers);
    }

    return $response;
}
