# 04 — Entrega

## 1. Resolução do destino

Cada licença corresponde a uma plataforma com o seu próprio endereço. A
resolução segue três passos, pela ordem indicada:

1. **`TEST_TARGET_URL`**, se estiver definida. Sobrepõe-se a tudo e dirige todas
   as licenças ao mesmo destino. Destina-se a desenvolvimento.
2. **`crm:target:{licenca}`** no Redis, se existir e não tiver expirado.
3. **Consulta ao CRM**, com `POST id_licenca={licenca}` para `CRM_URL`. A
   resposta é o endereço em texto simples, que é guardado na cache com
   `CRM_CACHE_TTL` segundos de validade.

Se o CRM responder com algo diferente de 200, ou com corpo vazio, a resolução
falha. As mensagens desse lote vão **diretamente para a fila de falhas, sem
qualquer tentativa de entrega**, e com `attempts` igual a 1.

Esse valor de `attempts` é o que distingue, na fila de falhas, uma falha de
resolução de uma falha de entrega: uma entrega esgotada tem `attempts` igual a
`FORWARD_MAX_ATTEMPTS`.

## 2. Lotes

O consumidor retira até `FORWARD_BATCH_SIZE` mensagens de uma fila e envia-as
num único pedido HTTP. Quando a fila tem menos do que isso, o lote é do tamanho
que houver.

**O lote não é opcional.** A plataforma só aceita este formato: um pedido sem
`batch: true` é recusado com `422 Batch mode required`, antes de qualquer
validação de conteúdo.

O lote é indivisível para a plataforma do cliente, que o processa como uma
transação única. Se uma mensagem do lote for recusada, **todo o lote é revertido
e nenhuma das restantes é gravada**. Uma mensagem problemática arrasta consigo
as outras noventa e nove.

## 3. Classificação da resposta

**O código HTTP não é suficiente para decidir.** A plataforma reverte o lote e
responde com `status: "error"` no corpo, mas o `http_response_code()` não tem
efeito depois de já ter saído output — e um lote revertido chega ao consumidor
como **200**. Confiar apenas no código faz descartar mensagens que nunca foram
gravadas, sem deixar rasto na fila de falhas nem no registo.

A decisão é tomada em `App\BatchOutcome::classify()`, que lê o código e o corpo
e devolve um de três destinos:

| Destino | Quando | Efeito |
|---|---|---|
| `delivered` | 2xx **e** `status: "ok"` no corpo | Sai da fila |
| `rejected` | `status: "error"` com código abaixo de 500, ou 4xx | Recusa definitiva; repetir dá o mesmo |
| `retry` | 5xx, erro de curl, ou 2xx sem confirmação interpretável | Falha transitória |

Um 2xx cujo corpo não se consiga interpretar — a página de erro de um proxy,
por exemplo — é tratado como `retry` e não como entrega. Repetir arrisca
duplicados; dar por entregue perde as mensagens em silêncio, que é o pior dos
dois.

O corpo pode vir precedido de avisos do PHP da plataforma, que empurram o JSON
para o fim da resposta. A leitura procura o início do objeto em vez de assumir
que a resposta começa nele.

## 4. Recusa localizada

Quando recusa, a plataforma indica no corpo **que mensagens** recusou e porquê,
indexadas pela posição no lote:

```json
{ "batch": true, "status": "error",
  "message": "Batch rolled back because at least one message failed",
  "results": { "57": { "index": 57, "status": "error", "message": "No deviceCode" } } }
```

O consumidor retira do lote apenas as posições nomeadas, escreve-as na fila de
falhas com o motivo devolvido, e **reenfileira as restantes**, que seguem no
ciclo seguinte. A plataforma continua a tratar o lote como transação única; o
que muda é o consumidor deixar de lhe reenviar um lote que já sabe que vai ser
recusado.

Sem isto, uma única mensagem inválida faz morrer as outras noventa e nove. Com
isto, morre só ela.

Se a recusa não nomear posições — `Batch mode required`, `Empty batch` — não há
como a localizar, e o lote inteiro passa à fila de falhas.

## 5. Retentativas

Só as falhas `retry` são repetidas. Cada mensagem do lote tem o `attempts`
incrementado; abaixo de `FORWARD_MAX_ATTEMPTS` volta à fila, e ao esgotá-lo
passa para `mqtt:forward_failed:{licenca}`.

Entre tentativas há espera crescente, a dobrar a cada uma, entre
`FORWARD_RETRY_BASE_MS` e `FORWARD_RETRY_MAX_MS`. Sem ela, as três tentativas
esgotam-se em milissegundos e uma indisponibilidade de uma hora produz dezenas
de milhares de entradas mortas em vez de algumas centenas.

## 6. O que fica registado

Uma mensagem que entre na fila de falhas leva consigo o que a plataforma
respondeu:

| Campo | Conteúdo |
|---|---|
| `last_http_code` | Código devolvido |
| `last_error` | Erro de curl, quando existiu |
| `last_response` | Primeiros 500 caracteres do corpo |
| `last_reason` | Motivo por mensagem no caso de recusa localizada |
| `last_failed_at` | Quando desistiu |

Em paralelo, `mqtt:forward:stats:{licenca}` acumula contadores por destino e
código — `delivered:200`, `rejected:422`, `retry:500` — que respondem a «o que
está a falhar» com um `HGETALL`, sem percorrer listas.

## 7. Tempos limite

`FORWARD_CONNECT_TIMEOUT_MS` limita o estabelecimento da ligação e
`FORWARD_TIMEOUT_MS` limita o pedido completo.

Os valores por omissão no código, 750 e 5000 milissegundos, são
substancialmente mais apertados do que os usados em produção. Ao diagnosticar
tempos esgotados, confirmar qual dos dois conjuntos está em vigor no ficheiro
`.env` da instalação.

## 8. Durabilidade e entregas repetidas

Uma mensagem sai da fila com `LMOVE` para
`mqtt:forward:{licenca}:processing`, e só é apagada de lá depois de a entrega
estar decidida. Ao arrancar, o consumidor devolve à fila o que encontrar em
trânsito — ver o [capítulo 03](03-redis.md).

A consequência é que a entrega passa a ser **pelo menos uma vez** em vez de **no
máximo uma vez**. Uma paragem no meio de um pedido deixa de perder o lote, mas
pode fazer com que ele seja entregue duas vezes: se a plataforma gravou e a
resposta se perdeu, a recuperação reenvia o que já lá está.

É a troca certa. Uma leitura de posição duplicada é ruído; uma leitura perdida
pode ser uma queda que ninguém viu.

A plataforma não desduplica: a `radares_eventos` não tem chave única sobre nada
que identifique a leitura. É por isso que o `traceId` do radar acompanha cada
mensagem — sem ele, uma entrega repetida não é sequer distinguível de uma
leitura nova, e com ele a desduplicação passa a ser possível do lado da
plataforma quando se justificar.

## 9. Verificação de certificado

Os pedidos de saída, tanto ao CRM como às plataformas, verificam o certificado
do destino. O que segue nestes pedidos é telemetria de saúde associada a pessoas
identificadas, e sem verificação o `https://` do endereço não oferece garantia
nenhuma de autenticidade.

`FORWARD_TLS_INSECURE` desliga-a. Destina-se a ser exceção por instalação, com
data e motivo registados, onde um destino comprovadamente não suporte a
verificação — e não um valor global que a desligue para todos os destinos
indefinidamente. Um certificado expirado num destino é um defeito a corrigir
nesse destino, e desligar a verificação apenas o torna invisível.
