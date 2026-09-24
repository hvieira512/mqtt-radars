#!/bin/bash
# Verifica a instalação do router de radares. Corre na própria máquina, depois
# do install.sh e do restauro do .env.
#
# Verifica o que é nosso. O broker — o ACL, as passwords, os certificados, a
# rotação do log dele — é mantido por outra via, e afirmar aqui como ele devia
# estar configurado só produz falsos alarmes. Do broker verifica-se uma coisa
# só: que responde e que a nossa telemetria passa por ele.
#
# Devolve código diferente de zero se alguma verificação falhar, para poder
# ser encadeado. Não altera nada.
#
# Sem `pipefail`, e é deliberado. Quase todos os testes abaixo terminam num
# `grep -q`, que sai ao primeiro acerto e mata o produtor com SIGPIPE; com
# `pipefail` o pipeline passaria a devolver 141 e o veredicto do grep seria
# descartado. Nos testes negados — `! … | grep -q 'already connected'` — isso
# inverte-se em «ok» precisamente quando o problema existe.
set -u

falhas=0
ok()    { printf "  ok    %s\n" "$1"; }
falha() { printf "  FALHA %s\n" "$1"; falhas=$((falhas + 1)); }
teste() { if eval "$2" >/dev/null 2>&1; then ok "$1"; else falha "$1"; fi; }

ENV=/opt/mqtt-radars/.env

echo "== Serviços"
for s in redis-server mqtt-worker \
         mqtt-forward-1001 mqtt-forward-2004 mqtt-forward-2103 mqtt-forward-2137 \
         'mqtt-forward-generic@1'; do
    teste "$s ativo" "[ \"\$(systemctl is-active '$s')\" = active ]"
done

echo "== Redis"
teste "6379 só em localhost"                  "ss -tln | grep ':6379 ' | grep -qE '127\.0\.0\.1|\[::1\]'"
teste "redis responde"                        "[ \"\$(redis-cli ping)\" = PONG ]"
# Sem isto, um Redis a encher descarta mensagens em fila em silêncio.
teste "maxmemory-policy é noeviction"         "redis-cli config get maxmemory-policy | grep -qx noeviction"

echo "== .env"
teste ".env presente"                         "[ -f $ENV ]"
teste "MQTT_TOPIC é radar/+/+"                "grep -qx 'MQTT_TOPIC=radar/+/+' $ENV"
teste "ALLOWED_LICENSES vazia"                "grep -qx 'ALLOWED_LICENSES=' $ENV"
# Chaves que o código não lê: uma delas no .env é ruído que engana quem
# diagnostica, porque parece configuração e não tem efeito nenhum.
for k in FORWARD_DISABLE_BATCH; do
    teste "sem a chave morta $k"              "! grep -q '^$k=' $ENV"
done

echo "== Broker"
# O endereço sai do .env e não se assume `127.0.0.1`: o broker escuta no
# endereço da rede interna, e um teste contra o loopback falha sem que nada
# esteja mal.
if [ -f "$ENV" ]; then
    h=$(grep -m1 '^MQTT_SERVER=' "$ENV" | cut -d= -f2- | tr -d '"')
    P=$(grep -m1 '^MQTT_PORT=' "$ENV" | cut -d= -f2- | tr -d '"')
    u=$(grep -m1 '^MQTT_USERNAME=' "$ENV" | cut -d= -f2- | tr -d '"')
    p=$(grep -m1 '^MQTT_PASSWORD=' "$ENV" | cut -d= -f2- | tr -d '"')
    teste "broker alcançável em $h:${P:-1883}"  "timeout 5 bash -c '</dev/tcp/$h/${P:-1883}'"
    teste "chega telemetria em radar/+/+"       "timeout 60 mosquitto_sub -h '$h' -p '${P:-1883}' -u '$u' -P '$p' -t 'radar/+/+' -C 1"
else
    falha "sem .env, não se verifica o broker"
fi

# Dois identificadores de cliente iguais expulsam-se do broker em ciclo. O teste
# olha só para o nosso: um radar ou um gateway a reconectar antes de o broker dar
# a sessão antiga por morta produz a mesma linha sem que nada esteja mal.
teste "sem o nosso identificador repetido"    "! journalctl -u mosquitto --since '24 hours ago' 2>/dev/null | grep 'already connected' | grep -q 'php-radar-router'"

echo "== Filas"
# Filas a crescer significam que a entrega não está a acompanhar. Vazias, ou
# com poucos elementos, é o estado normal.
for l in 1001 2004 2103 2137; do
    n=$(redis-cli LLEN "mqtt:forward:$l" 2>/dev/null || echo 0)
    t=$(redis-cli LLEN "mqtt:forward:processing:$l" 2>/dev/null || echo 0)
    printf "  fila %s: %s elementos, %s em trânsito\n" "$l" "${n:-0}" "${t:-0}"
done

echo
if [ "$falhas" -eq 0 ]; then
    echo "Tudo verificado."
else
    echo "$falhas verificação(ões) falharam."
fi
exit "$falhas"
