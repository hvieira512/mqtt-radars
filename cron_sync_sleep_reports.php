<?php

declare(strict_types=1);

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

$config = [
    'app_id'           => $_ENV['APP_ID'] ?? getenv('APP_ID'),
    'app_secret'       => $_ENV['SECRET'] ?? getenv('SECRET'),
    'username'         => $_ENV['HOBACARE_USERNAME'] ?? getenv('HOBACARE_USERNAME'),
    'password'         => $_ENV['HOBACARE_PASSWORD'] ?? getenv('HOBACARE_PASSWORD'),
    'api_base'         => $_ENV['BASE_URL'] ?? getenv('BASE_URL'),
    'credentials_file' => __DIR__ . '/credentials.json'
];

echo "[" . date('Y-m-d H:i:s') . "] Starting Daily Sleep Report Sync...\n";

try {
    $pdo = Database::connection();

    $sleepService = new SleepReportService(
        new SleepReportRepository(),
        new DeviceRepository(),
        new UserDeviceRepository(),
        $pdo,
        $config
    );

    $sleepService->syncAllDevices();

    echo "[" . date('Y-m-d H:i:s') . "] Sync finished successfully.\n";
} catch (Exception $e) {
    error_log("CRON ERROR: " . $e->getMessage());
    echo "[" . date('Y-m-d H:i:s') . "] CRITICAL ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
