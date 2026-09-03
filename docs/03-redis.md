# 03 — Chaves do Redis

O Redis é o único estado do projeto. Não há base de dados, e nada do que existe
em memória é reconstruível a partir de outra fonte: uma mensagem perdida do
Redis é uma mensagem perdida.

## 1. Todas as chaves

| Chave | Tipo | Expiração | Escreve | Lê |
|---|---|---|---|---|
| `mqtt:forward:{licenca}` | list | nenhuma | `mqtt-worker` | `forward-consumer` |
| `mqtt:forward:{licenca}:processing` | list | nenhuma | `forward-consumer` | `forward-consumer` |
| `mqtt:forward:licenses` | set | nenhuma | `mqtt-worker` | `forward-consumer` |
| `mqtt:forward_failed:{licenca}` | list | nenhuma | `forward-consumer` | ninguém |
| `mqtt:forward_invalid:{licenca}` | list | nenhuma | `forward-consumer` | ninguém |
| `mqtt:forward:stats:{licenca}` | hash | nenhuma | `forward-consumer` | operação |
| `crm:target:{licenca}` | string | `CRM_CACHE_TTL` | `forward-consumer` | `forward-consumer` |

## 2. `mqtt:forward:{licenca}`

A fila de entrega pendente. O subscritor acrescenta ao fim com `RPUSH`, o
consumidor retira do início com `LMOVE` para a lista de trânsito da secção
seguinte, o que dá ordem de chegada.

Cada elemento é um objeto JSON:

```json
{
  "topic": "radar/2103/BD395285E96F",
  "message": "{\"header\":{...},\"payload\":{...}}",
  "license": "2103",
  "queued_at": 1788401936,
  "queued_at_ms": 1788401936063,
  "attempts": 3
}
```

O `message` é o corpo MQTT original, por descodificar. O `attempts` só existe
depois da primeira falha de entrega; um elemento acabado de enfileirar não o
tem.

Uma fila que cresce indica que a plataforma daquele cliente está a recusar ou a
responder devagar. Em funcionamento normal, todas se mantêm perto de zero.

## 3. `mqtt:forward:{licenca}:processing`

Onde uma mensagem fica enquanto está a ser entregue. O consumidor não retira da
fila com `LPOP`: usa `LMOVE`, que a tira da fila e a põe aqui na mesma operação.
Ao confirmar a entrega, ou ao devolvê-la à fila, a lista é apagada.

Sem isto, entre sair da fila e a plataforma responder a mensagem existia apenas
na memória do processo — durante a chamada HTTP inteira, que pode demorar até
`FORWARD_TIMEOUT_MS`. Uma paragem nessa janela perdia o lote sem deixar rasto.

Ao arrancar, o consumidor devolve à cabeça da fila o que aqui encontrar, na
ordem original. **Isto pressupõe um consumidor por licença:** com dois, um deles
reclamaria o lote que o outro tem em voo.

Uma lista com conteúdo e sem consumidor a correr é o resto de uma paragem, e
resolve-se arrancando o consumidor.

## 4. `mqtt:forward:licenses`

Conjunto com as licenças que já foram vistas. O subscritor faz `SADD` a cada
mensagem, e o consumidor usa-o para saber que filas percorrer quando corre sem
`--license`.

O conjunto **só cresce**. Uma licença desativada permanece nele, e o consumidor
genérico continua a verificar a sua fila indefinidamente.

## 5. `mqtt:forward_failed:{licenca}`

Onde ficam as mensagens que esgotaram as tentativas de entrega, ou que foram
recusadas pela plataforma. Ao formato da fila de entrega juntam-se os campos de
diagnóstico descritos no [capítulo 04](04-entrega.md).

O tamanho é limitado por `FORWARD_FAILED_CAP`: a lista guarda as últimas N
entradas e as mais antigas caem. O número de falhas por código vive nos
contadores da secção seguinte, e é ali que se responde a «quanto» e «o quê»; a
lista serve para ver exemplos.

**Nada consome esta lista.** Não há reenvio automático, e as mensagens que aqui
entram não voltam a ser entregues.

## 6. `mqtt:forward_invalid:{licenca}`

Elementos da fila que não se conseguem interpretar como mensagem: JSON inválido,
`null`, uma lista, um objeto sem `topic` ou sem `message`. São guardados **em
cru**, tal como estavam na fila.

Não entram no lote, e é isso que importa: o código a jusante trata cada elemento
como objeto, e um valor que não o seja derruba o processo. Com `Restart=always`
na unidade e a recuperação do trânsito descrita acima, um único elemento
ilegível reentrava na fila a cada arranque e voltava a derrubar o consumidor —
um ciclo que não drenava nada.

O tamanho é limitado por `FORWARD_FAILED_CAP`, e o contador `unreadable:0`
regista quantos apareceram.

Uma lista com conteúdo significa que alguém escreveu na fila algo que não é uma
mensagem, e vale a pena ver o quê.

## 7. `mqtt:forward:stats:{licenca}`

Contadores acumulados por resultado e código HTTP, no formato
`{resultado}:{codigo}`:

```
delivered:200   184203
rejected:422        17
retry:500          902
```

Respondem a «o que está a falhar» com um `HGETALL`, sem percorrer listas. Não
expiram e não são reiniciados: são totais desde sempre, e o que interessa é a
variação entre duas leituras.

## 8. `crm:target:{licenca}`

Cache do endereço da plataforma do cliente, obtido do CRM. É a única chave com
expiração, definida por `CRM_CACHE_TTL`.

Quando expira, a consulta seguinte vai ao CRM e volta a preenchê-la. Um CRM
indisponível não interrompe a entrega enquanto a cache for válida, mas a partir
do momento em que expirar todas as mensagens dessa licença passam a falhar.

Apagar esta chave força a releitura do endereço, o que é o procedimento correto
quando uma plataforma muda de domínio.

## 9. Política de memória

O Redis usado por este projeto não é exclusivo dele. A política de expulsão
configurada na instância determina o que acontece quando a memória se esgota, e
a única aceitável para um servidor que guarda filas é `noeviction`.

Com uma política do tipo `allkeys-lru`, o Redis descarta chaves para libertar
espaço sem distinguir uma cache de uma fila por entregar. As mensagens em
`mqtt:forward:{licenca}` são candidatas à expulsão como qualquer outra chave, e
desaparecem sem registo.

Verificação:

```bash
redis-cli config get maxmemory-policy
```
