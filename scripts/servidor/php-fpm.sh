#!/usr/bin/env bash
# =========================================================
#  Cambiar Apache a PHP-FPM + MPM event + HTTP/2
#  Uso:  sudo bash scripts/servidor/php-fpm.sh
#
#  Antes (mod_php + prefork): cada proceso de Apache lleva PHP
#  dentro, también los que solo sirven una foto o un CSS.
#  Después (PHP-FPM + event): Apache atiende muchas conexiones
#  con pocos hilos y solo pasa a PHP-FPM las peticiones .php.
#  Además permite HTTP/2 (varias descargas por una sola conexión).
#
#  Si Apache no acepta la configuración o la web deja de
#  responder, el script vuelve solo a como estaba.
# =========================================================
set -uo pipefail
if [[ $EUID -ne 0 ]]; then echo "Ejecútalo con sudo: sudo bash $0"; exit 1; fi

paso() { printf '\n\033[1;34m== %s\033[0m\n' "$1"; }
ok()   { printf '   \033[32m✔\033[0m %s\n' "$1"; }
PHPV=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
RAIZ=$(cd "$(dirname "$0")/../.." && pwd)

volver_atras() {
    printf '\n\033[1;31mAlgo ha fallado: se vuelve a la configuración de antes\033[0m\n'
    a2disconf -q "php$PHPV-fpm" sabor-http2 >/dev/null 2>&1
    a2dismod -q mpm_event http2 >/dev/null 2>&1
    a2enmod -q mpm_prefork "php$PHPV" >/dev/null 2>&1
    systemctl restart apache2
    exit 1
}

paso "1/5 Instalar PHP-FPM $PHPV"
DEBIAN_FRONTEND=noninteractive apt-get -y -qq install "php$PHPV-fpm" || exit 1
# Los ajustes que estuvieran solo en el php.ini de Apache se avisan para copiarlos
cambios=$(diff <(grep -Ev '^\s*(;|$)' /etc/php/$PHPV/apache2/php.ini) <(grep -Ev '^\s*(;|$)' /etc/php/$PHPV/fpm/php.ini) | grep '^<' || true)
[[ -n $cambios ]] && printf '   Ajustes del php.ini de Apache que no están en el de PHP-FPM:\n%s\n' "$cambios"
for m in pdo_mysql mbstring redis; do
    php-fpm$PHPV -m 2>/dev/null | grep -qi "^$m$" && ok "Extensión $m disponible" || echo "   Falta la extensión $m en PHP-FPM"
done
# Más procesos de PHP que los 5 que trae Ubuntu (ver php-fpm-sabor.conf)
install -m 644 "$RAIZ/scripts/servidor/php-fpm-sabor.conf" "/etc/php/$PHPV/fpm/pool.d/zz-sabor.conf"
php-fpm$PHPV -t >/dev/null 2>&1 || { echo "   php-fpm-sabor.conf no es válido"; rm -f "/etc/php/$PHPV/fpm/pool.d/zz-sabor.conf"; }
systemctl enable --now "php$PHPV-fpm" >/dev/null
systemctl restart "php$PHPV-fpm"
ok "PHP-FPM en marcha"

paso "2/5 Cambiar Apache de prefork a event"
a2dismod -q "php$PHPV" mpm_prefork >/dev/null
a2enmod -q mpm_event proxy_fcgi setenvif http2 >/dev/null
a2enconf -q "php$PHPV-fpm" >/dev/null
ok "Módulos cambiados"

paso "3/5 Activar HTTP/2"
cat > /etc/apache2/conf-available/sabor-http2.conf <<'CONF'
# HTTP/2: el navegador descarga CSS, JS y fotos a la vez por una sola conexión
Protocols h2 http/1.1
CONF
a2enconf -q sabor-http2 >/dev/null
# Apache (desde el arreglo del CVE-2024-24795) no se fía del Content-Length que manda
# PHP-FPM y responde "a trozos". Nuestra API lo calcula con strlen(), así que es fiable.
cat > /etc/apache2/conf-available/sabor-php-fpm.conf <<'CONF'
# Confiar en el Content-Length de PHP-FPM: lo calcula nuestra propia API (api/db.php)
SetEnv ap_trust_cgilike_cl 1
CONF
a2enconf -q sabor-php-fpm >/dev/null
ok "HTTP/2 activado (solo por HTTPS, como hacen los navegadores)"

paso "4/5 Comprobar y reiniciar Apache"
apache2ctl configtest || volver_atras
systemctl restart apache2 || volver_atras
sleep 1
codigo=$(curl -sk -o /dev/null -w '%{http_code}' --max-time 10 https://localhost/api/salud.php)
[[ $codigo == 200 ]] || { echo "   La web responde $codigo"; volver_atras; }
ok "La web responde (HTTP 200)"
protocolo=$(curl -sk -o /dev/null -w '%{http_version}' --http2 https://localhost/)
ok "Protocolo usado por un navegador: HTTP/$protocolo"

paso "5/5 La monitorización también vigila PHP-FPM"
if [[ -f /usr/local/sbin/sabor-monitor ]]; then
    install -m 700 "$RAIZ/scripts/monitor.sh" /usr/local/sbin/sabor-monitor
    /usr/local/sbin/sabor-monitor >/dev/null 2>&1 || true
    ok "Monitor actualizado"
fi

printf '\n\033[1;32mHecho.\033[0m Repite la prueba de rendimiento (ab) para comparar.\n'
