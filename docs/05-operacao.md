# 05 — Operação

## 1. Instalação em produção

O projeto corre no servidor do broker, em `/root/mqtt-radars`, como checkout do
ramo `main`.

> O trabalho faz-se no ramo `dev` e promove-se para `main` quando estiver
> acordado. Antes de começar, confirmar que o `dev` não está atrás: já esteve
> sete commits atrasado sem ter nada de próprio, estado em que promovê-lo para
> `main` reverteria produção em vez de a atualizar.
>
> ```bash
> git checkout dev && git merge --ff-only origin/main
> ```

Atualização:

```bash
cd /root/mqtt-radars
git fetch origin
git checkout main
git pull
composer install --no-dev
systemctl restart mqtt-worker mqtt-forward-1001 mqtt-forward-2004 mqtt-forward-2103
```

## 2. Unidades systemd

Uma unidade para o subscritor:

```ini
# /etc/systemd/system/mqtt-worker.service
[Unit]
Description=MQTT Radar Worker
After=network.target redis.service

[Service]
Type=simple
User=root
WorkingDirectory=/root/mqtt-radars
ExecStart=/usr/bin/php mqtt-worker.php
Restart=on-failure
RestartSec=10

[Install]
WantedBy=multi-user.target
```

E uma unidade por licença para os consumidores:

```ini
# /etc/systemd/system/mqtt-forward-1001.service
[Unit]
Description=MQTT Forward Consumer for license 1001
After=network.target redis.service

[Service]
Type=simple
User=root
WorkingDirectory=/root/mqtt-radars
ExecStart=/usr/bin/php forward-consumer.php --license=1001
Restart=always
RestartSec=2

[Install]
WantedBy=multi-user.target
```

Existem unidades para as licenças 1001, 2004 e 2103, e **não corre nenhum
consumidor genérico** — as três instâncias arrancam todas com `--license=N`. O
modo genérico existe no código e nada o usa.

As unidades são fixas e não templadas. Acrescentar uma licença implica criar um
ficheiro novo, e enquanto isso não for feito as mensagens dessa licença
acumulam-se numa fila que ninguém lê, sem erro e sem nada que o assinale. Ver o
[capítulo 06](06-falhas-conhecidas.md).

## 3. Configuração

O ficheiro `.env` é lido a partir do diretório de trabalho pelo `bootstrap.php`.

| Variável | Por omissão | Notas |
|---|---|---|
| `MQTT_SERVER` | `127.0.0.1` | |
| `MQTT_PORT` | `1883` | |
| `MQTT_USERNAME` | vazio | Vazio é tratado como ausente |
| `MQTT_PASSWORD` | vazio | Vazio é tratado como ausente |
| `MQTT_TOPIC` | vazio | Em produção, `radar/+/+` |
| `MQTT_CLIENT_ID` | `php-radar-router` | Estável e sem PID; ver [capítulo 01](01-arquitectura.md) |
| `ALLOWED_LICENSES` | vazio | Lista separada por vírgulas; vazio aceita todas |
| `REDIS_URL` | `tcp://127.0.0.1:6379` | |
| `CRM_URL` | `https://crm.hitcare.net/api/get.url.php` | |
| `CRM_CACHE_TTL` | `3600` | Segundos |
| `TEST_TARGET_URL` | vazio | Sobrepõe-se ao CRM; desenvolvimento apenas |
| `FORWARD_SLEEP_MS` | `50` | Espera quando todas as filas estão vazias |
| `FORWARD_CONNECT_TIMEOUT_MS` | `750` | Em produção, `5000` |
| `FORWARD_TIMEOUT_MS` | `5000` | Em produção, `60000` |
| `FORWARD_MAX_ATTEMPTS` | `3` | |
| `FORWARD_BATCH_SIZE` | `100` | |
| `FORWARD_LICENSE` | vazio | Equivalente a `--license` |
| `FORWARD_EXCLUDE_LICENSES` | vazio | Equivalente a `--exclude` |
| `FORWARD_DRY_RUN` | `false` | Regista o destino sem enviar; as mensagens voltam à fila |
| `FORWARD_DISABLE_BATCH` | — | Reconhecida e ignorada |
| `FORWARD_RETRY_BASE_MS` | `1000` | Espera antes da primeira retentativa |
| `FORWARD_RETRY_MAX_MS` | `30000` | Teto da espera, que dobra a cada tentativa |
| `FORWARD_FAILED_CAP` | `5000` | Últimas N entradas guardadas nas filas de falhas e de inválidas; `0` desliga o teto |
| `FORWARD_TLS_INSECURE` | `false` | Desliga a verificação de certificado. Exceção por instalação, com data e motivo — ver [capítulo 04](04-entrega.md) |

Os valores por omissão do código diferem dos usados em produção nos dois tempos
limite. Ao diagnosticar, ler o `.env` da instalação em vez de assumir os
defaults.

## 4. Testes

A lógica que decide o destino de cada lote está isolada em classes sem
dependências, para poder ser exercitada sem broker, sem Redis e sem plataforma:

```bash
php tests/BatchOutcomeTest.php    # classificação da resposta da plataforma
php tests/OutboundBatchTest.php   # construção do corpo enviado, incluindo o traceId
php tests/QueueItemTest.php       # leitura e validação dos elementos da fila
```

Correm sem `composer install` — cada teste inclui diretamente a classe que
exercita. Devolvem código de saída diferente de zero quando falham, e podem ser
encadeados:

```bash
for t in tests/*.php; do php "$t" || break; done
```

## 5. Comandos

```bash
systemctl status mqtt-worker
journalctl -u mqtt-worker -f

systemctl status mqtt-forward-2103
journalctl -u mqtt-forward-2103 -f
```

Execução manual, útil para diagnóstico:

```bash
cd /root/mqtt-radars
php forward-consumer.php --license=2103 --dry-run    # resolve e regista, não envia
php forward-consumer.php --exclude=1001,2004         # todas menos estas
```

## 6. Verificações

**As filas estão a drenar.** Em funcionamento normal aproximam-se de zero. Um
valor que cresce indica uma plataforma a recusar ou a responder devagar.

```bash
for l in 1001 2004 2103; do
  printf "%s: %s pendentes, %s em trânsito, %s falhadas, %s inválidas\n" "$l" \
    "$(redis-cli llen mqtt:forward:$l)" \
    "$(redis-cli llen mqtt:forward:$l:processing)" \
    "$(redis-cli llen mqtt:forward_failed:$l)" \
    "$(redis-cli llen mqtt:forward_invalid:$l)"
done
```

A coluna **em trânsito** deve estar a zero ou perto disso. Um valor parado com o
consumidor a correr não é normal; com o consumidor parado, é o que ficou por
entregar e volta à fila no próximo arranque.

A coluna **inválidas** deve estar a zero. Qualquer valor significa que alguém
escreveu na fila algo que não é uma mensagem — ver o [capítulo 03](03-redis.md).

**O que está a falhar, e com que código.** Os contadores respondem sem percorrer
listas:

```bash
redis-cli hgetall mqtt:forward:stats:2103
```

O que interessa é a variação entre duas leituras: são totais acumulados e nunca
são reiniciados.

**As falhas não estão a acumular.** Duas leituras separadas por alguns segundos
mostram se o crescimento é atual ou histórico. Como nada consome a fila de
falhas, um valor elevado pode ser antigo.

**A política de memória do Redis.** Ver o [capítulo 03](03-redis.md).

```bash
redis-cli config get maxmemory-policy    # deve ser noeviction
```

**O subscritor está ligado.** Um único cliente com o identificador configurado
deve aparecer no broker. Identificadores repetidos denunciam-se assim:

```bash
journalctl -u mosquitto | grep "already connected"
```

**Datas das falhas mais antiga e mais recente**, para distinguir um incidente em
curso de resíduo histórico:

```bash
redis-cli lindex mqtt:forward_failed:2103 0     # mais antiga
redis-cli lindex mqtt:forward_failed:2103 -1    # mais recente
```

## 7. Limpeza da fila de falhas

Nada consome estas listas, pelo que a remoção é manual. Uma lista longa não deve
ser apagada de uma só vez: libertar milhões de elementos num `DEL` ou `LTRIM`
bloqueia o Redis durante segundos, e nesse período os consumidores param.

A remoção faz-se por blocos, sempre a partir da cabeça, que é onde estão as mais
antigas. Elementos acrescentados durante a operação ficam na cauda e sobrevivem.

```bash
# remover as 500 000 mais antigas da licença 2103, em blocos de 50 000
for i in $(seq 10); do redis-cli lpop mqtt:forward_failed:2103 50000 > /dev/null; done
```

Antes de remover, confirmar o intervalo de datas afetado com os comandos da
secção anterior. As entradas estão por ordem cronológica, e é essa ordem que
torna a remoção pela cabeça segura.
