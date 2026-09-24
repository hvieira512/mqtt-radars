#!/bin/bash
# Provisiona a máquina do broker a partir deste repositório.
#
# Corre na própria máquina, a partir do checkout em /opt/mqtt-radars, pela
# mesma razão que os alvos do Makefile do hub: a instalação vem do diretório
# onde o script está, e não de um argumento que se possa passar mal.
#
# Não toca em segredos. O .env, o /etc/mosquitto/passwd e o /etc/letsencrypt
# não estão neste repositório e vêm da cópia de segurança, com o
# restaurar-segredos.sh que lá está.
set -euo pipefail

[ "$(id -u)" = 0 ] || { echo "Correr como root." >&2; exit 1; }

REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
D="$REPO/deploy"

echo "== Pacotes"
# O mosquitto, o composer e o php-pecl-redis6 não estão nos repositórios base do
# AlmaLinux: vêm do EPEL, e sem ele o dnf falha na primeira linha.
dnf install -y epel-release

# O código assume PHP 8.0. Uma versão mais recente passa em desenvolvimento e
# rebenta aqui: já aconteceu com o array_is_list.
#
# O rsyslog e o logrotate costumam já estar instalados, e constam da lista para
# o script não depender disso — as validações mais abaixo chamam os dois.
#
# O `rsyslog-logrotate` é um subpacote à parte, e é ele que instala a rotação do
# /var/log/messages. Não estava instalado na máquina antiga, e é essa a razão
# pela qual aquele ficheiro cresceu até 35 GB sem nunca rodar.
dnf install -y \
    mosquitto redis certbot logrotate rsyslog rsyslog-logrotate git \
    php-cli php-pecl-redis6 composer

# O utilizador do broker é criado pelo systemd-sysusers a partir do
# /usr/lib/sysusers.d/mosquitto.conf que o pacote traz. Numa máquina com systemd
# isto já aconteceu e a chamada não faz nada; a linha existe para o install
# abaixo não falhar com um "invalid user" difícil de ler.
id mosquitto >/dev/null 2>&1 || systemd-sysusers /usr/lib/sysusers.d/mosquitto.conf

php_version=$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')
[ "$php_version" = "8.0" ] || echo "AVISO: PHP $php_version, e não 8.0. Correr os testes antes de arrancar."

echo "== Broker"
install -d -m 750 -o mosquitto -g mosquitto /var/log/mosquitto
install -m 644 -o root -g root "$D/mosquitto.conf" /etc/mosquitto/mosquitto.conf
install -m 640 -o mosquitto -g mosquitto "$D/mosquitto.acl" /etc/mosquitto/acl

echo "== Redis"
install -m 644 -o root -g root "$D/redis-mqtt.conf" /etc/redis/mqtt.conf
# O include vai no fim, onde a última diretiva ganha.
grep -qxF 'include /etc/redis/mqtt.conf' /etc/redis/redis.conf \
    || echo 'include /etc/redis/mqtt.conf' >> /etc/redis/redis.conf

echo "== Logs"
# As duas fugas que encheram 40 GB dos 42 GB da máquina antiga: o log do broker,
# que não tinha rotação nenhuma, e a segunda cópia das linhas do subscritor no
# /var/log/messages, que não tinha rotação nem razão para existir.
#
# A rotação do /var/log/messages vem do pacote rsyslog-logrotate, acima. Aqui só
# entra o que é nosso.
install -m 644 "$D/logrotate/mosquitto" /etc/logrotate.d/mosquitto
install -m 644 "$D/rsyslog-mqtt-radars.conf" /etc/rsyslog.d/10-mqtt-radars.conf

# Com a cópia para o /var/log/messages cortada, o journal passa a ser o único
# sítio onde ficam as linhas do subscritor, e o teto por omissão são 10% do
# disco — perto de 10 GB numa máquina destas.
install -d -m 755 /etc/systemd/journald.conf.d
install -m 644 "$D/journald-mqtt-radars.conf" /etc/systemd/journald.conf.d/10-mqtt-radars.conf
systemctl restart systemd-journald

# É aqui que as duas passam por um parser pela primeira vez: nem o logrotate nem
# o rsyslogd aceitam configuração que não seja um ficheiro em disco, e por isso
# não há como as validar antes de chegarem à máquina.
rsyslogd -N1 >/dev/null 2>&1 || { echo "A configuração do rsyslog não passa o rsyslogd -N1." >&2; exit 1; }
# O logrotate devolve 0 mesmo quando rejeita uma opção, e por isso a deteção tem
# de ser pelo texto. O filtro é pelo caminho do ficheiro de propósito: um `grep
# error:` largo apanha também os erros de ambiente — o ficheiro de estado que
# ainda não existe, por exemplo — e abortaria a instalação por outra razão.
#
# A saída é capturada antes de ser filtrada, e não canalizada para um `grep -q`:
# com `pipefail`, o grep sai ao primeiro acerto, o logrotate morre com SIGPIPE, o
# pipeline devolve 141 e o teste dá-se por não cumprido exactamente quando
# encontrou o erro que procurava.
rotacao=$(logrotate -d /etc/logrotate.d/mosquitto 2>&1 || true)
if erros=$(printf '%s\n' "$rotacao" | grep -E "error:.*/etc/logrotate\.d/mosquitto"); then
    echo "A rotação do mosquitto tem um erro de sintaxe:" >&2
    printf '%s\n' "$erros" >&2
    exit 1
fi
systemctl restart rsyslog
echo "   rotação e filtro instalados, e ambos passam o parser"

echo "== Dependências do PHP"
# O composer.lock é a fonte de verdade. Sem --no-interaction, num terminal
# não interativo o comando fica à espera de alguém.
( cd "$REPO" && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction )

echo "== Testes"
# Correm sem broker e sem Redis, e é o que apanha uma incompatibilidade de
# versão do PHP antes de a máquina receber tráfego.
( cd "$REPO" && for t in tests/*.php; do php "$t" >/dev/null || { echo "FALHOU: $t" >&2; exit 1; }; done )
echo "   os três passam"

echo "== Unidades"
install -m 644 "$D"/systemd/*.service /etc/systemd/system/
systemctl daemon-reload
systemctl enable redis mosquitto >/dev/null
systemctl enable mqtt-worker >/dev/null
systemctl enable mqtt-forward-1001 mqtt-forward-2004 mqtt-forward-2051 mqtt-forward-2103 >/dev/null
systemctl enable 'mqtt-forward-generic@1' >/dev/null
echo "   instaladas e enabled, nenhuma arrancada"

cat <<'FIM'

== Feito. Falta o que não está neste repositório:

  1. Da máquina de onde veio a cópia, correr o restaurar-segredos.sh:
     o .env, o /etc/mosquitto/passwd, o /etc/letsencrypt e o authorized_keys.

  2. Encaminhar o endereço para esta máquina. Se o IP for o mesmo, é só
     atribuí-lo; se for outro, ver "Mudar de endereço" no docs/05-operacao.md,
     que lista tudo o que tem de ser reapontado.

  3. Arrancar, por esta ordem — o subscritor em último, porque é o único que
     perde mensagens enquanto está em baixo:

     systemctl start redis mosquitto
     systemctl start mqtt-forward-1001 mqtt-forward-2004 mqtt-forward-2051 mqtt-forward-2103
     systemctl start mqtt-forward-generic@1
     systemctl start mqtt-worker

  4. deploy/verificar.sh
FIM
