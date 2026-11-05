#!/usr/bin/env bash
# =========================================================
#  Endurecimiento del servidor según la auditoría de Lynis
#  Uso:  sudo bash scripts/servidor/endurecer.sh
#
#  Se puede ejecutar varias veces: cada paso comprueba si ya
#  está hecho. Antes de tocar SSH valida la configuración y,
#  si fuera incorrecta, la deshace (así no nos quedamos fuera).
#
#  Lo que NO se aplica, y por qué (va también en la memoria):
#   - umask 027: los archivos de la web que crea "git pull" dejarían
#     de poder leerse por Apache (www-data) y la web daría error 403.
#   - Desactivar USB: queremos poder sacar copias a un pendrive.
#   - AllowTcpForwarding no: rompería el túnel SSH de phpMyAdmin.
#   - Contraseña en GRUB: si se olvida, el servidor no arranca.
#   - Particiones separadas para /home, /tmp y /var: solo al instalar.
#   - ModSecurity: con las reglas OWASP bloquea peticiones normales del
#     formulario de reservas (falsos positivos); necesita ajuste fino.
# =========================================================
set -euo pipefail

if [[ $EUID -ne 0 ]]; then echo "Ejecútalo con sudo: sudo bash $0"; exit 1; fi

paso() { printf '\n\033[1;34m== %s\033[0m\n' "$1"; }
ok()   { printf '   \033[32m✔\033[0m %s\n' "$1"; }
export DEBIAN_FRONTEND=noninteractive

# ---------------------------------------------------------
paso "1/11 Actualizar paquetes con fallos de seguridad (PKGS-7392)"
apt-get update -qq
apt-get -y -qq -o Dpkg::Options::="--force-confold" upgrade
ok "Sistema al día"

# ---------------------------------------------------------
paso "2/11 Herramientas de auditoría y control de paquetes"
# libpam-tmpdir: cada usuario tiene su propia carpeta temporal (DEB-0280)
# apt-show-versions y debsums: versiones y comprobación de archivos de paquetes (PKGS-7394, PKGS-7370)
# libpam-pwquality: obliga a contraseñas fuertes al cambiarlas (AUTH-9262)
# sysstat, acct y auditd: registro de uso del sistema y de quién hace qué (ACCT-9622/9626/9628)
# rkhunter: busca rootkits y programas maliciosos (HRDN-7230)
apt-get -y -qq --no-install-recommends install \
    libpam-tmpdir apt-show-versions debsums libpam-pwquality sysstat acct auditd rkhunter
sed -i 's/^ENABLED="false"/ENABLED="true"/' /etc/default/sysstat
systemctl enable --now sysstat acct auditd >/dev/null 2>&1 || true
# Huella de los programas del sistema para que rkhunter detecte cambios
rkhunter --propupd --quiet || true
ok "Instaladas y en marcha"

# ---------------------------------------------------------
paso "3/11 Parámetros del núcleo (KRNL-6000)"
cat > /etc/sysctl.d/99-sabor.conf <<'EOF'
# Sabor Mediterráneo · endurecimiento del núcleo (lo pide Lynis, KRNL-6000)
# No mostrar direcciones de memoria del núcleo ni sus mensajes a usuarios normales
kernel.kptr_restrict = 2
kernel.dmesg_restrict = 1
# Sin la combinación de teclas "SysRq" (reinicios desde el teclado)
kernel.sysrq = 0
# Solo un proceso padre puede inspeccionar a sus hijos
kernel.yama.ptrace_scope = 1
kernel.unprivileged_bpf_disabled = 1
net.core.bpf_jit_harden = 2
dev.tty.ldisc_autoload = 0
# Sin volcados de memoria de programas con permisos especiales (KRNL-5820)
fs.suid_dumpable = 0
fs.protected_fifos = 2
fs.protected_regular = 2
# Red: no aceptar redirecciones ni rutas de origen, anotar paquetes imposibles
net.ipv4.conf.all.accept_redirects = 0
net.ipv4.conf.default.accept_redirects = 0
net.ipv4.conf.all.send_redirects = 0
net.ipv4.conf.default.send_redirects = 0
net.ipv4.conf.all.accept_source_route = 0
net.ipv4.conf.default.accept_source_route = 0
net.ipv4.conf.all.log_martians = 1
net.ipv4.conf.default.log_martians = 1
net.ipv4.conf.all.rp_filter = 1
net.ipv4.tcp_syncookies = 1
net.ipv6.conf.all.accept_redirects = 0
net.ipv6.conf.default.accept_redirects = 0
EOF
sysctl --system >/dev/null
# Sin volcados de memoria (core dumps) para nadie (KRNL-5820)
echo '* hard core 0' > /etc/security/limits.d/99-sabor.conf
ok "Aplicados sin reiniciar"

# ---------------------------------------------------------
paso "4/11 Protocolos de red que no usamos (NETW-3200)"
cat > /etc/modprobe.d/sabor-protocolos.conf <<'EOF'
# Protocolos de red que este servidor no necesita: no se pueden cargar
install dccp /bin/true
install sctp /bin/true
install rds /bin/true
install tipc /bin/true
EOF
ok "dccp, sctp, rds y tipc bloqueados"

# ---------------------------------------------------------
paso "5/11 Contraseñas de las cuentas del sistema (AUTH-9230, AUTH-9286)"
sed -i -E 's/^PASS_MAX_DAYS\s+[0-9]+/PASS_MAX_DAYS\t365/; s/^PASS_MIN_DAYS\s+[0-9]+/PASS_MIN_DAYS\t1/' /etc/login.defs
grep -q '^SHA_CRYPT_MIN_ROUNDS' /etc/login.defs || printf 'SHA_CRYPT_MIN_ROUNDS\t5000\nSHA_CRYPT_MAX_ROUNDS\t50000\n' >> /etc/login.defs
ok "Caducidad de 365 días para cuentas nuevas y más rondas de cifrado"

# ---------------------------------------------------------
paso "6/11 SSH más estricto (SSH-7408)"
SSH_CONF=/etc/ssh/sshd_config.d/99-sabor.conf
[[ -f $SSH_CONF ]] && cp "$SSH_CONF" "$SSH_CONF.antes"
cat > "$SSH_CONF" <<'EOF'
# Endurecimiento de SSH (en este archivo los comentarios van siempre en su propia línea)

# Nadie entra como root: se entra con vuestro usuario y se usa sudo
PermitRootLogin no
# 3 intentos de contraseña por conexión y 30 segundos para escribirla
MaxAuthTries 3
LoginGraceTime 30
# Como mucho 2 sesiones por conexión
MaxSessions 2
# No hace falta entorno gráfico ni reenviar el agente de claves
X11Forwarding no
AllowAgentForwarding no
# Cerrar sesiones abandonadas tras 10 minutos sin respuesta
ClientAliveInterval 300
ClientAliveCountMax 2
TCPKeepAlive no
# Sin compresión y con registro detallado (quién entra y con qué)
Compression no
LogLevel VERBOSE
# El reenvío de puertos se deja activado: lo usa el túnel de phpMyAdmin
# Aviso legal antes de pedir la contraseña
Banner /etc/issue.net
EOF
if sshd -t; then
    systemctl reload ssh
    ok "Configuración válida y aplicada (la sesión actual sigue abierta)"
else
    echo "   La configuración de SSH no es válida: se deja la de antes"
    [[ -f $SSH_CONF.antes ]] && mv "$SSH_CONF.antes" "$SSH_CONF" || rm -f "$SSH_CONF"
fi

# ---------------------------------------------------------
paso "7/11 Aviso legal al conectarse (BANN-7126, BANN-7130)"
AVISO='Sistema privado de Sabor Mediterráneo. Acceso solo para personal autorizado.
Todas las conexiones quedan registradas.'
printf '%s\n' "$AVISO" > /etc/issue
printf '%s\n' "$AVISO" > /etc/issue.net
ok "Aviso puesto en /etc/issue y /etc/issue.net"

# ---------------------------------------------------------
paso "8/11 fail2ban: archivo propio para que las actualizaciones no lo pisen (DEB-0880)"
if [[ ! -f /etc/fail2ban/jail.local ]]; then
    printf '# Ajustes locales de fail2ban (los nuestros están en jail.d/).\n# Este archivo no lo cambian las actualizaciones.\n[DEFAULT]\n' > /etc/fail2ban/jail.local
    systemctl restart fail2ban
fi
ok "jail.local creado"

# ---------------------------------------------------------
paso "9/11 Redis: desactivar el comando CONFIG (DBS-1886)"
if [[ -f /etc/redis/redis.conf ]]; then
    if ! grep -q '^rename-command CONFIG' /etc/redis/redis.conf; then
        printf '\n# Nadie puede cambiar la configuración de Redis desde una conexión (Lynis DBS-1886)\nrename-command CONFIG ""\n' >> /etc/redis/redis.conf
        systemctl restart redis-server
    fi
    ok "CONFIG desactivado"
else
    ok "Redis no está instalado: nada que hacer"
fi

# ---------------------------------------------------------
paso "10/11 Apache: frenar ataques de muchas peticiones seguidas (HTTP-6640)"
apt-get -y -qq --no-install-recommends install libapache2-mod-evasive
mkdir -p /var/log/mod_evasive && chown www-data:www-data /var/log/mod_evasive
cat > /etc/apache2/mods-available/evasive.conf <<'EOF'
# mod_evasive: si una misma IP pide demasiado en 1 segundo, se le responde 403 durante 10 s.
# Los límites son altos para que la web normal (con muchas fotos) nunca se bloquee.
<IfModule mod_evasive20.c>
    DOSHashTableSize    3097
    # La misma página más de 20 veces en 1 segundo
    DOSPageCount        20
    DOSPageInterval     1
    # Más de 150 peticiones en total en 1 segundo
    DOSSiteCount        150
    DOSSiteInterval     1
    DOSBlockingPeriod   10
    DOSLogDir           /var/log/mod_evasive
    # El propio servidor (pruebas de rendimiento, monitorización) no se bloquea
    DOSWhitelist        127.0.0.1
</IfModule>
EOF
a2enmod -q evasive >/dev/null
if apache2ctl configtest 2>/dev/null; then
    systemctl reload apache2
    ok "mod_evasive activo"
else
    a2dismod -q evasive >/dev/null; systemctl reload apache2
    echo "   Apache no aceptó la configuración: mod_evasive se deja desactivado"
fi

# ---------------------------------------------------------
paso "11/11 Permisos de archivos del sistema (FILE-7524)"
chmod 600 /etc/crontab /etc/ssh/sshd_config
chmod 700 /etc/cron.d /etc/cron.daily /etc/cron.hourly /etc/cron.weekly /etc/cron.monthly
[[ -f /boot/grub/grub.cfg ]] && chmod 400 /boot/grub/grub.cfg
ok "Solo root puede leer la configuración de cron, SSH y el arranque"

printf '\n\033[1;32mHecho.\033[0m Ahora vuelve a pasar Lynis para ver la nota nueva.\n'
