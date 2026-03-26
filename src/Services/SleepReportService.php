<?php

namespace App\Services;

use App\Repositories\SleepReportRepository;
use App\Repositories\DeviceRepository;
use Exception;

class SleepReportService
{
    public function __construct(
        private SleepReportRepository $sleepRepo,
        private DeviceRepository $deviceRepo,
        private array $config
    ) {}

    public function get(string $deviceUid, string $date): ?array
    {
        $deviceId = $this->deviceRepo->getDeviceId($deviceUid);

        $existing = $this->sleepRepo->find($deviceId, $date);
        if ($existing) {
            return json_decode($existing['payload_bruto'] ?? '[]', true) ?: [];
        }

        $params = ['uid' => $deviceUid, 'date' => $date, 'lang' => "en_US"];
        $response = \apiRequest($this->config, '/radar/monitor/report', $params);
        $decoded = json_decode($response, true);

        if (isset($decoded['msg']) && str_contains($decoded['msg'], 'due after')) {
            return null;
        }

        if (!$decoded || !isset($decoded['data'])) {
            return null;
        }

        $this->sleepRepo->insert(
            $deviceId,
            null,
            $date,
            $decoded['data']['score'] ?? null,
            $decoded['data']
        );

        return $decoded;
    }

    public function syncForDate(string $date): void
    {
        $devices = $this->deviceRepo->getAllDevices();

        foreach ($devices as $device) {
            try {
                $this->get($device['uid'], $date);
                echo "Synced {$device['uid']}\n";
            } catch (Exception $e) {
                echo "Failed {$device['uid']}: {$e->getMessage()}\n";
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
        $devices = $this->deviceRepo->getAllDevices();

        foreach ($devices as $device) {
            $this->syncDeviceHistory($device['uid']);
        }
    }

    public function getStoredReportDates(int $deviceId, string $startDate, string $endDate): array
    {
        return $this->sleepRepo->getReportDates($deviceId, $startDate, $endDate);
    }
}
