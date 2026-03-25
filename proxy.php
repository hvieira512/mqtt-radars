<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/Repositories/DeviceRepository.php';
require_once __DIR__ . '/src/Repositories/SleepReportRepository.php';
require_once __DIR__ . '/src/Repositories/UserDeviceRepository.php';
require_once __DIR__ . '/src/Services/SleepReportService.php';

use App\Database;
use App\Repositories\DeviceRepository;
use App\Repositories\SleepReportRepository;
use App\Repositories\UserDeviceRepository;
use App\Services\SleepReportService;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$config = [
    'app_id'           => $_ENV['APP_ID'] ?? getenv('APP_ID'),
    'app_secret'       => $_ENV['SECRET'] ?? getenv('SECRET'),
    'username'         => $_ENV['HOBACARE_USERNAME'] ?? getenv('HOBACARE_USERNAME'),
    'password'         => $_ENV['HOBACARE_PASSWORD'] ?? getenv('HOBACARE_PASSWORD'),
    'api_base'         => $_ENV['BASE_URL'] ?? getenv('BASE_URL'),
    'credentials_file' => __DIR__ . '/credentials.json'
];

$pdo = Database::connection();
$sleepService = new SleepReportService(
    new SleepReportRepository(),
    new DeviceRepository(),
    new UserDeviceRepository(),
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

echo apiRequest($config, $endpoint, $params);
