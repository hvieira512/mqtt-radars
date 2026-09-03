<?php

/**
 * Corre com: php tests/OutboundBatchTest.php
 */

require __DIR__ . '/../src/OutboundBatch.php';

use App\OutboundBatch;

$falhas = 0;
$total = 0;

function verifica(string $nome, $esperado, $obtido): void
{
    global $falhas, $total;
    $total++;
    if ($esperado === $obtido) {
        echo "  ok   $nome\n";
        return;
    }
    $falhas++;
    echo "  FALHA $nome\n";
    echo "       esperado: " . var_export($esperado, true) . "\n";
    echo "       obtido:   " . var_export($obtido, true) . "\n";
}

echo "OutboundBatch\n";

// Envelope completo do radar, como chega do broker.
$envelope = json_encode([
    'header' => [
        'traceId' => '99017BD395285E96F',
        'payloadVersion' => 1,
        'brand' => 'qinglan',
        'timestamp' => 99017,
        'method' => 'device-penetrate',
    ],
    'payload' => ['deviceCode' => 'BD395285E96F', 'heartbreath' => 'ABJFAAAAABEAF00AHEADAg=='],
]);

$mensagens = OutboundBatch::build([
    ['topic' => 'radar/2103/BD395285E96F', 'message' => $envelope],
]);

verifica('envia o topico', 'radar/2103/BD395285E96F', $mensagens[0]['topic']);
verifica(
    'envia o payload sem o envelope',
    ['deviceCode' => 'BD395285E96F', 'heartbreath' => 'ABJFAAAAABEAF00AHEADAg=='],
    $mensagens[0]['payload']
);
verifica('leva o traceId do header', '99017BD395285E96F', $mensagens[0]['traceId']);

// Sem header nao ha traceId, mas a mensagem segue na mesma.
$semHeader = json_encode(['payload' => ['deviceCode' => 'AABB', 'position' => 'xyz']]);
$mensagens = OutboundBatch::build([['topic' => 'radar/1001/AABB', 'message' => $semHeader]]);
verifica('sem header o traceId fica ausente', false, array_key_exists('traceId', $mensagens[0]));
verifica('sem header o payload segue na mesma', ['deviceCode' => 'AABB', 'position' => 'xyz'], $mensagens[0]['payload']);

// Mensagem sem a chave payload: o objeto inteiro serve de payload.
$semChave = json_encode(['deviceCode' => 'CCDD', 'position' => 'xyz']);
$mensagens = OutboundBatch::build([['topic' => 'radar/1001/CCDD', 'message' => $semChave]]);
verifica('sem chave payload usa o objeto inteiro', ['deviceCode' => 'CCDD', 'position' => 'xyz'], $mensagens[0]['payload']);

// A mensagem que envenenou lotes reais em producao.
$envenenada = json_encode(['type' => 'position']);
$mensagens = OutboundBatch::build([['topic' => 'radar/1001/AABBCCDDEEFF', 'message' => $envenenada]]);
verifica('mensagem malformada nao rebenta a construcao', ['type' => 'position'], $mensagens[0]['payload']);

// JSON invalido.
$mensagens = OutboundBatch::build([['topic' => 'radar/1001/X', 'message' => 'isto nao e json']]);
verifica('JSON invalido produz payload vazio', [], $mensagens[0]['payload']);

// A ordem do lote e a ordem enviada: os indices do results dependem disso.
$mensagens = OutboundBatch::build([
    ['topic' => 'a', 'message' => json_encode(['payload' => ['deviceCode' => '1']])],
    ['topic' => 'b', 'message' => json_encode(['payload' => ['deviceCode' => '2']])],
    ['topic' => 'c', 'message' => json_encode(['payload' => ['deviceCode' => '3']])],
]);
verifica('a ordem do lote e preservada', ['a', 'b', 'c'], array_column($mensagens, 'topic'));

echo "\n$total verificacoes, $falhas falhas\n";
exit($falhas === 0 ? 0 : 1);
