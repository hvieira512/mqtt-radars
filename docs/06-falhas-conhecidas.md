# 06 — Falhas conhecidas

Defeitos de desenho identificados no levantamento de 3 de setembro de 2026, por
ordem de risco.

| | Estado |
|---|---|
| 1. Verificação de certificado desligada | **corrigido** — ligada por omissão, exceção em `FORWARD_TLS_INSECURE` |
| 2. Motivo da falha descartado | **corrigido** — `last_http_code`, `last_error`, `last_response`, `last_reason` |
| 3. Fila de falhas sem leitura, teto ou expiração | **teto corrigido** — `FORWARD_FAILED_CAP` e contadores; continua sem consumidor |
| 4. Não existe alarme | **fora de âmbito** — o projeto é a ponte para as bases das plataformas, e alarmística não lhe pertence |
| 5. Todas as falhas tratadas como iguais | **corrigido** — `App\BatchOutcome` classifica e a recusa é localizada |
| 6. Não há espera entre tentativas | **corrigido** — espera crescente entre `FORWARD_RETRY_BASE_MS` e `FORWARD_RETRY_MAX_MS` |
| 7. Canal `radar:ingest:*` sem subscritores | **corrigido** — chamada removida, e com ela oito dependências mortas do `composer.json` |
| 8. Licença que comece a produzir dados sem ter consumidor | **adiado por decisão** — a unidade templada resolve, mas não vale um lançamento |
| 9. Mensagens só em memória entre a fila e a entrega | **corrigido** — `LMOVE` para lista de trânsito, com recuperação no arranque |
| 10. Sem identificador que permita desduplicar | **habilitado** — o `traceId` acompanha cada mensagem; desduplicar é decisão da plataforma |
| 11. Elemento ilegível na fila derruba o consumidor em ciclo | **corrigido** — `App\QueueItem` valida à leitura, e o que não serve vai para `mqtt:forward_invalid:{licenca}` |
| 12. Índices de recusa fora do lote geram ciclo infinito | **corrigido** — só contam índices existentes no lote; sem nenhum, recusa-se o lote inteiro |
| 13. Licença do tópico entrava em cru na chave do Redis | **corrigido** — validada como inteiro positivo no `mqtt-worker` e em `getQueueKeys` |
| 14. Espera crescente do subscritor no ramo errado | **corrigido** — aplica-se agora a ligação perdida e a ligação recusada |

Doze corrigidas. Duas fechadas por decisão: a alarmística não pertence a este
projeto, e a unidade templada não corrige defeito nenhum.

As correções vivem no `forward-consumer.php`, no `mqtt-worker.php` e em
`src/BatchOutcome.php`, `src/OutboundBatch.php` e `src/QueueItem.php`, com testes
em `tests/`. Nada disto exigiu alterações ao `ingest.php` das plataformas.

## 1. A verificação de certificado está desligada

Todas as chamadas HTTP de saída — ao CRM e às plataformas — usam
`CURLOPT_SSL_VERIFYPEER => false`. Qualquer certificado é aceite, expirado,
inválido ou substituído.

O conteúdo entregue é telemetria de saúde associada a pessoas identificadas, e o
`https://` dos endereços não oferece garantia nenhuma de autenticidade.

O efeito secundário é tão sério como o direto: uma plataforma cujo certificado
expire continua a receber dados sem que nada o assinale. A verificação
desligada não gere o risco, suprime o sinal.

**Correção.** Remover a linha. Onde uma plataforma tiver, comprovadamente, um
certificado que não valida, a exceção deve ser explícita e delimitada a essa
instalação por variável de ambiente, com nota da data e do motivo, em vez de um
valor global que desliga a verificação para todos os destinos indefinidamente.

## 2. O motivo da falha é descartado

A função `forwardBatch` calcula `http_code`, `error` e `response`, e o elemento
escrito em `mqtt:forward_failed:{licenca}` não guarda nenhum dos três. Guarda
apenas `attempts`.

No caso do 500, a plataforma envia no corpo da resposta a mensagem da exceção
que causou a reversão, precisamente para explicar a falha. Essa mensagem é
recebida e deitada fora.

A consequência prática mediu-se: 2,5 milhões de entregas falhadas acumuladas
durante três meses, das quais **não é possível determinar se foram 422, 500 ou
ligação recusada**. A natureza das falhas teve de ser inferida por aritmética,
comparando o volume por hora com os tempos limite configurados.

Estes campos existiram. Foram acrescentados no commit `91e9b4a` e removidos no
`5ba15eb`, que introduziu o envio em lote — três linhas removidas, nenhuma
acrescentada em substituição.

**Corrigido.** O elemento escrito na fila de falhas leva agora `last_http_code`,
`last_error`, `last_response`, `last_reason` e `last_failed_at`, gravados em
`recordFailure()`. Os campos estão descritos no [capítulo 04](04-entrega.md).

## 3. A fila de falhas não é lida, não tem limite e não expira

As referências a `mqtt:forward_failed:*` no código são duas, ambas `RPUSH`.
Nunca há `LPOP`, `LTRIM` ou `EXPIRE`.

As mensagens que falham a entrega não são reenviadas nem descartadas: acumulam
indefinidamente. Em combinação com uma política de expulsão do tipo
`allkeys-lru`, descrita no [capítulo 03](03-redis.md), é arquivo morto a
competir por memória com filas por entregar.

Chegou a ocupar 925 MB, 96% de toda a memória do Redis, numa única licença.

**Correção.** Contar as falhas por código de resposta, com um `HINCRBY` por
lote, e limitar a lista com um `LTRIM` a guardar as últimas N como amostra. Os
contadores respondem à pergunta «o que está a falhar»; a lista passa a servir
apenas para exemplos.

## 4. Não existe alarme

**Fora de âmbito, por decisão.** A acumulação descrita acima decorreu durante
três meses sem que nada a assinalasse, e foi descoberta acidentalmente ao
investigar outro assunto.

O propósito deste projeto é a ponte entre o MQTT e as bases de dados das
plataformas dos clientes, e a alarmística não lhe pertence. Os contadores em
`mqtt:forward:stats:{licenca}` ficam disponíveis para quem os queira observar de
fora — um `HGETALL` responde a «o que está a falhar» sem percorrer listas —, mas
este projeto não os vigia.

Fica registado que, sem alguém a olhar, uma acumulação como a de maio a julho
volta a passar despercebida.

## 5. Todas as falhas são tratadas como se fossem iguais

O consumidor considera falha tudo o que não seja 2xx e repete
`FORWARD_MAX_ATTEMPTS` vezes, sem olhar ao que a plataforma respondeu. As
respostas têm significados diferentes e exigem tratamentos diferentes.

Um **422** significa recusa na validação — `deviceCode` ausente, tipo de leitura
inválido, ou aparelho desconhecido. O conteúdo não muda entre tentativas, e a
repetição não pode ter outro resultado.

Um **500** significa exceção no processamento, tipicamente na transação de base
de dados. É transitório e a repetição faz sentido.

O custo do 422 é ainda triplicado por o lote ser indivisível para a plataforma:
uma mensagem recusada faz reverter as outras noventa e nove, e as cem são
reenviadas três vezes antes de todas irem para a fila de falhas.

**Correção.** A plataforma identifica, no corpo do 422, exatamente que mensagens
recusou e porquê, indexadas pela posição no lote:

```json
{ "batch": true, "status": "error",
  "message": "Batch rolled back because at least one message failed",
  "results": { "37": { "index": 37, "status": "error",
                       "message": "Unknown device", "device": "594B3CB3B417" } } }
```

O consumidor deve ramificar pela resposta:

| Resposta | Natureza | Tratamento |
|---|---|---|
| 422 **com** `results` | permanente e localizada | Retirar do lote os índices apontados, escrevê-los na fila de falhas com o motivo devolvido, e reenviar de imediato as mensagens restantes |
| 422 **sem** `results` | erro de formato do próprio consumidor — `Batch mode required`, `Empty batch` | Falhar o lote e registar em destaque; repetir não muda nada |
| 500 | transitória, do lado da plataforma | Repetir o lote completo, com a espera do ponto 6 |
| erro de curl, código HTTP 0 | rede ou TLS | Igual ao 500 |

Retirar a mensagem recusada e reenviar as restantes elimina a amplificação sem
qualquer alteração à plataforma: esta continua a tratar o lote como transação
única, e é o consumidor que deixa de lhe voltar a entregar um lote que já sabe
que vai ser recusado.

## 6. Não há espera entre tentativas

As retentativas não têm intervalo. Uma mensagem reenfileirada é reprocessada
assim que o consumidor lhe chegar, o que com filas curtas acontece em
milissegundos.

Contra uma plataforma que responde depressa a recusar, as três tentativas
esgotam-se quase instantaneamente e o consumidor passa ao lote seguinte à mesma
velocidade. É o que permite que uma hora de indisponibilidade produza dezenas de
milhares de entradas mortas em vez de algumas centenas.

**Correção.** Espera crescente entre tentativas do mesmo lote.

## 7. O canal `radar:ingest:*` não tem subscritores

O `mqtt-worker` publica uma cópia de cada mensagem no canal
`radar:ingest:{licenca}`. As três licenças em produção reportaram zero
subscritores.

Em pub/sub, uma mensagem publicada sem subscritores é descartada pelo Redis. A
publicação é, portanto, uma ida ao Redis por cada mensagem recebida, sem efeito.

**A origem está identificada.** O canal foi introduzido no commit `7c12284`, a
30 de março de 2026, ao mesmo tempo que `assets/js/pages/home/radar/websocket.js`
e um endpoint de ingestão próprio: alimentava uma vista de radares em tempo real
no browser, servida por um servidor websocket que lia deste canal.

Essa funcionalidade foi removida a 12 de maio no commit `9ca96da`, *cleanup:
remove dead code, keep only MQTT router flow*, que levou o front-end inteiro —
assets, JavaScript, Dockerfile, autenticação. **Só a chamada ao `publish`
sobreviveu.**

Sobreviveram com ela cinco dependências no `composer.json` que nenhum ficheiro
PHP usa: `cboden/ratchet`, `textalk/websocket`, `react/http`, `react/socket` e
`simps/mqtt`.

**Corrigido.** A chamada foi removida do `mqtt-worker`, e com ela as oito
dependências que nenhum ficheiro PHP usava: `cboden/ratchet`,
`textalk/websocket`, `react/http`, `react/socket`, `react/event-loop`,
`simps/mqtt`, `illuminate/events` e `illuminate/container`. O `composer.json`
passou de onze dependências para três.

Não havia consumidor por escrever à espera do canal — havia uma funcionalidade
apagada que deixou resto.

## 8. Uma licença que comece a produzir dados sem ter consumidor

As unidades systemd são fixas, uma por licença. Uma licença sem unidade
correspondente só seria servida por um consumidor genérico — e **não existe
nenhum em execução**. As três unidades em produção correm todas com
`--license=N`:

```
mqtt-forward-1001.service   forward-consumer.php --license=1001
mqtt-forward-2004.service   forward-consumer.php --license=2004
mqtt-forward-2103.service   forward-consumer.php --license=2103
```

O modo genérico existe no código e nada o corre.

Uma licença que exista no CRM e não tenha radares a publicar é indiferente a
este projeto: não gera fila, e não há nada para entregar.

O que importa é o momento em que uma licença **começa** a publicar. O subscritor
enfileira-a e acrescenta-a a `mqtt:forward:licenses` sem que nada mais seja
preciso — e, se não existir unidade para ela, as mensagens acumulam-se numa fila
que ninguém lê. Sem erro, sem falha registada, e sem nada que o assinale.

**Correção conhecida, adiada por decisão.** Uma unidade templada,
`mqtt-forward@.service`, com o identificador da licença como parâmetro da
instância: três ficheiros passam a um, e acrescentar uma licença passa a ser
`systemctl enable --now mqtt-forward@2051`. É o padrão que o
`havicare-hub-client` já usa.

Não foi feita porque não corrige defeito nenhum e o lançamento que a
acompanharia já muda a semântica de entrega, liga a verificação de certificado e
limita as filas de falhas. Juntar uma reorganização das unidades a essas
alterações dá dois suspeitos em vez de um se algo correr mal.

Com três licenças a mudarem raramente, o custo que evita não está a doer. Faz
sentido quando aparecer uma quarta, como alteração isolada.

## 9. Mensagens só em memória entre a fila e a entrega

**Corrigido.** O consumidor retirava as mensagens com `LPOP`, e a partir desse
momento a única cópia existia na memória do processo — durante o pedido HTTP
inteiro, que em produção pode demorar até sessenta segundos. Uma paragem nessa
janela perdia até cem mensagens sem erro, sem entrada na fila de falhas e sem
linha no registo. Com `Restart=always` na unidade, paragens fazem parte do
funcionamento normal.

A entrega passou a usar `LMOVE` para uma lista de trânsito, apagada quando o
destino da mensagem está decidido, e o arranque devolve à fila o que lá
encontrar. O detalhe está no [capítulo 03](03-redis.md).

**Pressupõe um consumidor por licença.** Com dois na mesma fila, a recuperação
de um reclamaria o lote que o outro tem em voo.

## 10. Sem identificador que permita desduplicar

**Habilitado do lado deste projeto.** A entrega é agora *pelo menos uma vez*, o
que troca perda silenciosa por duplicação possível. A plataforma não desduplica
— a `radares_eventos` não tem chave única sobre nada que identifique a leitura —
e não o poderia fazer, porque o `header` que o radar envia era descartado antes
de sair daqui.

O `traceId` passou a acompanhar cada mensagem. Não impede duplicados por si só:
torna-os detetáveis, e deixa a desduplicação ao alcance da plataforma quando
essa decisão for tomada.

## 11. Um elemento ilegível na fila derruba o consumidor em ciclo

**Corrigido.** O consumidor fazia `json_decode` de cada elemento e punha o
resultado no lote sem o verificar. Um elemento que não decodificasse para objeto
entrava como `null`, e mais à frente o código trata cada elemento como array: o
processo morria com `TypeError`.

Com `Restart=always` na unidade e a recuperação do trânsito, o resultado não era
uma paragem — era um **ciclo**. O systemd relançava, a recuperação devolvia o
elemento à fila, e o consumidor voltava a morrer. A fila nunca drenava.

Um segundo efeito do mesmo elemento: a licença era lida de `$batch[0]['license']`
e saía **vazia**, pelo que as falhas iriam para uma chave sem licença.

A leitura passou por `App\QueueItem::parse()`, que recusa o que não sirva e o
encaminha para `mqtt:forward_invalid:{licenca}`, e a licença passou a sair da
chave da fila.

## 12. Índices de recusa fora do lote geram um ciclo infinito

**Corrigido.** A recusa localizada retira do lote as posições que a plataforma
nomeia. Se nenhuma dessas posições existir no lote — por divergência entre as
duas pontas sobre o que é uma posição — nada era removido, o lote inteiro
voltava à fila, e como as sobreviventes não incrementam `attempts`, nada
envelhecia.

O resultado medido foi **5231 pedidos em dez segundos** contra a plataforma, sem
progresso nenhum: o consumidor a atacar involuntariamente o destino.

Passam a contar apenas os índices que existem no lote. Sem nenhum utilizável, a
recusa não se consegue localizar e o lote inteiro passa à fila de falhas, com um
aviso a assinalar a divergência.

## 13. O segmento da licença do tópico entrava em cru na chave do Redis

**Corrigido.** O `mqtt-worker` lia a licença de `$parts[1]` do tópico e usava-a
sem validação para compor `mqtt:forward:{licenca}`. Um segmento com dois pontos
produzia uma chave que colide com as internas do projeto.

Verificado publicando no broker: `radar/1001:processing/x` criou
`mqtt:forward:1001:processing`, que é a **lista de trânsito da licença 1001**. O
que ali fosse escrito seria recuperado no arranque seguinte, empurrado para a
fila real, e **entregue à plataforma desse cliente**. O conjunto
`mqtt:forward:licenses` acumulava também as entradas malformadas.

Qualquer publicador com acesso ao broker chega lá, e não é preciso má intenção:
já se encontrou uma mensagem publicada à mão num tópico de radares em agosto, e
uma gralha no segmento basta.

A licença passou a ser validada como inteiro positivo, em duas pontas: no
`mqtt-worker`, que recusa o tópico e regista o aviso, e em `getQueueKeys`, que
ignora entradas inválidas já existentes no conjunto.

À data da correção, as licenças registadas em produção eram `1001`, `2004` e
`2103`, e não havia nenhuma chave malformada — a falha existia sem nunca ter
sido acionada.

## 14. A espera crescente do subscritor estava no ramo errado

**Corrigido.** O ciclo de reconexão do `mqtt-worker` tinha dois blocos de
captura. O `DataTransferException` — a ligação a cair com o broker de pé —
recebia a espera a dobrar até sessenta segundos. Uma **ligação recusada**, que é
o que acontece enquanto o broker está em baixo, chega como exceção genérica e
caía no segundo bloco: `sleep(5)` fixo, sem tocar no contador.

Ou seja, a espera crescente existia e **nunca disparava no caso para que foi
escrita**. Uma indisponibilidade prolongada do broker fazia o subscritor tentar
de cinco em cinco segundos indefinidamente.

Medido antes: cinco segundos, sempre. Depois: 2s, 4s, 8s, 16s.

Os dois blocos foram colapsados num só, que aplica a mesma espera aos dois
casos.

No mesmo ficheiro, a linha `Logger::info("MQTT Worker started")` estava
duplicada e registava o arranque duas vezes.
