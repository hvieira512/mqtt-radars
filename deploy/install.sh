#!/bin/bash
# Provisiona o router de radares a partir deste repositório.
#
# Corre na própria máquina, a partir do checkout em /opt/mqtt-radars, pela mesma
# razão que os alvos do Makefile do hub: a instalação vem do diretório onde o
# script está, e não de um argumento que se possa passar mal.
#
# **Não provisiona o broker.** O mosquitto, o seu ACL, as suas passwords e os
# seus certificados servem também as duas instâncias do hub, o hub de NCS e a
# plataforma, e são mantidos por outra via. Este script trata do que é nosso: as
# dependências de PHP, a sobreposição do Redis, o teto do journal e as unidades
# dos nossos processos.
#
# Não toca em segredos. O .env não está neste repositório e vem da cópia de
# segurança, com o restaurar-segredos.sh que lá está.
set -euo pipefail

[ "$(id -u)" = 0 ] || { echo "Correr como root." >&2; exit 1; }

REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
D="$REPO/deploy"

echo "== Pacotes"
# Debian 13. O predis é PHP puro e não precisa da extensão redis; o php-curl é
# que é exigido, porque a entrega às plataformas e a consulta ao CRM são curl.
#
# O mosquitto-clients entra pelo verificar.sh, que confirma o tráfego com um
# mosquitto_sub. O broker em si não se instala aqui.
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y --no-install-recommends \
    git composer redis-server mosquitto-clients \
    php-cli php-curl php-mbstring

php_version=$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')
echo "   PHP $php_version"

echo "== Redis"
install -m 644 -o root -g root "$D/redis-mqtt.conf" /etc/redis/mqtt.conf
# O include vai no fim, onde a última diretiva ganha.
grep -qxF 'include /etc/redis/mqtt.conf' /etc/redis/redis.conf \
    || echo 'include /etc/redis/mqtt.conf' >> /etc/redis/redis.conf

echo "== Journal"
# As linhas do subscritor e dos consumidores só ficam no journal, e o teto por
# omissão são 10% do disco.
install -d -m 755 /etc/systemd/journald.conf.d
install -m 644 "$D/journald-mqtt-radars.conf" /etc/systemd/journald.conf.d/10-mqtt-radars.conf
systemctl restart systemd-journald

echo "== Dependências do PHP"
# O composer.lock é a fonte de verdade. Sem --no-interaction, num terminal
# não interativo o comando fica à espera de alguém.
( cd "$REPO" && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction )

echo "== Testes"
# Correm sem broker e sem Redis, e é o que apanha uma incompatibilidade de
# versão do PHP antes de a máquina receber tráfego.
( cd "$REPO" && for t in tests/*.php; do php "$t" >/dev/null || { echo "FALHOU: $t" >&2; exit 1; }; done )
echo "   os testes passam"

echo "== Unidades"
install -m 644 "$D"/systemd/*.service /etc/systemd/system/
systemctl daemon-reload
systemctl enable redis-server >/dev/null
systemctl enable mqtt-worker >/dev/null
systemctl enable mqtt-forward-1001 mqtt-forward-2004 mqtt-forward-2103 mqtt-forward-2137 >/dev/null
systemctl enable 'mqtt-forward-generic@1' >/dev/null
echo "   instaladas e enabled, nenhuma arrancada"

cat <<'FIM'

== Feito. Falta o que não está neste repositório:

  1. O .env, da cópia de segurança, com o restaurar-segredos.sh.

  2. O broker. Este script não o instala nem o configura: confirmar com quem o
     mantém que o mosquitto está de pé, que o utilizador do router existe no
     ACL, e qual o endereço a pôr em MQTT_SERVER.

  3. Arrancar, por esta ordem — o subscritor em último, porque é o único que
     perde mensagens enquanto está em baixo:

     systemctl start redis-server
     systemctl start mqtt-forward-1001 mqtt-forward-2004 mqtt-forward-2103 mqtt-forward-2137
     systemctl start mqtt-forward-generic@1
     systemctl start mqtt-worker

  4. deploy/verificar.sh
FIM
