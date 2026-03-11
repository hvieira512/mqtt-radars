<?php

namespace App;

use Ratchet\ConnectionInterface;

class WebLogger
{
    private static array $clients = [];

    public static function registerClient(ConnectionInterface $conn): void
    {
        self::$clients[$conn->resourceId] = $conn;
    }

    public static function removeClient(ConnectionInterface $conn): void
    {
        unset(self::$clients[$conn->resourceId]);
    }

    public static function broadcast(array $data): void
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);

        foreach (self::$clients as $conn) {
            $conn->send($json);
        }
    }
}
