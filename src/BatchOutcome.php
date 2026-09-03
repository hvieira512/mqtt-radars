<?php

namespace App;

/**
 * Decide o destino de um lote a partir da resposta da plataforma.
 *
 * Le o corpo e nao so o codigo: um lote revertido chega como 200 sempre que a
 * plataforma emita output antes de responder.
 */
class BatchOutcome
{
    /** Gravado pela plataforma. Sai da fila. */
    public const DELIVERED = 'delivered';

    /** Recusa definitiva. Repetir da o mesmo resultado. */
    public const REJECTED = 'rejected';

    /** Falha transitoria. Vale a pena repetir com espera. */
    public const RETRY = 'retry';

    /**
     * @param array $result saida de forwardBatch(): http_code, error, response
     * @return array{outcome: string, rejected: array<int, string>, reason: string}
     */
    public static function classify(array $result): array
    {
        $httpCode = (int)($result['http_code'] ?? 0);
        $curlError = trim((string)($result['error'] ?? ''));
        $body = (string)($result['response'] ?? '');

        if ($curlError !== '' || $httpCode === 0) {
            return self::outcome(self::RETRY, [], $curlError !== '' ? $curlError : 'sem resposta do servidor');
        }

        $decoded = self::decodeBody($body);
        $status = $decoded['status'] ?? null;
        $message = trim((string)($decoded['message'] ?? $decoded['error'] ?? ''));

        if ($status === 'error') {
            // Excecao no processamento da plataforma: o lote pode passar a seguir.
            if ($httpCode >= 500) {
                return self::outcome(self::RETRY, [], $message !== '' ? $message : "HTTP $httpCode");
            }

            // Recusa na validacao: o conteudo nao muda entre tentativas.
            return self::outcome(self::REJECTED, self::rejectedIndexes($decoded), $message);
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            if ($status === 'ok') {
                return self::outcome(self::DELIVERED, [], '');
            }

            // Sem confirmacao no corpo nao se assume entrega: duplicar e preferivel a perder.
            return self::outcome(self::RETRY, [], 'resposta 2xx sem confirmacao interpretavel');
        }

        if ($httpCode >= 500) {
            return self::outcome(self::RETRY, [], $message !== '' ? $message : "HTTP $httpCode");
        }

        return self::outcome(self::REJECTED, self::rejectedIndexes($decoded), $message !== '' ? $message : "HTTP $httpCode");
    }

    /** Avisos do PHP da plataforma podem preceder o JSON, por isso procura-se o inicio do objeto. */
    private static function decodeBody(string $body): array
    {
        if (trim($body) === '') {
            return [];
        }

        $start = strpos($body, '{"batch"');
        if ($start === false) {
            $start = strpos($body, '{');
        }
        if ($start === false) {
            return [];
        }

        $decoded = json_decode(substr($body, $start), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * O campo results chega como objeto se so algumas posicoes falharam, e como lista se falharam todas.
     *
     * @return array<int, string> indice no lote => motivo
     */
    private static function rejectedIndexes(array $decoded): array
    {
        $results = $decoded['results'] ?? null;
        if (!is_array($results)) {
            return [];
        }

        $rejected = [];
        foreach ($results as $key => $entry) {
            if (!is_array($entry) || ($entry['status'] ?? null) !== 'error') {
                continue;
            }
            $index = isset($entry['index']) ? (int)$entry['index'] : (int)$key;
            $rejected[$index] = (string)($entry['message'] ?? 'sem motivo indicado');
        }

        return $rejected;
    }

    /** @param array<int, string> $rejected */
    private static function outcome(string $outcome, array $rejected, string $reason): array
    {
        return ['outcome' => $outcome, 'rejected' => $rejected, 'reason' => $reason];
    }
}
