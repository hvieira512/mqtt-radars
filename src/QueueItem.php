<?php

namespace App;

/**
 * Leitura de um elemento da fila de entrega.
 *
 * Um elemento que nao se consiga interpretar nao pode entrar no lote: mais a
 * frente o codigo trata cada elemento como array, e um `null` a meio derruba o
 * processo. Com reinicio automatico e recuperacao do que esta em transito, isso
 * torna-se um ciclo — o elemento volta sempre e volta sempre a derrubar.
 */
class QueueItem
{
    /**
     * A licenca e um inteiro positivo, e nada mais.
     *
     * Vem de um segmento do topico MQTT e entra em cru no nome da chave do
     * Redis. Sem esta verificacao, um topico como `radar/1001:processing/x`
     * produz a chave `mqtt:forward:1001:processing` — a lista de transito da
     * licenca 1001 — e o que la for escrito acaba entregue a esse cliente.
     */
    public static function isValidLicense(string $license): bool
    {
        return preg_match('/^[1-9][0-9]*$/', $license) === 1;
    }

    /**
     * Extrai a licenca da chave da fila.
     *
     * E mais fiavel do que a ler do primeiro elemento do lote: se esse elemento
     * estiver corrompido, a licenca sai vazia e as falhas vao parar a uma chave
     * sem licenca nenhuma.
     */
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
        if (!is_array($decoded) || array_is_list($decoded)) {
            return null;
        }

        $topic = (string)($decoded['topic'] ?? '');
        $message = (string)($decoded['message'] ?? '');
        if ($topic === '' || $message === '') {
            return null;
        }

        // A fila e por licenca, e e a chave que manda: um elemento que diga
        // outra coisa foi escrito por engano e seria entregue ao cliente errado.
        $decoded['license'] = $queueLicense;

        return $decoded;
    }
}
