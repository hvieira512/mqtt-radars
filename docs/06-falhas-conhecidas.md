# 06 — Falhas conhecidas

Defeitos de desenho identificados no levantamento de 3 de setembro de 2026, por
ordem de risco.

| | Estado |
|---|---|
| 1. Verificação de certificado desligada | **corrigido** — ligada por omissão, exceção em `FORWARD_TLS_INSECURE` |
| 2. Motivo da falha descartado | **corrigido** — `last_http_code`, `last_error`, `last_response`, `last_reason` |
| 3. Fila de falhas sem leitura, teto ou expiração | **teto corrigido** — `FORWARD_FAILED_CAP` e contadores; continua sem consumidor, e o acumulado histórico foi apagado a 4 de setembro |
| 4. Não existe alarme | **fora de âmbito** — o projeto é a ponte para as bases das plataformas, e alarmística não lhe pertence |
| 5. Todas as falhas tratadas como iguais | **corrigido** — `App\BatchOutcome` classifica e a recusa é localizada |
| 6. Não há espera entre tentativas | **corrigido** — espera crescente entre `FORWARD_RETRY_BASE_MS` e `FORWARD_RETRY_MAX_MS` |
| 7. Canal `radar:ingest:*` sem subscritores | **corrigido** — chamada removida, e com ela oito dependências mortas do `composer.json` |
| 8. Licença que comece a produzir dados sem ter consumidor | **corrigido** — consumidor de recolha em execução e `ALLOWED_LICENSES` removida. Ocorreu na licença 2051, de 26 de maio a 4 de setembro |
| 9. Mensagens só em memória entre a fila e a entrega | **corrigido** — `LMOVE` para lista de trânsito, com recuperação no arranque |
| 10. Sem identificador que permita desduplicar | **habilitado** — o `traceId` acompanha cada mensagem; desduplicar é decisão da plataforma |
| 11. Elemento ilegível na fila derruba o consumidor em ciclo | **corrigido** — `App\QueueItem` valida à leitura, e o que não serve vai para `mqtt:forward_invalid:{licenca}` |
| 12. Índices de recusa fora do lote geram ciclo infinito | **corrigido** — só contam índices existentes no lote; sem nenhum, recusa-se o lote inteiro |
| 13. Licença do tópico entrava em cru na chave do Redis | **corrigido** — validada como inteiro positivo no `mqtt-worker` e em `getQueueKeys` |
| 14. Espera crescente do subscritor no ramo errado | **corrigido** — aplica-se agora a ligação perdida e a ligação recusada |

Treze corrigidas. Uma fechada por decisão: a alarmística não pertence a este
projeto.

As correções vivem no `forward-consumer.php`, no `mqtt-worker.php` e em
`src/BatchOutcome.php`, `src/OutboundBatch.php` e `src/QueueItem.php`, com testes
em `tests/`. Nada disto exigiu alterações ao `ingest.php` das plataformas.

A exceção é o ponto 8, corrigido a 4 de setembro sem escrever código: a falha
estava na configuração e na topologia dos serviços, e resolveu-se removendo uma
variável de ambiente e ligando uma unidade que já estava instalada.

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

**Corrigido a 4 de setembro de 2026, depois de ter ocorrido.** Este ponto estava
descrito como risco por realizar. Verificou-se que já se tinha concretizado, na
licença 2051, e que o mecanismo real era pior do que o previsto.

### O que se passou

O consumidor da licença 2051 foi parado a 26 de maio de 2026. Dois dias depois,
o commit `e23e8a6` introduziu a variável `ALLOWED_LICENSES` no `mqtt-worker`, e
a licença ficou de fora da lista. De 26 de maio a 4 de setembro os três radares
dessa instalação continuaram a publicar, a um ritmo medido de 275 mensagens por
minuto, e nada disso chegou à plataforma do cliente. São da ordem dos 40 milhões
de mensagens.

### Porque foi pior do que o previsto

A descrição original supunha que as mensagens de uma licença sem consumidor se
acumulariam numa fila por ler. Uma fila é observável: aparece no `LLEN`, ocupa
memória, e o ciclo de verificação do [capítulo 05](05-operacao.md) mostra-a.

Não foi o que aconteceu. O teste da `ALLOWED_LICENSES` está no `mqtt-worker`
**antes** do `RPUSH`, e o ramo que recusa é um `return` sem registo:

```php
if ($allowedLicenses !== null && !in_array($idLicenca, $allowedLicenses, true)) {
    return;
}
```

As mensagens não chegaram a entrar em fila nenhuma. Não havia lista a crescer,
contador a subir, nem linha no registo — e, por não haver fila, também não havia
nada para recuperar depois. As restantes três recusas da mesma função registam
todas um aviso; esta era a única silenciosa.

### Como foi detetado

Por comparação entre duas fontes independentes: as licenças que publicam no
broker, obtidas com uma subscrição de dois minutos a `radar/#`, e as unidades
systemd em execução. A 2051 aparecia na primeira e não na segunda. Nenhum registo
do próprio projeto continha indício da falha.

### Correção

Duas alterações de operação, sem código:

1. **`ALLOWED_LICENSES` esvaziada.** Com a lista vazia o subscritor aceita todas
   as licenças, e deixa de existir descarte silencioso.
2. **Consumidor de recolha em execução.** A unidade `mqtt-forward-generic@1`
   estava instalada e ativada, mas parada desde sempre. Serve todas as licenças
   fora do seu `--exclude`, pelo que uma licença nova passa a ser entregue sem
   intervenção.

As duas em conjunto fecham os dois lados: sem a primeira, uma licença nova
desaparecia; sem a segunda, acumularia fila até esgotar o Redis, que está em
`noeviction`.

**A lista `--exclude` do consumidor de recolha passa a ser o único sítio que
exige sincronia**, e a regra está no [capítulo 05](05-operacao.md): uma licença
que ganhe unidade dedicada tem de constar ali. Dois consumidores na mesma fila
partilham a lista de trânsito, o que duplica entregas e perde mensagens pelos
mecanismos descritos no ponto 9.

### O que não foi feito

A **unidade templada** continua por fazer. Passou a ser conveniência de manutenção
e não correção de defeito, uma vez que o consumidor de recolha já garante a
entrega de qualquer licença.

Um **aviso no descarte** da whitelist chegou a ser considerado, e deixou de ter
objeto: com a lista vazia não há descarte que registar.

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
