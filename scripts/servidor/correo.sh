#!/usr/bin/env bash
# =========================================================
#  Enviar los emails de la web por Gmail (Postfix como "relay")
#  Uso:  sudo bash scripts/servidor/correo.sh
#
#  La web entrega cada email a Postfix (el servidor de correo local);
#  Postfix lo manda a smtp.gmail.com cifrado (TLS, puerto 587) con
#  la cuenta del proyecto y una CONTRASEÑA DE APLICACIÓN de Google
#  (no la contraseña normal de la cuenta).
#
#  La contraseña se pide sin mostrarla y se guarda en
#  /etc/postfix/sasl_passwd con permisos solo para root.
# =========================================================
set -euo pipefail
if [[ $EUID -ne 0 ]]; then echo "Ejecútalo con sudo: sudo bash $0"; exit 1; fi

CUENTA_DEF=proyectointermodulargastrotech@gmail.com
read -rp "Cuenta de Gmail [$CUENTA_DEF]: " CUENTA
CUENTA=${CUENTA:-$CUENTA_DEF}
read -rsp "Contraseña de aplicación (16 letras; no se ve al escribir): " CLAVE; echo
CLAVE=${CLAVE// /}
[[ ${#CLAVE} -eq 16 ]] || { echo "La contraseña de aplicación tiene 16 letras (sin espacios). No se ha cambiado nada."; exit 1; }

echo "== 1/4 Módulo de autenticación para Postfix"
DEBIAN_FRONTEND=noninteractive apt-get -y -qq install postfix libsasl2-modules >/dev/null

echo "== 2/4 Postfix manda todo por smtp.gmail.com:587 con TLS"
postconf -e \
    "relayhost = [smtp.gmail.com]:587" \
    "smtp_sasl_auth_enable = yes" \
    "smtp_sasl_password_maps = hash:/etc/postfix/sasl_passwd" \
    "smtp_sasl_security_options = noanonymous" \
    "smtp_tls_security_level = encrypt" \
    "smtp_tls_CAfile = /etc/ssl/certs/ca-certificates.crt" \
    "inet_interfaces = loopback-only"
umask 077
printf '[smtp.gmail.com]:587 %s:%s\n' "$CUENTA" "$CLAVE" > /etc/postfix/sasl_passwd
postmap /etc/postfix/sasl_passwd
chmod 600 /etc/postfix/sasl_passwd /etc/postfix/sasl_passwd.db
unset CLAVE
systemctl restart postfix

echo "== 3/4 Email de prueba"
read -rp "¿A qué email mando la prueba? " PRUEBA
printf 'From: Sabor Mediterraneo <%s>\nTo: %s\nSubject: Prueba del servidor de Sabor Mediterraneo\n\nSi lees esto, el servidor ya puede enviar emails.\n' "$CUENTA" "$PRUEBA" \
    | /usr/sbin/sendmail -f "$CUENTA" "$PRUEBA"
sleep 6
if journalctl -t postfix/smtp --since "-2 min" --no-pager | grep -q "status=sent"; then
    echo "   ✔ Enviado. Mira la bandeja de entrada (y la de spam) de $PRUEBA"
else
    echo "   ✘ No consta como enviado. Últimas líneas del registro de Postfix:"
    journalctl -t postfix/smtp --since "-2 min" --no-pager | tail -5
    echo "   Si pone 'Username and Password not accepted', la contraseña de aplicación no es correcta."
    exit 1
fi

echo "== 4/4 Activar los emails en la web"
CFG="$(cd "$(dirname "$0")/../.." && pwd)/api/config.local.php"
if grep -q "correo_activo" "$CFG"; then
    sed -i "s/'correo_activo'\s*=>\s*false/'correo_activo' => true/" "$CFG"
else
    sed -i "0,/return \[/s//return [\n    'correo_activo' => true,/" "$CFG"
fi
php -r "exit((require '$CFG')['correo_activo'] === true ? 0 : 1);" && echo "   ✔ correo_activo = true en api/config.local.php"
echo "Hecho. Haz una reserva de prueba en la web: el email llegará de verdad y saldrá como «Enviado» en el panel."
