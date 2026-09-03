# 01 — Arquitetura

## 1. Os dois processos

O projeto é composto por dois programas que nunca se falam diretamente. A única
coisa que partilham é o Redis.

**`mqtt-worker.php`** mantém uma ligação ao broker MQTT, subscreve o filtro
configurado em `MQTT_TOPIC` e, para cada mensagem recebida, extrai a licença do
tópico e coloca a mensagem numa fila do Redis.

**`forward-consumer.php`** retira mensagens dessa fila, resolve o endereço da
plataforma do cliente e entrega-as por HTTP. Cada instância pode servir uma
licença específica ou todas as que estiverem registadas.

```
                        ┌─────────────────────┐
Radar ──radar/{lic}/{uid}──► Broker MQTT      │
                        └──────────┬──────────┘
                                   │ subscrição
                        ┌──────────▼──────────┐
                        │   mqtt-worker.php   │
                        └──────────┬──────────┘
                                   │ RPUSH
                        ┌──────────▼──────────┐
                        │       Redis         │
                        │ mqtt:forward:{lic}  │
                        └──────────┬──────────┘
                                   │ LMOVE, em lotes
                        ┌──────────▼──────────┐
                        │ forward-consumer.php│
                        └──────────┬──────────┘
                                   │ POST
                        ┌──────────▼──────────┐
                        │ Plataforma cliente  │
                        └─────────────────────┘
```

A separação existe para que a lentidão de uma plataforma não bloqueie a receção.
O subscritor nunca espera por HTTP: escreve na fila e volta imediatamente ao
broker.

## 2. O broker é partilhado

O broker MQTT não pertence a este projeto. É a mesma instância de Mosquitto que
serve o Havicare Hub e os seus subscritores, e os radares publicam-lhe
diretamente.

Isto tem duas consequências que importam ao operar o sistema.

A primeira é que **o fluxo de radares tem mais do que um consumidor**. Além do
`mqtt-worker`, o Havicare Hub subscreve os mesmos tópicos para os normalizar e
republicar noutro espaço. Os dois consumos são independentes e nenhum depende do
outro.

A segunda é que **um reinício do broker afeta todo o ecossistema**, e não apenas
este projeto. O `mqtt-worker` recupera sozinho, mas as mensagens publicadas
enquanto estiver desligado perdem-se, porque a subscrição usa sessão limpa e o
broker não guarda nada para sessões ausentes.

## 3. Identificador de cliente estável

O `mqtt-worker` liga-se com um identificador fixo, sem PID, definido em
`MQTT_CLIENT_ID`. Isto é deliberado e não deve ser alterado para um valor
gerado.

A consequência é que **só pode existir uma instância do subscritor**. Dois
clientes com o mesmo identificador expulsam-se mutuamente do broker num ciclo
que se manifesta como perda intermitente de mensagens, e cuja causa é difícil de
identificar a partir dos sintomas.

O `forward-consumer` não se liga ao broker e não tem esta restrição: podem
correr várias instâncias em paralelo.

## 4. Recuperação de falhas

O `mqtt-worker` implementa o seu próprio ciclo de reconexão, com espera
exponencial de dois até sessenta segundos, aplicada tanto a uma ligação perdida
como a uma ligação recusada. O processo não termina quando a ligação cai; volta
ao início do ciclo e tenta de novo. O `Restart=on-failure` da unidade systemd
existe como rede de segurança e, na prática, não chega a ser acionado.

O `forward-consumer` não tem ciclo de reconexão porque a sua única dependência
permanente é o Redis, local. Falhas de HTTP são tratadas como parte do fluxo
normal e estão descritas no [capítulo 04](04-entrega.md).

## 5. O que este projeto não faz

Não interpreta o conteúdo dos payloads dos radares. O `payload` recebido é
entregue tal como chegou, e toda a descodificação — posições, sinais vitais,
deteções — acontece do lado da plataforma do cliente.

Não tem base de dados. O estado que existe vive no Redis e está descrito no
[capítulo 03](03-redis.md).

Não reenvia mensagens que tenham esgotado as tentativas de entrega. Ver o
[capítulo 06](06-falhas-conhecidas.md).
