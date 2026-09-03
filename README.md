# mqtt-radars

Encaminhador de dados de radares Qinglan para as plataformas dos clientes.

Os radares publicam por MQTT no broker partilhado. Este projeto subscreve esse
fluxo, enfileira-o no Redis e entrega-o por HTTP à plataforma do cliente a que a
licença corresponde.

São dois processos independentes ligados por uma fila:

```
Radares ──MQTT──► mqtt-worker ──Redis──► forward-consumer ──HTTP──► Plataforma
```

O projeto é anterior ao Havicare Hub e não depende dele. Vive no seu próprio
espaço de tópicos, `radar/{licenca}/{uid}`, onde o conceito de `company` não
existe: a licença é sempre hitcare.

## Documentação

| Capítulo | Conteúdo |
|---|---|
| [01 — Arquitetura](docs/01-arquitectura.md) | Os dois processos, o broker partilhado, e o que é responsabilidade de quem |
| [02 — Tópicos e payloads](docs/02-topicos-e-payloads.md) | O contrato de entrada e o que é transformado antes da entrega |
| [03 — Chaves do Redis](docs/03-redis.md) | Todas as chaves, tipos, expiração, e quem escreve e lê cada uma |
| [04 — Entrega](docs/04-entrega.md) | Resolução do destino, lotes, códigos de resposta e retentativas |
| [05 — Operação](docs/05-operacao.md) | Unidades systemd, configuração, comandos e verificações |
| [06 — Falhas conhecidas](docs/06-falhas-conhecidas.md) | Defeitos de desenho identificados, com estado e prioridade |

## Arranque rápido

```bash
composer install
cp .env.example .env    # preencher broker, Redis e CRM

php mqtt-worker.php                        # subscritor
php forward-consumer.php --license=2103    # consumidor de uma licença
```

O detalhe da configuração está no [capítulo 05](docs/05-operacao.md).

## Testes

```bash
for t in tests/*.php; do php "$t" || break; done
```

A lógica que decide o destino de cada lote vive em `src/`, sem dependências, e
os testes incluem-na diretamente — correm sem broker, sem Redis, sem plataforma
e sem `composer install`.

## Estrutura

| Ficheiro | Papel |
|---|---|
| `mqtt-worker.php` | Subscreve o broker e enfileira no Redis |
| `forward-consumer.php` | Retira da fila e entrega por HTTP à plataforma |
| `src/QueueItem.php` | Lê e valida um elemento da fila |
| `src/OutboundBatch.php` | Constrói o corpo que segue para a plataforma |
| `src/BatchOutcome.php` | Decide o destino do lote a partir da resposta |
| `src/Logger.php` | Registo em consola |
| `simulate-radars.php` | Gerador de tráfego para desenvolvimento |

## Requisitos

PHP 8.0 ou superior, Composer, Redis, e acesso ao broker MQTT.

O servidor corre 8.0, e é esse o mínimo a respeitar: funções introduzidas em 8.1
passam nos testes localmente e falham na instalação.
