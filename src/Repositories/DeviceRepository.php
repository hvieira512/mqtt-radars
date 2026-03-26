<?php

namespace App\Repositories;

class DeviceRepository
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function getDeviceId(string $deviceCode): int
    {
        $sql = "INSERT INTO radares (uid) VALUES ('" . $this->db->escape($deviceCode) . "')";
        $this->db->execute($sql);

        $result = $this->db->getRow("SELECT LAST_INSERT_ID() as id");
        return (int)$result['id'];
    }

    public function getAllDevices(): array
    {
        $result = $this->db->query("SELECT id, uid, criado_em FROM radares");
        $devices = [];
        while ($row = $this->db->fetchRow($result)) {
            $devices[] = $row;
        }
        return $devices;
    }
}