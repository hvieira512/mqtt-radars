# 02 — Tópicos e payloads

## 1. Estrutura do tópico

Os radares publicam em tópicos de três segmentos:

```
radar/{licenca}/{uid}
```

```
radar/2103/594B3CF10097
radar/2004/594B3CD234D3
```

O `{licenca}` é o identificador numérico da licença no CRM hitcare e é o **único
sítio de onde a licença é lida**. O corpo da mensagem não a contém, e o
encaminhamento depende inteiramente deste segmento.

**Só é aceite um inteiro positivo.** O segmento entra no nome da chave do Redis,
e um valor com dois pontos produziria uma chave que colide com as internas: um
tópico `radar/1001:processing/x` dava `mqtt:forward:1001:processing`, que é a
lista de trânsito da licença 1001 — e o que ali fosse escrito acabaria entregue
a esse cliente. Um tópico com um segmento inválido é registado como aviso e
descartado.

O `{uid}` é o endereço do radar. Coincide com o `deviceCode` que vem dentro do
payload, mas o encaminhador não verifica essa coincidência.

O filtro subscrito é configurável em `MQTT_TOPIC`. Em produção é `radar/+/+`,
que apanha todas as licenças. Um tópico com menos de três segmentos é registado
como aviso e descartado.

## 2. Formato da mensagem

O corpo é JSON com duas secções:

```json
{
  "header": {
    "traceId": "99017BD395285E96F",
    "payloadVersion": 1,
    "brand": "qinglan",
    "timestamp": 99017,
    "method": "device-penetrate"
  },
  "payload": {
    "deviceCode": "BD395285E96F",
    "heartbreath": "ABJFAAAAABEAF00AHEADAg=="
  }
}
```

O `header` identifica a mensagem na origem. O `payload` contém o `deviceCode` e
exatamente um campo de dados, codificado em base64, cujo nome indica o tipo de
leitura.

## 3. Tipos de leitura

A plataforma do cliente reconhece quatro, e exige que **exatamente um** esteja
presente e não vazio:

| Campo | Conteúdo |
|---|---|
| `position` | Posições instantâneas das pessoas detetadas |
| `heartbreath` | Ritmo cardíaco e respiratório |
| `posstatics` | Estatísticas de posição agregadas |
| `hbstatics` | Estatísticas de sinais vitais agregadas |

Uma mensagem com nenhum destes campos, ou com mais do que um, é recusada pela
plataforma. O encaminhador não faz esta validação e entrega-a na mesma.

A distribuição observada aproxima-se de três quartos de `position`, um quarto de
`heartbreath`, e uma fração residual dos dois tipos de estatísticas.

## 4. O que é transformado antes da entrega

O encaminhador não é transparente. Ao construir o pedido HTTP, reduz cada
mensagem a três campos:

```json
{
  "topic": "radar/2103/BD395285E96F",
  "payload": { "deviceCode": "...", "heartbreath": "..." },
  "traceId": "99017BD395285E96F"
}
```

O `payload` é extraído do envelope. Se a mensagem não tiver a chave `payload`, é
usado o objeto inteiro como payload.

O `traceId` é copiado do `header`, quando existe. É o identificador que o radar
atribui à leitura, e é a única forma de distinguir uma entrega repetida de uma
leitura nova — ver a secção 9 do [capítulo 04](04-entrega.md). Uma mensagem sem
`header` segue sem o campo.

**O resto do `header` é descartado.** O `timestamp` de origem, a marca e o método
não chegam à plataforma.

A construção do corpo está em `App\OutboundBatch`, com teste em
`tests/OutboundBatchTest.php`.

## 5. Formato entregue à plataforma

As mensagens são agrupadas num único pedido:

```json
{
  "batch": true,
  "messages": [
    { "topic": "radar/2103/...", "payload": { } },
    { "topic": "radar/2103/...", "payload": { } }
  ]
}
```

O destino é o caminho `/modulos/radares/_ajax/radar-data-ingest.php` sob o
endereço resolvido para a licença. O cabeçalho `Content-Type` é
`application/json`.

A plataforma trata o lote como uma transação única. As implicações disso estão
no [capítulo 04](04-entrega.md).
