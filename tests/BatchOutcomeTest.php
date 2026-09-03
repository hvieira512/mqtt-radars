<?php

/**
 * Corre com: php tests/BatchOutcomeTest.php
 *
 * Os corpos de resposta usados aqui foram capturados de pedidos reais ao
 * ingest.php, incluindo o caso em que avisos do PHP precedem o JSON.
 */

// A classe nao tem dependencias, por isso o teste corre sem composer install.
require __DIR__ . '/../src/BatchOutcome.php';

use App\BatchOutcome;

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

echo "BatchOutcome\n";

// --- entrega confirmada -----------------------------------------------------

$r = BatchOutcome::classify([
    'http_code' => 200,
    'error' => '',
    'response' => '{"batch":true,"status":"ok","total":1,"results":[{"status":"ok","device":"AABB","message_type":"position","event_id":1}]}',
]);
verifica('200 com status ok e entregue', 'delivered', $r['outcome']);

// --- 200 com status error: o caso que perde dados em silencio ---------------

$r = BatchOutcome::classify([
    'http_code' => 200,
    'error' => '',
    'response' => '{"batch":true,"status":"error","message":"Batch rolled back because at least one message failed","total":2,"results":{"1":{"index":1,"status":"error","message":"No deviceCode"}}}',
]);
verifica('200 com status error nao e entrega', 'rejected', $r['outcome']);
verifica('200 com status error nomeia o indice recusado', [1 => 'No deviceCode'], $r['rejected']);

// --- 422 com results indexado por posicao -----------------------------------

$r = BatchOutcome::classify([
    'http_code' => 422,
    'error' => '',
    'response' => '{"batch":true,"status":"error","message":"Batch rolled back because at least one message failed","total":3,"results":{"0":{"index":0,"status":"error","message":"Invalid payload type"},"2":{"index":2,"status":"error","message":"No deviceCode"}}}',
]);
verifica('422 e recusa permanente', 'rejected', $r['outcome']);
verifica('422 nomeia todos os indices', [0 => 'Invalid payload type', 2 => 'No deviceCode'], $r['rejected']);

// --- 422 com results como lista sequencial ----------------------------------

$r = BatchOutcome::classify([
    'http_code' => 422,
    'error' => '',
    'response' => '{"batch":true,"status":"error","message":"...","total":1,"results":[{"index":0,"status":"error","message":"Invalid payload type"}]}',
]);
verifica('results em lista sequencial tambem e lido', [0 => 'Invalid payload type'], $r['rejected']);

// --- avisos do PHP antes do JSON --------------------------------------------

$comAvisos = "Deprecated:  Methods with the same name as their class...\n"
    . "Warning:  mysqli_errno() expects parameter 1...\n"
    . '{"batch":true,"status":"error","message":"rolled back","results":{"5":{"index":5,"status":"error","message":"No deviceCode"}}}';
$r = BatchOutcome::classify(['http_code' => 200, 'error' => '', 'response' => $comAvisos]);
verifica('JSON e encontrado depois de avisos do PHP', 'rejected', $r['outcome']);
verifica('indice lido apesar dos avisos', [5 => 'No deviceCode'], $r['rejected']);

// --- 500: excecao do lado do tenant, transitoria ----------------------------

$r = BatchOutcome::classify([
    'http_code' => 500,
    'error' => '',
    'response' => '{"batch":true,"status":"error","message":"Duplicate entry \'123\' for key \'PRIMARY\'"}',
]);
verifica('500 e transitorio', 'retry', $r['outcome']);
verifica('500 preserva a mensagem da excecao', 'Duplicate entry \'123\' for key \'PRIMARY\'', $r['reason']);

// --- rede -------------------------------------------------------------------

$r = BatchOutcome::classify([
    'http_code' => 0,
    'error' => 'Connection refused',
    'response' => '',
]);
verifica('erro de curl e transitorio', 'retry', $r['outcome']);
verifica('erro de curl preserva o motivo', 'Connection refused', $r['reason']);

// --- 2xx sem corpo interpretavel: nao se confirma entrega -------------------

$r = BatchOutcome::classify([
    'http_code' => 200,
    'error' => '',
    'response' => '<html><body>502 Bad Gateway</body></html>',
]);
verifica('2xx sem JSON nao conta como entregue', 'retry', $r['outcome']);

// --- 422 sem results: recusa que nao se consegue localizar ------------------

$r = BatchOutcome::classify([
    'http_code' => 422,
    'error' => '',
    'response' => '{"error":"Batch mode required","message":"Expected payload format: {\"batch\": true, \"messages\": [...]}"}',
]);
verifica('422 sem results recusa o lote inteiro', 'rejected', $r['outcome']);
verifica('422 sem results nao nomeia indices', [], $r['rejected']);

echo "\n$total verificacoes, $falhas falhas\n";
exit($falhas === 0 ? 0 : 1);
