#!/usr/bin/env bash
# =========================================================
#  Monitorización del servidor de Sabor Mediterráneo
#
#  Lo ejecuta cron cada 5 minutos como root. Comprueba los servicios,
#  el disco, la memoria, la última copia de seguridad, el certificado
#  HTTPS, fail2ban y las actualizaciones pendientes, y deja el resultado en
#  /var/lib/sabor-mediterraneo/estado.json (lo lee el panel: Estado del servidor).
#
#  Si algo pasa a estar mal, lo anota en el registro del sistema
#  (journalctl -t sabor-monitor) una sola vez, no cada 5 minutos.
#
#  Se instala en /usr/local/sbin/sabor-monitor (docs/servidor-ubuntu.md, 11g).
#  A mano:  sudo sabor-monitor && cat /var/lib/sabor-mediterraneo/estado.json
# =========================================================
set -uo pipefail

SALIDA_DIR="${SALIDA_DIR:-/var/lib/sabor-mediterraneo}"
COPIAS="${COPIAS:-/var/backups/sabor-mediterraneo}"
CERT="${CERT:-/etc/ssl/certs/sabor.crt}"
URL_SALUD="${URL_SALUD:-https://localhost/api/salud.php}"

mkdir -p "$SALIDA_DIR"
chmod 755 "$SALIDA_DIR"

CHECKS=()     # cada comprobación en JSON
PEOR="ok"     # ok < aviso < error
PROBLEMAS=()  # texto de lo que está mal, para el registro

# Escapa comillas y barras para meter un texto en JSON
json() { local s="${1//\\/\\\\}"; s="${s//\"/\\\"}"; printf '"%s"' "$s"; }

# anotar ID NOMBRE ESTADO(ok|aviso|error) DETALLE
anotar() {
    CHECKS+=("{\"id\":$(json "$1"),\"nombre\":$(json "$2"),\"estado\":$(json "$3"),\"detalle\":$(json "$4")}")
    if [[ "$3" == "error" ]]; then PEOR="error"; PROBLEMAS+=("$2: $4")
    elif [[ "$3" == "aviso" && "$PEOR" != "error" ]]; then PEOR="aviso"; PROBLEMAS+=("$2: $4"); fi
}

# ---------- 1. Servicios ----------
for s in apache2:Apache mariadb:MariaDB fail2ban:fail2ban cron:cron; do
    unidad="${s%%:*}"; nombre="${s#*:}"
    if systemctl is-active --quiet "$unidad"; then anotar "svc-$unidad" "$nombre" ok "En marcha"
    else anotar "svc-$unidad" "$nombre" error "Parado ($(systemctl is-active "$unidad" 2>/dev/null))"; fi
done
# Redis es opcional: solo se vigila si está instalado
if systemctl list-unit-files redis-server.service >/dev/null 2>&1 && systemctl list-unit-files redis-server.service | grep -q redis-server; then
    if systemctl is-active --quiet redis-server; then anotar svc-redis "Redis" ok "En marcha"
    else anotar svc-redis "Redis" error "Parado: la web sigue, pero sin caché"; fi
fi
# PHP-FPM, si Apache lo usa (scripts/servidor/php-fpm.sh)
fpm=$(systemctl list-unit-files 'php*-fpm.service' --no-legend 2>/dev/null | awk '{print $1}' | head -1)
if [[ -n "$fpm" ]] && systemctl is-enabled --quiet "$fpm"; then
    if systemctl is-active --quiet "$fpm"; then anotar svc-php-fpm "PHP-FPM" ok "En marcha"
    else anotar svc-php-fpm "PHP-FPM" error "Parado: la web no puede ejecutar PHP"; fi
fi
# En Ubuntu 24.04 SSH arranca "bajo demanda": basta con que escuche ssh.socket
if systemctl is-active --quiet ssh.service || systemctl is-active --quiet ssh.socket; then
    anotar svc-ssh "SSH" ok "Escuchando en el puerto 22"
else
    anotar svc-ssh "SSH" error "Ni ssh.service ni ssh.socket están activos"
fi

if ufw status 2>/dev/null | grep -q "Status: active"; then
    anotar ufw "Cortafuegos UFW" ok "Activo"
else
    anotar ufw "Cortafuegos UFW" error "Desactivado"
fi

# ---------- 2. La web responde ----------
codigo=$(curl -sk -o /dev/null -w '%{http_code}' --max-time 5 "$URL_SALUD" || echo 000)
if [[ "$codigo" == "200" ]]; then anotar web "Web y base de datos" ok "Responde (HTTP 200)"
else anotar web "Web y base de datos" error "No responde bien (HTTP $codigo)"; fi

# ---------- 3. Disco y memoria ----------
uso=$(df --output=pcent / | tail -1 | tr -dc '0-9')
libre=$(df -h --output=avail / | tail -1 | tr -d ' ')
if   (( uso >= 90 )); then anotar disco "Disco" error "${uso} % usado, quedan $libre"
elif (( uso >= 80 )); then anotar disco "Disco" aviso "${uso} % usado, quedan $libre"
else anotar disco "Disco" ok "${uso} % usado, quedan $libre"; fi

mem_total=$(awk '/MemTotal/ {print $2}' /proc/meminfo)
mem_disp=$(awk '/MemAvailable/ {print $2}' /proc/meminfo)
mem_pct=$(( 100 - mem_disp * 100 / mem_total ))
if (( mem_pct >= 92 )); then anotar memoria "Memoria" aviso "${mem_pct} % en uso"
else anotar memoria "Memoria" ok "${mem_pct} % en uso de $(( mem_total / 1024 )) MB"; fi

carga=$(cut -d' ' -f1 /proc/loadavg)
anotar carga "Carga del procesador" ok "$carga (media de 1 minuto, $(nproc) núcleos)"

# ---------- 4. Última copia de seguridad ----------
ultima=$(ls -1t "$COPIAS"/bd_*.sql.gz 2>/dev/null | head -1)
if [[ -z "$ultima" ]]; then
    anotar copia "Copia de seguridad" error "No hay ninguna copia"
else
    horas=$(( ( $(date +%s) - $(stat -c %Y "$ultima") ) / 3600 ))
    total=$(ls -1 "$COPIAS"/bd_*.sql.gz | wc -l)
    detalle="Hace ${horas} h ($(basename "$ultima"), $(du -h "$ultima" | cut -f1)); $total guardadas"
    # La torre no está encendida de noche ni el fin de semana: hasta 3 días es normal
    if   (( horas > 96 )); then anotar copia "Copia de seguridad" error "$detalle"
    elif (( horas > 72 )); then anotar copia "Copia de seguridad" aviso "$detalle"
    else anotar copia "Copia de seguridad" ok "$detalle"; fi
fi

# Copia en Google Drive (solo si está configurada: scripts/copia-nube.sh)
SELLO_NUBE=/var/lib/sabor-mediterraneo/ultima-copia-nube
if [[ -f /usr/local/sbin/sabor-copia-nube ]]; then
    if [[ ! -f $SELLO_NUBE ]]; then
        anotar nube "Copia en la nube" aviso "Todavía no se ha subido ninguna copia a Google Drive"
    else
        horas=$(( ( $(date +%s) - $(cat "$SELLO_NUBE") ) / 3600 ))
        detalle="Última subida cifrada a Google Drive hace ${horas} h"
        if   (( horas > 96 )); then anotar nube "Copia en la nube" error "$detalle"
        elif (( horas > 72 )); then anotar nube "Copia en la nube" aviso "$detalle"
        else anotar nube "Copia en la nube" ok "$detalle"; fi
    fi
fi

# ---------- 5. Certificado HTTPS ----------
if [[ -r "$CERT" ]]; then
    fin=$(openssl x509 -enddate -noout -in "$CERT" | cut -d= -f2)
    dias=$(( ( $(date -d "$fin" +%s) - $(date +%s) ) / 86400 ))
    if   (( dias < 7 ));  then anotar cert "Certificado HTTPS" error "Caduca en $dias días"
    elif (( dias < 30 )); then anotar cert "Certificado HTTPS" aviso "Caduca en $dias días"
    else anotar cert "Certificado HTTPS" ok "Válido $dias días más"; fi
else
    anotar cert "Certificado HTTPS" aviso "No se encuentra $CERT"
fi

# ---------- 6. fail2ban ----------
if command -v fail2ban-client >/dev/null && systemctl is-active --quiet fail2ban; then
    ahora=$(fail2ban-client status sshd 2>/dev/null | awk -F: '/Currently banned/ {gsub(/[ \t]/,"",$2); print $2}')
    total=$(fail2ban-client status sshd 2>/dev/null | awk -F: '/Total banned/ {gsub(/[ \t]/,"",$2); print $2}')
    anotar bloqueos "IPs bloqueadas (SSH)" ok "${ahora:-0} ahora, ${total:-0} desde el arranque"
fi

# ---------- 7. Actualizaciones de seguridad pendientes ----------
if [[ -x /usr/lib/update-notifier/apt-check ]]; then
    pend=$(/usr/lib/update-notifier/apt-check 2>&1)   # "normales;seguridad"
    seg="${pend#*;}"; normales="${pend%;*}"
    if [[ -f /var/run/reboot-required ]]; then
        anotar updates "Actualizaciones" aviso "Hay que reiniciar para terminar de aplicarlas"
    elif (( ${seg:-0} > 0 )); then
        anotar updates "Actualizaciones" aviso "$seg de seguridad pendientes (se instalan solas)"
    else
        anotar updates "Actualizaciones" ok "${normales:-0} normales pendientes, 0 de seguridad"
    fi
fi

# ---------- Guardar el informe (de golpe, para que el panel nunca lea uno a medias) ----------
arranque=$(uptime -s 2>/dev/null || echo "")
{
    printf '{"generado":%s,"equipo":%s,"arranque":%s,"estado":%s,"checks":[' \
        "$(json "$(date -Iseconds)")" "$(json "$(hostname)")" "$(json "$arranque")" "$(json "$PEOR")"
    (IFS=,; printf '%s' "${CHECKS[*]}")
    printf ']}\n'
} > "$SALIDA_DIR/estado.json.tmp"
chmod 644 "$SALIDA_DIR/estado.json.tmp"
mv "$SALIDA_DIR/estado.json.tmp" "$SALIDA_DIR/estado.json"

# ---------- Registro: solo cuando cambia el estado ----------
anterior=$(cat "$SALIDA_DIR/ultimo-estado" 2>/dev/null || echo ok)
if [[ "$PEOR" != "$anterior" ]]; then
    if [[ "$PEOR" == "ok" ]]; then
        logger -t sabor-monitor -p user.notice "Todo vuelve a estar bien"
    else
        logger -t sabor-monitor -p user.warning "Estado $PEOR: $(IFS='; '; echo "${PROBLEMAS[*]}")"
    fi
    echo "$PEOR" > "$SALIDA_DIR/ultimo-estado"
fi
exit 0
