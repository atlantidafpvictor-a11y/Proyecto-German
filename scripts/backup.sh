#!/usr/bin/env bash
# =========================================================
#  Copia de seguridad de Sabor Mediterráneo
#
#  Guarda la base de datos completa (tablas, datos, procedimiento,
#  función, trigger) y las fotos subidas desde el panel.
#  Conserva las copias de los últimos 7 días y borra las antiguas.
#
#  Se instala en /usr/local/sbin y lo ejecuta cron como root
#  (ver docs/servidor-ubuntu.md, apartado 11).
#  A mano:  sudo sabor-backup
# =========================================================
set -euo pipefail

BD="${BD:-sabor_mediterraneo}"
WEB="${WEB:-/var/www/sabor-mediterraneo}"
DESTINO="${DESTINO:-/var/backups/sabor-mediterraneo}"
DIAS=7
FECHA="$(date +%F_%H%M)"

umask 077                      # las copias solo las puede leer root
mkdir -p "$DESTINO"

log() { logger -t sabor-backup "$*"; echo "$*"; }

# 1. Base de datos. --single-transaction hace la copia "en una foto fija"
#    sin bloquear la web: se puede seguir reservando mientras se copia.
SQL="$DESTINO/bd_${FECHA}.sql.gz"
mysqldump --single-transaction --routines --triggers --events \
          --default-character-set=utf8mb4 "$BD" | gzip -9 > "$SQL"
gzip -t "$SQL"                 # comprueba que el archivo no está corrupto

# 2. Fotos subidas desde el panel y configuración (contraseña de la BD)
ARCH="$DESTINO/archivos_${FECHA}.tar.gz"
tar -czf "$ARCH" -C "$WEB" uploads api/config.local.php 2>/dev/null \
  || tar -czf "$ARCH" -C "$WEB" uploads

# 3. Rotación: borrar las copias de hace más de $DIAS días
find "$DESTINO" -type f \( -name 'bd_*.sql.gz' -o -name 'archivos_*.tar.gz' \) -mtime +"$DIAS" -delete

log "Copia correcta: $(basename "$SQL") ($(du -h "$SQL" | cut -f1)), $(basename "$ARCH") ($(du -h "$ARCH" | cut -f1)). Copias guardadas: $(ls "$DESTINO"/bd_*.sql.gz | wc -l)"

# 4. Copia fuera de la torre (Google Drive, cifrada), si está configurada
if [[ -x /usr/local/sbin/sabor-copia-nube ]]; then
    /usr/local/sbin/sabor-copia-nube || log "AVISO: la copia local está bien, pero no se pudo subir a Google Drive"
fi
