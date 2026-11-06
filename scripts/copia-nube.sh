#!/usr/bin/env bash
# =========================================================
#  Copia de seguridad fuera de la torre: Google Drive, cifrada
#
#  Coge la última copia local (base de datos + fotos), la cifra con
#  "age" y la sube a Google Drive con "rclone". La lanza sabor-backup
#  al terminar cada copia; a mano:  sudo sabor-copia-nube
#
#  - Cifrado: con la clave PÚBLICA (copias.pub). Quien entre en el Drive
#    solo ve archivos ilegibles. Para descifrar hace falta la clave
#    PRIVADA (copias.key), que está en el servidor y en otro sitio fuera
#    de la torre (si el disco muere, sin esa copia no se puede restaurar).
#  - Google Drive: rclone con permiso "drive.file", solo ve lo que sube él.
#  - En la nube se guardan 30 días.
# =========================================================
set -euo pipefail

COPIAS="${COPIAS:-/var/backups/sabor-mediterraneo}"
REMOTO="${REMOTO:-gdrive:sabor-mediterraneo-copias}"
CLAVE_PUB="${CLAVE_PUB:-/root/.config/sabor/copias.pub}"
SELLO=/var/lib/sabor-mediterraneo/ultima-copia-nube   # lo mira la monitorización
DIAS_NUBE=30

log() { logger -t sabor-copia-nube "$*"; echo "$*"; }

[[ -f $CLAVE_PUB ]] || { log "ERROR: falta la clave pública $CLAVE_PUB"; exit 1; }
bd=$(ls -1t "$COPIAS"/bd_*.sql.gz 2>/dev/null | head -1)
arch=$(ls -1t "$COPIAS"/archivos_*.tar.gz 2>/dev/null | head -1)
[[ -n $bd ]] || { log "ERROR: no hay ninguna copia local que subir"; exit 1; }

TEMPORAL=$(mktemp -d)
trap 'rm -rf "$TEMPORAL"' EXIT
umask 077

for f in "$bd" $arch; do
    age -R "$CLAVE_PUB" -o "$TEMPORAL/$(basename "$f").age" "$f"
done

rclone copy "$TEMPORAL" "$REMOTO" --quiet
# Borrar de la nube las copias de más de 30 días
rclone delete "$REMOTO" --min-age "${DIAS_NUBE}d" --quiet || true

mkdir -p "$(dirname "$SELLO")"
date +%s > "$SELLO"; chmod 644 "$SELLO"
log "Subida a la nube: $(ls "$TEMPORAL" | tr '\n' ' ')→ $REMOTO"
