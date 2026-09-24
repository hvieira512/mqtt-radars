#!/bin/bash
# Verifica a instalação do broker. Corre na própria máquina, depois do
# install.sh e do restauro dos segredos.
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

echo "== Serviços"
for s in redis mosquitto mqtt-worker \
         mqtt-forward-1001 mqtt-forward-2004 mqtt-forward-2051 mqtt-forward-2103 \
         'mqtt-forward-generic@1'; do
    teste "$s ativo" "[ \"\$(systemctl is-active '$s')\" = active ]"
done

echo "== Portas"
teste "1883 a escutar (MQTT em claro)"        "ss -tln | grep -q ':1883 '"
teste "9001 a escutar (WebSockets)"           "ss -tln | grep -q ':9001 '"
teste "6379 só em localhost"                  "ss -tln | grep ':6379 ' | grep -qE '127\.0\.0\.1|\[::1\]'"
teste "redis responde"                        "[ \"\$(redis-cli ping)\" = PONG ]"

echo "== Configuração"
teste "acl com os cinco utilizadores"         "[ \$(grep -c '^user ' /etc/mosquitto/acl) -eq 5 ]"
teste "passwd com os cinco utilizadores"      "[ \$(grep -c . /etc/mosquitto/passwd) -eq 5 ]"
teste "certificado válido mais de 20 dias"    "openssl x509 -in /etc/letsencrypt/live/mqtt.havicare.net/cert.pem -noout -checkend 1728000"
teste "live/privkey.pem é ligação simbólica"  "[ -L /etc/letsencrypt/live/mqtt.havicare.net/privkey.pem ]"
teste "rotação do mosquitto instalada"        "logrotate -d /etc/logrotate.d/mosquitto"
teste "rotação do rsyslog instalada"          "logrotate -d /etc/logrotate.d/rsyslog"

echo "== .env"
ENV=/root/mqtt-radars/.env
teste ".env presente"                         "[ -f $ENV ]"
teste "MQTT_TOPIC é radar/+/+"                "grep -qx 'MQTT_TOPIC=radar/+/+' $ENV"
teste "ALLOWED_LICENSES vazia"                "grep -qx 'ALLOWED_LICENSES=' $ENV"
# Chaves que o código não lê: uma delas no .env é ruído que engana quem
# diagnostica, porque parece configuração e não tem efeito nenhum.
for k in FORWARD_DISABLE_BATCH; do
    teste "sem a chave morta $k"              "! grep -q '^$k=' $ENV"
done

echo "== Tráfego"
# A prova de que as duas famílias de tópicos estão a passar. Precisa das
# credenciais, que saem do .env da própria instalação.
if [ -f "$ENV" ]; then
    u=$(grep -m1 '^MQTT_USERNAME=' "$ENV" | cut -d= -f2-)
    p=$(grep -m1 '^MQTT_PASSWORD=' "$ENV" | cut -d= -f2-)
    teste "chega telemetria em radar/+/+"     "timeout 60 mosquitto_sub -h 127.0.0.1 -u '$u' -P '$p' -t 'radar/+/+' -C 1"
    teste "o hub publica em havicare-hub/#"   "timeout 60 mosquitto_sub -h 127.0.0.1 -u '$u' -P '$p' -t 'havicare-hub/#' -C 1"
else
    falha "sem .env, não se verifica o tráfego"
fi

echo "== Broker"
LOG=/var/log/mosquitto/mosquitto.log
# Dois identificadores de cliente iguais expulsam-se do broker em ciclo, e é
# assim que isso aparece. O teste olha só para os identificadores que nós
# configuramos: um radar ou um gateway a reconectar antes de o broker dar a
# sessão antiga por morta produz a mesma linha sem que nada esteja mal, e no log
# da máquina antiga eram essa a totalidade das 108 ocorrências.
teste "sem identificadores nossos repetidos"  "! tail -n 20000 $LOG 2>/dev/null | grep 'already connected' | grep -qE 'php-radar-router|health-mqtt|qinglanst-radar'"
teste "sem erros de socket recentes"          "! tail -n 20000 $LOG 2>/dev/null | grep -q 'Socket error'"

echo "== Filas"
# Filas a crescer significam que a entrega não está a acompanhar. Vazias, ou
# com poucos elementos, é o estado normal.
for l in 1001 2004 2051 2103; do
    n=$(redis-cli LLEN "mqtt:forward:queue:$l" 2>/dev/null || echo 0)
    printf "  fila %s: %s elementos\n" "$l" "${n:-0}"
done

echo
if [ "$falhas" -eq 0 ]; then
    echo "Tudo verificado."
else
    echo "$falhas verificação(ões) falharam."
fi
exit "$falhas"
