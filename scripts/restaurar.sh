#!/usr/bin/env bash
# =========================================================
#  Restaurar una copia de seguridad de la base de datos
#
#  Uso:  sudo sabor-restaurar /var/backups/sabor-mediterraneo/bd_2026-11-05_1400.sql.gz
#        sudo sabor-restaurar            (sin nada: lista las copias disponibles)
#
#  Antes de restaurar hace una copia del estado actual, por si
#  acaso hay que deshacer la restauración.
# =========================================================
set -euo pipefail

BD="${BD:-sabor_mediterraneo}"
DESTINO="${DESTINO:-/var/backups/sabor-mediterraneo}"

if [[ $# -eq 0 ]]; then
    echo "Copias disponibles (la más reciente al final):"
    ls -lh "$DESTINO"/bd_*.sql.gz 2>/dev/null | awk '{print "  " $5 "\t" $9}' || echo "  (ninguna)"
    echo
    echo "Uso: sudo sabor-restaurar ARCHIVO.sql.gz"
    exit 0
fi

COPIA="$1"
[[ -f "$COPIA" ]] || { echo "No existe $COPIA"; exit 1; }
gzip -t "$COPIA"  || { echo "El archivo está dañado"; exit 1; }

echo "Se va a sustituir la base de datos $BD por:"
echo "  $COPIA"
read -r -p "Escribe SI en mayúsculas para continuar: " RESPUESTA
[[ "$RESPUESTA" == "SI" ]] || { echo "Cancelado."; exit 1; }

umask 077
SEGURIDAD="$DESTINO/antes_de_restaurar_$(date +%F_%H%M).sql.gz"
mysqldump --single-transaction --routines --triggers --events "$BD" | gzip -9 > "$SEGURIDAD"
echo "Estado actual guardado en $SEGURIDAD"

gunzip -c "$COPIA" | mysql "$BD"
logger -t sabor-backup "Restaurada la copia $COPIA"
echo "Restaurada. Comprueba la web y el panel."
