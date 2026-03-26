<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/bootstrap.php';

use App\Logger;

$host = $argv[1] ?? '0.0.0.0';
$port = $argv[2] ?? 1883;

$mqttUsername = $_ENV['MQTT_USERNAME'] ?? '';
$mqttPassword = $_ENV['MQTT_PASSWORD'] ?? '';

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

$server = @stream_socket_server("tcp://$host:$port", $errno, $errstr);
if (!$server) {
    die("Failed to create server: $errstr ($errno)\n");
}

stream_set_blocking($server, false);

Logger::info("MQTT Server started on $host:$port");
Logger::info("Waiting for connections...");

$crmCache = [];
$crmCacheTtl = 300;

function getTargetFromCrm(string $idLicenca): ?string
{
    global $crmCache, $crmCacheTtl;

    if (isset($crmCache[$idLicenca]) && $crmCache[$idLicenca]['expires'] > time()) {
        return $crmCache[$idLicenca]['url'];
    }

    $crmUrl = $_ENV['CRM_URL'] ?? 'https://crm.hitcare.net/api/get.url.php';
    $postData = http_build_query(['id_licenca' => $idLicenca]);

    $ch = curl_init($crmUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        $url = trim($response);
        $crmCache[$idLicenca] = [
            'url' => $url,
            'expires' => time() + $crmCacheTtl
        ];
        return $url;
    }

    Logger::error("CRM request failed for license $idLicenca: HTTP $httpCode");
    return null;
}

function parseMqttPacket(string $data): ?array
{
    if (strlen($data) < 2) return null;

    $firstByte = ord($data[0]);
    $packetType = $firstByte >> 4;

    $pos = 1;
    $remainingLen = 0;
    $multiplier = 1;

    while ($pos < strlen($data)) {
        $digit = ord($data[$pos++]);
        $remainingLen += ($digit & 0x7F) * $multiplier;
        $multiplier *= 128;
        if (($digit & 0x80) === 0) break;
    }

    $totalLen = $pos + $remainingLen;
    if (strlen($data) < $totalLen) return null;

    return [
        'type' => $packetType,
        'remaining_len' => $remainingLen,
        'offset' => $pos,
        'raw' => substr($data, 0, $totalLen)
    ];
}

function parseConnect(string $packet, int $offset): array
{
    $pos = $offset;

    if ($pos + 4 > strlen($packet)) {
        return ['valid' => false, 'client_id' => '', 'username' => '', 'password' => ''];
    }

    $protocolLen = unpack('n', substr($packet, $pos, 2))[1];
    $pos += 2;

    if ($pos + $protocolLen > strlen($packet)) {
        return ['valid' => false, 'client_id' => '', 'username' => '', 'password' => ''];
    }

    $protocol = substr($packet, $pos, $protocolLen);
    $pos += $protocolLen;

    if ($protocol !== 'MQTT' && $protocol !== 'MQIsdp') {
        return ['valid' => false, 'client_id' => '', 'username' => '', 'password' => ''];
    }

    if ($pos >= strlen($packet)) {
        return ['valid' => false, 'client_id' => '', 'username' => '', 'password' => ''];
    }

    $level = ord($packet[$pos]);
    $pos++;

    $flags = ord($packet[$pos] ?? '');
    $pos++;

    $keepAlive = unpack('n', substr($packet, $pos, 2))[1] ?? 0;
    $pos += 2;

    if ($pos + 2 > strlen($packet)) {
        return ['valid' => false, 'client_id' => '', 'username' => '', 'password' => ''];
    }

    $clientIdLen = unpack('n', substr($packet, $pos, 2))[1] ?? 0;

    if ($clientIdLen === 0) {
        $clientId = '';
        $pos += 2;
    } else {
        $pos += 2;
        if ($pos + $clientIdLen > strlen($packet)) {
            return ['valid' => false, 'client_id' => '', 'username' => '', 'password' => ''];
        }
        $clientId = substr($packet, $pos, $clientIdLen);
        $pos += $clientIdLen;
    }

    $username = '';
    $password = '';

    if (($flags & 0x80) !== 0) {
        if ($pos + 2 <= strlen($packet)) {
            $userLen = unpack('n', substr($packet, $pos, 2))[1] ?? 0;
            $pos += 2;
            if ($userLen > 0 && $pos + $userLen <= strlen($packet)) {
                $username = substr($packet, $pos, $userLen);
                $pos += $userLen;
            }
        }
    }

    if (($flags & 0x40) !== 0) {
        if ($pos + 2 <= strlen($packet)) {
            $passLen = unpack('n', substr($packet, $pos, 2))[1] ?? 0;
            $pos += 2;
            if ($passLen > 0 && $pos + $passLen <= strlen($packet)) {
                $password = substr($packet, $pos, $passLen);
            }
        }
    }

    return [
        'valid' => true,
        'client_id' => $clientId,
        'username' => $username,
        'password' => $password
    ];
}

function parsePublish(string $packet, int $offset): ?array
{
    $pos = $offset;

    if ($pos + 2 > strlen($packet)) return null;

    $topicLen = unpack('n', substr($packet, $pos, 2))[1];
    $pos += 2;

    if ($pos + $topicLen > strlen($packet)) return null;

    $topic = substr($packet, $pos, $topicLen);
    $pos += $topicLen;

    if ($pos >= strlen($packet)) {
        return ['topic' => $topic, 'payload' => '', 'qos' => 0];
    }

    $flags = ord($packet[$pos]);
    $qos = ($flags >> 1) & 0x03;
    $pos++;

    if ($qos > 0 && $pos + 2 <= strlen($packet)) {
        $pos += 2;
    }

    $payload = ($pos < strlen($packet)) ? substr($packet, $pos) : '';

    return [
        'topic' => $topic,
        'payload' => $payload,
        'qos' => $qos
    ];
}

function parseSubscribe(string $packet, int $offset): string
{
    $pos = $offset + 2;
    $results = '';

    while ($pos < strlen($packet) - 1) {
        $topicLen = unpack('n', substr($packet, $pos, 2))[1] ?? 0;
        $pos += 2 + $topicLen + 1;
        $results .= pack('C', 0x00);
    }

    $msgId = substr($packet, $offset, 2);
    return pack('CC', 0x90, 2 + strlen($results)) . $msgId . $results;
}

$read = [$server];
$clients = [];

while (true) {
    $validRead = array_filter($read, fn($s) => is_resource($s) && get_resource_type($s) === 'stream');
    if (empty($validRead)) {
        $read = [$server];
        usleep(100000);
        continue;
    }

    $changed = @stream_select($validRead, $write, $except, 0, 200000);

    if ($changed === false) {
        $read = [$server];
        continue;
    }
    if ($changed === 0) {
        $read = [$server];
        continue;
    }

    $read = $validRead;

    foreach ($read as $socket) {
        if ($socket === $server) {
            $client = @stream_socket_accept($server, 0);
            if ($client) {
                $clientId = (int)$client;
                Logger::info("Client connected: $clientId");
                $clients[$clientId] = ['connected' => false, 'buffer' => ''];
                $read[] = $client;
            }
        } else {
            $clientId = (int)$socket;
            $data = @fread($socket, 8192);

            if ($data === '' || $data === false) {
                Logger::info("Client $clientId: Disconnected");
                $key = array_search($socket, $read);
                if ($key !== false) {
                    unset($read[$key]);
                    sort($read);
                }
                fclose($socket);
                unset($clients[$clientId]);
                $read = [$server];
                continue;
            }

            $clients[$clientId]['buffer'] .= $data;
            $buffer = $clients[$clientId]['buffer'];

            while (($packet = parseMqttPacket($buffer)) !== null) {
                $buffer = substr($buffer, strlen($packet['raw']));

                switch ($packet['type']) {
                    case 1:
                        $connect = parseConnect($packet['raw'], $packet['offset']);
                        if (!$connect['valid']) {
                            Logger::warn("Client $clientId: Invalid CONNECT packet, closing");
                            $key = array_search($socket, $read);
                            if ($key !== false) unset($read[$key]);
                            fclose($socket);
                            unset($clients[$clientId]);
                            $read = array_merge([$server], $read);
                            break;
                        }
                        if ($mqttUsername && $connect['username'] !== $mqttUsername) {
                            Logger::warn("Client $clientId: Invalid username '{$connect['username']}', closing");
                            $key = array_search($socket, $read);
                            if ($key !== false) unset($read[$key]);
                            fclose($socket);
                            unset($clients[$clientId]);
                            $read = array_merge([$server], $read);
                            break;
                        }
                        if ($mqttPassword && $connect['password'] !== $mqttPassword) {
                            Logger::warn("Client $clientId: Invalid password, closing");
                            $key = array_search($socket, $read);
                            if ($key !== false) unset($read[$key]);
                            fclose($socket);
                            unset($clients[$clientId]);
                            $read = array_merge([$server], $read);
                            break;
                        }
                        $clients[$clientId]['connected'] = true;
                        $response = pack('CC', 0x20, 0x02) . pack('Cn', 0x00, 0x00);
                        fwrite($socket, $response);
                        Logger::info("Client $clientId: CONNECT (user={$connect['username']}) -> CONNACK");
                        break;

                    case 3:
                        if ($clients[$clientId]['connected']) {
                            $publish = parsePublish($packet['raw'], $packet['offset']);
                            if ($publish) {
                                Logger::info("Client $clientId: PUBLISH topic={$publish['topic']}");
                                
                                $parts = explode('/', $publish['topic']);
                                if (count($parts) >= 2 && $parts[0] === 'radar') {
                                    $idLicenca = $parts[1] ?? null;
                                    $uidRadar = $parts[2] ?? 'unknown';
                                    
                                    if ($idLicenca) {
                                        $targetUrl = getTargetFromCrm($idLicenca);
                                        
                                        if ($targetUrl) {
                                            Logger::info("Client $clientId: [$idLicenca] -> $targetUrl/api/radar-data/ingest");
                                        } else {
                                            Logger::warn("Client $clientId: No target for license $idLicenca");
                                        }
                                        
                                        Logger::info("Client $clientId: Payload: " . substr($publish['payload'], 0, 200) . " [len=" . strlen($publish['payload']) . "]");
                                    }
                                }
                            }
                        }
                        break;

                    case 8:
                        if ($clients[$clientId]['connected']) {
                            $response = parseSubscribe($packet['raw'], $packet['offset']);
                            fwrite($socket, $response);
                            Logger::info("Client $clientId: SUBSCRIBE -> SUBACK");
                        }
                        break;

                    case 12:
                        fwrite($socket, pack('CC', 0xD0, 0x00));
                        break;

                    case 14:
                        Logger::info("Client $clientId: DISCONNECT");
                        break;
                }
            }

            $clients[$clientId]['buffer'] = $buffer;
        }
    }
}

