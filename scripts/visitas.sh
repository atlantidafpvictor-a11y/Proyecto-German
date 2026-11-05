#!/usr/bin/env bash
# =========================================================
#  Informe de visitas de la web con GoAccess
#  Se instala en /usr/local/sbin/sabor-visitas y lo ejecuta cron cada 15 minutos.
#  El informe se ve en el panel → Visitas (solo Administración).
#
#  - Lee el registro de Apache de la web, también los antiguos comprimidos (.gz).
#  - IPs anonimizadas (RGPD): se guarda 192.168.4.0 en vez de 192.168.4.121.
#  - No cuenta robots de buscadores ni las pruebas hechas desde el propio servidor.
# =========================================================
set -euo pipefail

REGISTROS=(/var/log/apache2/sabor_access.log*)
SALIDA=/var/lib/sabor-mediterraneo/visitas.html
TEMPORAL=$(mktemp --suffix=.html)
trap 'rm -f "$TEMPORAL"' EXIT

mkdir -p "$(dirname "$SALIDA")"
zcat -f "${REGISTROS[@]}" | goaccess - \
    --log-format=COMBINED \
    --anonymize-ip \
    --ignore-crawlers \
    --exclude-ip=127.0.0.1 --exclude-ip=::1 \
    --html-report-title="Visitas · Sabor Mediterráneo" \
    --html-prefs='{"theme":"bright","perPage":10}' \
    --no-progress \
    -o "$TEMPORAL" 2>/dev/null

# Se sustituye de golpe: el panel nunca lee un informe a medio escribir
install -m 644 "$TEMPORAL" "$SALIDA"
