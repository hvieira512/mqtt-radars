<?php

namespace App\Services;

use App\Repositories\SleepReportRepository;
use App\Repositories\DeviceRepository;
use App\Repositories\UserDeviceRepository;
use Exception;

class SleepReportService
{
    public function __construct(
        private SleepReportRepository $sleepRepo,
        private DeviceRepository $deviceRepo,
        private UserDeviceRepository $userDeviceRepo,
        private array $config
    ) {}

    public function get(string $deviceUid, string $date): array
    {
        $deviceId = $this->deviceRepo->getDeviceId($deviceUid);

        // Try DB first
        $existing = $this->sleepRepo->find($deviceId, $date);
        if ($existing) {
            return json_decode($existing['raw_payload'], true);
        }

        // Fetch from API
        $params = ['uid' => $deviceUid, 'date' => $date, 'lang' => "en_US"];
        $response = \apiRequest($this->config, '/radar/monitor/report', $params);
        $decoded = json_decode($response, true);

        if (!$decoded || !isset($decoded['data'])) {
            throw new Exception("API error or empty data");
        }

        // Resolve current user
        $userId = $this->userDeviceRepo->getActiveUserId($deviceId);
        if (!$userId) {
            throw new Exception("No active user found for device $deviceUid");
        }

        // Save to DB
        $this->sleepRepo->insert(
            $deviceId,
            $userId,
            $date,
            $decoded['data']['score'] ?? null,
            $decoded['data']
        );

        return $decoded;
    }

    public function syncForDate(string $date): void
    {
        $devices = $this->deviceRepo->getActiveDevices();

        foreach ($devices as $device) {
            try {
                $this->get($device['device_code'], $date);
                echo "Synced {$device['device_code']}\n";
            } catch (Exception $e) {
                echo "Failed {$device['device_code']}: {$e->getMessage()}\n";
            }
        }
    }

    public function syncDeviceHistory(string $deviceUid): void
    {
        $params = [
            'uid' => $deviceUid,
            'date' => date('Y-m-01'),
            'lang' => "en_US"
        ];

        $response = \apiRequest($this->config, '/radar/monitor/reportDays', $params);
        $decoded = json_decode($response, true);

        $availableDates = $decoded['data'] ?? $decoded ?? [];
        if (!is_array($availableDates)) return;

        foreach ($availableDates as $date) {
            try {
                $this->get($deviceUid, $date);
            } catch (Exception $e) {
                error_log("Failed syncing $deviceUid for date $date: " . $e->getMessage());
            }
        }
    }

    public function syncAllDevices(): void
    {
        $devices = $this->deviceRepo->getActiveDevices();

        foreach ($devices as $device) {
            $this->syncDeviceHistory($device['device_code']);
        }
    }
}
