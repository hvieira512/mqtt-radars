<?php

namespace App;

/**
 * Constroi o corpo que segue para a plataforma a partir das mensagens da fila.
 *
 * A ordem do lote e a ordem enviada, e os indices do campo results que a
 * plataforma devolve numa recusa referem-se a essas posicoes.
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

            // O radar gera um traceId por leitura. Sem ele a chegar a
            // plataforma, uma entrega repetida — depois de uma resposta
            // perdida, ou de uma recuperacao da lista de processamento — nao e
            // distinguivel de uma leitura nova, nem por nos nem por ela.
            $traceId = $envelope['header']['traceId'] ?? null;
            if (is_string($traceId) && $traceId !== '') {
                $message['traceId'] = $traceId;
            }

            $messages[] = $message;
        }

        return $messages;
    }
}
