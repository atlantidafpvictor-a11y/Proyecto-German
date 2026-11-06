#!/usr/bin/env bash
# =========================================================
#  Prueba de restauración REAL desde Google Drive
#  Uso:  sudo sabor-probar-copia
#
#  1. Descarga de Drive la última copia cifrada de la base de datos.
#  2. La descifra con la clave privada.
#  3. La carga en una base de datos de PRUEBA (sabor_prueba_restauracion):
#     la de verdad no se toca.
#  4. Compara cuántas filas tienen las tablas en las dos y borra la de prueba.
#  Una copia que nunca se ha probado a restaurar no es una copia.
# =========================================================
set -euo pipefail

REMOTO="${REMOTO:-gdrive:sabor-mediterraneo-copias}"
CLAVE="${CLAVE:-/root/.config/sabor/copias.key}"
BD=sabor_mediterraneo
PRUEBA=sabor_prueba_restauracion

[[ -f $CLAVE ]] || { echo "Falta la clave privada $CLAVE"; exit 1; }
TEMPORAL=$(mktemp -d)
trap 'rm -rf "$TEMPORAL"; mysql -e "DROP DATABASE IF EXISTS $PRUEBA" 2>/dev/null || true' EXIT

echo "1/4 Buscando la última copia en Google Drive..."
ultima=$(rclone lsf "$REMOTO" --include 'bd_*.sql.gz.age' | sort | tail -1)
[[ -n $ultima ]] || { echo "No hay copias en $REMOTO"; exit 1; }
rclone copy "$REMOTO/$ultima" "$TEMPORAL" --quiet
echo "    $ultima ($(du -h "$TEMPORAL/$ultima" | cut -f1))"

echo "2/4 Descifrando..."
age -d -i "$CLAVE" -o "$TEMPORAL/copia.sql.gz" "$TEMPORAL/$ultima"
gzip -t "$TEMPORAL/copia.sql.gz" && echo "    Archivo íntegro"

echo "3/4 Restaurando en la base de datos de prueba $PRUEBA..."
mysql -e "DROP DATABASE IF EXISTS $PRUEBA; CREATE DATABASE $PRUEBA CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci"
zcat "$TEMPORAL/copia.sql.gz" | mysql "$PRUEBA"

echo "4/4 Comparando con la base de datos real:"
printf '    %-24s %10s %10s\n' "Tabla" "Real" "Copia"
for t in reservas clientes platos mesas eventos opiniones usuarios; do
    real=$(mysql -N -e "SELECT COUNT(*) FROM $BD.$t" 2>/dev/null || echo '-')
    copia=$(mysql -N -e "SELECT COUNT(*) FROM $PRUEBA.$t" 2>/dev/null || echo '-')
    printf '    %-24s %10s %10s\n' "$t" "$real" "$copia"
done
rutinas=$(mysql -N -e "SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA='$PRUEBA'")
disparadores=$(mysql -N -e "SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA='$PRUEBA'")
echo "    Procedimientos/funciones: $rutinas · Triggers: $disparadores"
echo "Restauración correcta. (Si la copia es de hace horas, puede haber alguna reserva nueva en la real.)"
