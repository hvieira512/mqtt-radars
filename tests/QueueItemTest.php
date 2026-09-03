<?php

/**
 * Corre com: php tests/QueueItemTest.php
 */

require __DIR__ . '/../src/QueueItem.php';

use App\QueueItem;

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

echo "QueueItem\n";

// --- validacao da licenca ---------------------------------------------------
// A licenca vem de um segmento do topico e vai em cru para o nome da chave do
// Redis. Sem validar, um topico como radar/1001:processing/x cria a chave
// mqtt:forward:1001:processing — a lista de transito da licenca 1001.

verifica('licenca numerica e aceite', true, QueueItem::isValidLicense('2103'));
verifica('licenca com dois pontos e recusada', false, QueueItem::isValidLicense('1001:processing'));
verifica('licenca vazia e recusada', false, QueueItem::isValidLicense(''));
verifica('licenca com letras e recusada', false, QueueItem::isValidLicense('1001a'));
verifica('licenca com asterisco e recusada', false, QueueItem::isValidLicense('*'));
verifica('licenca com espaco e recusada', false, QueueItem::isValidLicense('10 01'));
verifica('zero e recusado', false, QueueItem::isValidLicense('0'));
verifica('negativo e recusado', false, QueueItem::isValidLicense('-1'));

// --- licenca a partir da chave da fila --------------------------------------

verifica('licenca sai da chave da fila', '2103', QueueItem::licenseFromQueueKey('mqtt:forward:2103'));
verifica('chave inesperada nao inventa licenca', '', QueueItem::licenseFromQueueKey('lixo'));

// --- elementos validos ------------------------------------------------------

$valido = json_encode([
    'topic' => 'radar/2103/BD395285E96F',
    'message' => '{"header":{},"payload":{"deviceCode":"BD395285E96F"}}',
    'license' => '2103',
    'queued_at' => 1788401936,
]);
$item = QueueItem::parse($valido, '2103');
verifica('elemento valido e aceite', 'radar/2103/BD395285E96F', $item['topic']);
verifica('licenca preservada', '2103', $item['license']);

// --- o que fazia o consumidor rebentar --------------------------------------

verifica('JSON invalido e recusado', null, QueueItem::parse('isto nao e json', '2103'));
verifica('JSON nulo e recusado', null, QueueItem::parse('null', '2103'));
verifica('JSON escalar e recusado', null, QueueItem::parse('"uma string"', '2103'));
verifica('lista em vez de objeto e recusada', null, QueueItem::parse('[1,2,3]', '2103'));
verifica('objeto vazio e recusado', null, QueueItem::parse('{}', '2103'));

// Sem topic ou sem message nao ha nada para entregar.
verifica('sem topic e recusado', null, QueueItem::parse('{"message":"{}","license":"2103"}', '2103'));
verifica('sem message e recusado', null, QueueItem::parse('{"topic":"radar/2103/X","license":"2103"}', '2103'));

// --- licenca em falta no elemento -------------------------------------------

$semLicenca = json_encode(['topic' => 'radar/2103/X', 'message' => '{}']);
$item = QueueItem::parse($semLicenca, '2103');
verifica('licenca em falta e preenchida pela fila', '2103', $item['license']);

// Uma licenca que contradiz a fila nao e de confiar: manda a fila.
$outraLicenca = json_encode(['topic' => 'radar/2103/X', 'message' => '{}', 'license' => '9999']);
$item = QueueItem::parse($outraLicenca, '2103');
verifica('licenca divergente cede a da fila', '2103', $item['license']);

echo "\n$total verificacoes, $falhas falhas\n";
exit($falhas === 0 ? 0 : 1);
