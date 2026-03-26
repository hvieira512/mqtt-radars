<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/Repositories/DeviceRepository.php';
require_once __DIR__ . '/src/Repositories/SleepReportRepository.php';
require_once __DIR__ . '/src/Services/SleepReportService.php';

use App\Logger;
use App\Repositories\DeviceRepository;
use App\Repositories\SleepReportRepository;
use App\Services\SleepReportService;

$config = [
    'app_id'           => $_ENV['APP_ID'] ?? getenv('APP_ID'),
    'app_secret'       => $_ENV['SECRET'] ?? getenv('SECRET'),
    'username'         => $_ENV['HOBACARE_USERNAME'] ?? getenv('HOBACARE_USERNAME'),
    'password'         => $_ENV['HOBACARE_PASSWORD'] ?? getenv('HOBACARE_PASSWORD'),
    'api_base'         => $_ENV['BASE_URL'] ?? getenv('BASE_URL'),
    'credentials_file' => __DIR__ . '/credentials.json'
];

Logger::info("Starting Daily Sleep Report Sync...");

try {
    $sleepService = new SleepReportService(
        new SleepReportRepository(),
        new DeviceRepository(),
        $config
    );

    $sleepService->syncAllDevices();

    Logger::info("Sync finished successfully.");
} catch (Exception $e) {
    error_log("CRON ERROR: " . $e->getMessage());
    Logger::info("CRITICAL ERROR: " . $e->getMessage());
    exit(1);
}
