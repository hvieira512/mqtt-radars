<?php

namespace App;

/**
 * Constroi o corpo que segue para a plataforma.
 *
 * A ordem e preservada: os indices que a plataforma devolve numa recusa
 * referem-se a estas posicoes.
 */
class OutboundBatch
{
    /**
     * @param array $batch elementos da fila: topic, message
     * @return array<int, array{topic: string, payload: mixed, traceId?: string}>
     */
    public static function build(array $batch): array
    {
        $messages = [];

        foreach ($batch as $item) {
            $envelope = json_decode((string)($item['message'] ?? '{}'), true);
            if (!is_array($envelope)) {
                $envelope = [];
            }

            $message = [
                'topic' => (string)($item['topic'] ?? ''),
                'payload' => $envelope['payload'] ?? $envelope,
            ];

            // Unico identificador que distingue uma entrega repetida de uma leitura nova.
            $traceId = $envelope['header']['traceId'] ?? null;
            if (is_string($traceId) && $traceId !== '') {
                $message['traceId'] = $traceId;
            }

            $messages[] = $message;
        }

        return $messages;
    }
}
