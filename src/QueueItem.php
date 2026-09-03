<?php

namespace App;

/**
 * Leitura de um elemento da fila de entrega.
 *
 * O que nao se interpreta nao entra no lote: o codigo a jusante trata cada
 * elemento como array, e um valor que nao o seja derruba o processo.
 */
class QueueItem
{
    /** A licenca entra no nome da chave do Redis, e dois pontos colidem com as chaves internas. */
    public static function isValidLicense(string $license): bool
    {
        return preg_match('/^[1-9][0-9]*$/', $license) === 1;
    }

    /** Mais fiavel do que ler do primeiro elemento, que pode estar corrompido. */
    public static function licenseFromQueueKey(string $queueKey): string
    {
        if (preg_match('/^mqtt:forward:([^:]+)$/', $queueKey, $matches) !== 1) {
            return '';
        }

        return $matches[1];
    }

    /**
     * @return array|null o elemento pronto a entregar, ou null se nao servir
     */
    public static function parse(string $raw, string $queueLicense): ?array
    {
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return null;
        }

        // Exigir os campos basta: uma lista, um objeto vazio ou um escalar nao os tem.
        $topic = (string)($decoded['topic'] ?? '');
        $message = (string)($decoded['message'] ?? '');
        if ($topic === '' || $message === '') {
            return null;
        }

        // Manda a chave da fila: uma licenca divergente entregaria ao cliente errado.
        $decoded['license'] = $queueLicense;

        return $decoded;
    }
}
