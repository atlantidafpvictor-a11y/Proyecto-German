# Montar el servidor en Ubuntu Server 24.04

Guía para publicar Sabor Mediterráneo en un servidor Linux con Apache, MariaDB y PHP (pila **LAMP**).

## Nuestro servidor

| Dato | Valor |
|---|---|
| Equipo | Ordenador de torre físico con Ubuntu Server 24.04 LTS |
| Nombre | `proyectoserver` |
| Tarjeta de red | `eno1` (cable) |
| IP fija | `192.168.4.121/23` |
| Puerta de enlace | `192.168.4.1` |
| DNS | `10.150.1.1`, `8.8.8.8`, `8.8.4.4` |
| Web | `http://192.168.4.121/` |
| Panel | `http://192.168.4.121/admin/` |
| Servicios | Apache 2.4, MariaDB, PHP 8.3, OpenSSH, UFW |

Configuración de red usada (`/etc/netplan/50-cloud-init.yaml`):

```yaml
network:
  version: 2
  ethernets:
    eno1:
      dhcp4: false
      addresses:
        - 192.168.4.121/23
      routes:
        - to: default
          via: 192.168.4.1
      nameservers:
        addresses: [10.150.1.1, 8.8.8.8, 8.8.4.4]
```

Pruebas superadas: la API devuelve la carta desde MariaDB; `database/restaurante.sql`, `api/config.local.php` y `.git/config` responden **403**; el cortafuegos está activo con OpenSSH y Apache Full; las reservas hechas desde otro PC se guardan y reciben mesa automáticamente.

> Nota de red: el PC de trabajo estaba en otras redes (`192.168.50.x` por cable y `192.168.22.x` por Wi-Fi) y no veía el servidor. Se solucionó conectando el PC por cable a la misma red que la torre (`192.168.4.0/23`).

---

Repositorio: `https://github.com/atlantidafpvictor-a11y/Proyecto-German.git`

---

## 0. Antes de empezar (en vuestro PC)

**Renombrad `Index.html` a `index.html`.** Linux distingue mayúsculas y minúsculas y Apache solo busca `index.html`. Desde Git Bash, en la carpeta del proyecto:

```bash
git mv Index.html tmp.html
git mv tmp.html index.html
git commit -m "Renombrar index.html a minúsculas"
git push
```

Comprobad también que el código más reciente (con `api/` y `admin/`) está en la rama que vais a clonar. En esta guía se usa `main`; si está en `Francisco`, haced el merge a `main` antes o clonad esa rama.

**El servidor es un ordenador de torre físico.** Conectadlo a la red de clase **por cable**: es más estable que el Wi-Fi y en Ubuntu Server no hay que configurar nada extra para usarlo.

---

## 1. Preparar el sistema

```bash
sudo apt update && sudo apt upgrade -y
sudo timedatectl set-timezone Europe/Madrid
ip a
```

- `apt update / upgrade`: actualiza la lista de paquetes y el sistema.
- `timedatectl`: pone la hora de España. Importante, porque las reservas dependen de qué día es "hoy".
- `ip a`: muestra la IP del servidor (algo como `192.168.1.50`) y el nombre de la tarjeta de red (algo como `enp3s0` o `eno1`). Apuntad los dos.

### 1b. Acceso por SSH

Para no tener que trabajar delante de la torre, instalad el servidor SSH (si no lo marcasteis al instalar Ubuntu):

```bash
sudo apt install -y openssh-server
sudo systemctl enable --now ssh
```

Desde vuestro PC con Windows, en PowerShell o Git Bash: `ssh vuestro_usuario@IP-DEL-SERVIDOR`.

### 1c. IP fija

Por defecto el router o el servidor DHCP del instituto puede darle otra IP cada vez que se encienda, y la web dejaría de estar en la misma dirección. **Preguntad primero al profesor** qué IP podéis usar, para no chocar con otro equipo de la red; también puede reservarla en el DHCP.

Averiguad la puerta de enlace (el router):

```bash
ip route | grep default
```

Desactivad la configuración de red automática de cloud-init, para que no deshaga el cambio al reiniciar, y haced una copia del archivo actual:

```bash
echo "network: {config: disabled}" | sudo tee /etc/cloud/cloud.cfg.d/99-disable-network-config.cfg
sudo cp /etc/netplan/50-cloud-init.yaml /root/50-cloud-init.yaml.copia
```

Mirad el archivo de red que ya existe (el nombre puede variar):

```bash
ls /etc/netplan/
sudo nano /etc/netplan/50-cloud-init.yaml
```

Sustituid su contenido por esto, cambiando la tarjeta, la IP y la puerta de enlace por las vuestras (en YAML los espacios importan: usad espacios, nunca tabuladores):

```yaml
network:
  version: 2
  ethernets:
    enp3s0:
      dhcp4: false
      addresses:
        - 192.168.1.50/24
      routes:
        - to: default
          via: 192.168.1.1
      nameservers:
        addresses: [8.8.8.8, 1.1.1.1]
```

```bash
sudo chmod 600 /etc/netplan/*.yaml
sudo netplan try
```

`netplan try` aplica el cambio y lo deshace solo a los 120 segundos si no pulsáis Enter, así que si os quedáis sin red no pasa nada. Si todo funciona (comprobadlo con `ping -c 3 google.com`), pulsad **Enter** para confirmarlo.

> Si lo estáis haciendo por SSH y cambiáis la IP, la conexión se cortará: volved a entrar con la IP nueva. Lo más cómodo es hacer este paso delante de la torre.

## 2. Instalar Apache, MariaDB, PHP y Git

```bash
sudo apt install -y apache2 mariadb-server php libapache2-mod-php php-mysql php-mbstring git
```

| Paquete | Para qué |
|---|---|
| `apache2` | Servidor web |
| `mariadb-server` | Base de datos (compatible con MySQL, la misma que trae XAMPP) |
| `php`, `libapache2-mod-php` | PHP y su conexión con Apache |
| `php-mysql` | Extensión PDO para conectar PHP con MariaDB |
| `php-mbstring` | Funciones de texto con tildes y eñes (`mb_strlen`) |
| `git` | Descargar y actualizar la web desde GitHub |

Comprobación:

```bash
php -v
systemctl status apache2 mariadb --no-pager
```

Ambos servicios deben salir `active (running)`. Si abrís `http://IP-DEL-SERVIDOR` desde otro ordenador, debe aparecer la página de bienvenida de Apache.

## 3. Asegurar MariaDB

```bash
sudo mysql_secure_installation
```

Respuestas recomendadas:

| Pregunta | Respuesta |
|---|---|
| Enter current password for root | Pulsar **Enter** (está vacía) |
| Switch to unix_socket authentication | **n** (ya lo usa) |
| Change the root password? | **n** (root solo entra con `sudo`, que es más seguro) |
| Remove anonymous users? | **Y** |
| Disallow root login remotely? | **Y** |
| Remove test database? | **Y** |
| Reload privilege tables now? | **Y** |

## 4. Descargar la web

```bash
cd /var/www
sudo git clone https://github.com/atlantidafpvictor-a11y/Proyecto-German.git sabor-mediterraneo
ls /var/www/sabor-mediterraneo
```

Debe aparecer `index.html` en minúsculas, junto con `api`, `admin` y `database`.

> Si el repositorio es privado, Git pedirá usuario y contraseña. La contraseña no es la de GitHub, sino un **token personal**: GitHub → Settings → Developer settings → Personal access tokens.

## 5. Crear la base de datos

```bash
sudo mysql < /var/www/sabor-mediterraneo/database/restaurante.sql
```

Se importa como administrador (`sudo mysql`) porque el script crea una función, un procedimiento y un trigger, que necesitan privilegios especiales.

Ahora se crea un **usuario solo para la web**, con los permisos mínimos que necesita (principio de **mínimo privilegio**):

```bash
sudo mysql
```

```sql
CREATE USER 'sabor_web'@'localhost' IDENTIFIED BY 'CambiadEstaClave_2026!';
GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE ON sabor_mediterraneo.* TO 'sabor_web'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

- Solo puede entrar desde el propio servidor (`@'localhost'`).
- Solo puede tocar la base `sabor_mediterraneo`.
- Puede leer y modificar datos y ejecutar el procedimiento (`EXECUTE`), pero **no** puede borrar tablas ni crear otras bases de datos. Si alguien encontrara un fallo en la web, el daño estaría limitado.

Comprobación:

```bash
mysql -u sabor_web -p -e "SELECT COUNT(*) AS platos FROM sabor_mediterraneo.platos;"
```

Debe responder `17`.

## 6. Configurar la conexión de PHP

No se modifica `api/config.php`: se crea un archivo aparte, `config.local.php`, que Git ignora, para que la contraseña no acabe en GitHub.

```bash
sudo nano /var/www/sabor-mediterraneo/api/config.local.php
```

Contenido (con vuestra contraseña):

```php
<?php
return [
    'db_user' => 'sabor_web',
    'db_pass' => 'CambiadEstaClave_2026!',
];
```

Guardar con `Ctrl+O`, `Enter`, y salir con `Ctrl+X`. Después, permisos para que solo root y Apache (`www-data`) puedan leerlo:

```bash
sudo chown root:www-data /var/www/sabor-mediterraneo/api/config.local.php
sudo chmod 640 /var/www/sabor-mediterraneo/api/config.local.php
```

## 7. Configurar Apache (VirtualHost)

```bash
sudo nano /etc/apache2/sites-available/sabor-mediterraneo.conf
```

```apache
<VirtualHost *:80>
    ServerName sabor.local
    DocumentRoot /var/www/sabor-mediterraneo

    <Directory /var/www/sabor-mediterraneo>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # La carpeta .git contiene todo el historial del proyecto: nunca debe verse
    <DirectoryMatch "/\.git">
        Require all denied
    </DirectoryMatch>

    ErrorLog ${APACHE_LOG_DIR}/sabor_error.log
    CustomLog ${APACHE_LOG_DIR}/sabor_access.log combined
</VirtualHost>
```

- `DocumentRoot`: carpeta que se sirve.
- `Options -Indexes`: no enseña listados de carpetas (el "Index of /database" que salía en XAMPP).
- `AllowOverride All`: hace caso a vuestros `.htaccess`. Sin esto, `database/` y `api/config.php` quedarían accesibles.
- `DirectoryMatch "/\.git"`: bloquea la carpeta `.git`, que se crea al clonar.

Activar el sitio y desactivar la página por defecto:

```bash
sudo a2ensite sabor-mediterraneo
sudo a2dissite 000-default
sudo apache2ctl configtest
sudo systemctl reload apache2
```

`configtest` debe responder `Syntax OK`.

Opcional, para que Apache no diga su versión exacta en los errores:

```bash
sudo sed -i 's/^ServerTokens OS/ServerTokens Prod/; s/^ServerSignature On/ServerSignature Off/' /etc/apache2/conf-available/security.conf
sudo systemctl reload apache2
```

## 8. Cortafuegos

```bash
sudo ufw allow OpenSSH
sudo ufw allow "Apache Full"
sudo ufw enable
sudo ufw status
```

Solo quedan abiertos SSH (22), HTTP (80) y HTTPS (443). **Permitid OpenSSH antes de activar `ufw`**, o perderéis la conexión si trabajáis por SSH.

## 9. Probar

Desde otro ordenador de la red, con la IP del paso 1:

| Dirección | Qué debe pasar |
|---|---|
| `http://IP/` | Se ve la web |
| `http://IP/admin/` | Pide crear la cuenta de administración |
| `http://IP/api/carta.php` | Devuelve JSON con los platos |
| `http://IP/database/restaurante.sql` | **403 Forbidden** |
| `http://IP/api/config.php` | **403 Forbidden** |
| `http://IP/.git/config` | **403 Forbidden** |

Haced una reserva desde la web: al confirmar **no** debe decir "Modo demostración". Después debe aparecer en el panel, con mesa asignada.

Las tres respuestas 403 son la prueba de que la configuración de seguridad funciona.

## 10. Extra: HTTPS con certificado autofirmado

Para una red local sin dominio. El navegador avisará de que el certificado no es de confianza (es normal: lo habéis firmado vosotros), pero el tráfico va cifrado.

```bash
sudo a2enmod ssl
sudo openssl req -x509 -nodes -days 365 -newkey rsa:2048 -keyout /etc/ssl/private/sabor.key -out /etc/ssl/certs/sabor.crt -subj "/C=ES/ST=Granada/L=Granada/O=Sabor Mediterraneo/CN=192.168.4.121" -addext "subjectAltName=IP:192.168.4.121"
```

Cread un archivo nuevo, `/etc/apache2/sites-available/sabor-mediterraneo-ssl.conf`, con:

```apache
<VirtualHost *:443>
    ServerName sabor.local
    DocumentRoot /var/www/sabor-mediterraneo

    SSLEngine on
    SSLCertificateFile    /etc/ssl/certs/sabor.crt
    SSLCertificateKeyFile /etc/ssl/private/sabor.key

    <Directory /var/www/sabor-mediterraneo>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    <DirectoryMatch "/\.git">
        Require all denied
    </DirectoryMatch>

    ErrorLog ${APACHE_LOG_DIR}/sabor_error.log
    CustomLog ${APACHE_LOG_DIR}/sabor_access.log combined
</VirtualHost>
```

Y dentro del bloque `<VirtualHost *:80>`, para redirigir todo a HTTPS:

```apache
    Redirect permanent / https://192.168.4.121/
```

```bash
sudo a2ensite sabor-mediterraneo-ssl
sudo apache2ctl configtest
sudo systemctl reload apache2
```

Con HTTPS, la cookie de sesión del panel se marca automáticamente como `Secure`.

Si el servidor tuviera un dominio público, en lugar del autofirmado se usaría Let's Encrypt (`sudo apt install certbot python3-certbot-apache` y `sudo certbot --apache`).

---

## Actualizar la web después de un cambio

En vuestro PC: commit y push. En el servidor:

```bash
cd /var/www/sabor-mediterraneo
sudo git pull
```

`config.local.php` no se toca, porque Git lo ignora.

### Si la actualización trae una migración

Algunas actualizaciones añaden tablas nuevas. En vez de reimportar `restaurante.sql` (que borraría todo), se ejecuta solo el archivo de `database/migraciones/` correspondiente:

```bash
sudo mysql sabor_mediterraneo < /var/www/sabor-mediterraneo/database/migraciones/001_galeria.sql
sudo mysql sabor_mediterraneo < /var/www/sabor-mediterraneo/database/migraciones/002_fotos_propias.sql
sudo mysql sabor_mediterraneo < /var/www/sabor-mediterraneo/database/migraciones/003_fotos_webp.sql
sudo mysql sabor_mediterraneo < /var/www/sabor-mediterraneo/database/migraciones/004_contacto_reserva.sql
sudo mysql sabor_mediterraneo < /var/www/sabor-mediterraneo/database/migraciones/005_ingles.sql
sudo mysql sabor_mediterraneo < /var/www/sabor-mediterraneo/database/migraciones/006_maridajes_presupuestos.sql
sudo mysql sabor_mediterraneo < /var/www/sabor-mediterraneo/database/migraciones/007_pizarra.sql
```

Se ejecutan en orden y solo una vez cada una (aunque repetirlas no rompe nada).

### Carpeta para las fotos de la galería (una sola vez)

Apache (usuario `www-data`) tiene que poder guardar las fotos que se suben desde el panel:

```bash
sudo mkdir -p /var/www/sabor-mediterraneo/uploads/galeria
sudo chown -R www-data:www-data /var/www/sabor-mediterraneo/uploads
sudo chmod -R 755 /var/www/sabor-mediterraneo/uploads
```

Solo esa carpeta es de `www-data`: el resto del código sigue siendo de `root`, así que aunque alguien atacara la web no podría modificar los archivos PHP. Además, `uploads/.htaccess` impide que se ejecute ningún script dentro de `uploads/`, y `admin/.htaccess` sube el límite de subida a 8 MB (Ubuntu trae 2 MB).

> ⚠️ **No volváis a importar `restaurante.sql` en el servidor** salvo que queráis empezar de cero: el script empieza con `DROP DATABASE` y borra todas las reservas, mensajes y usuarios reales.

## 11. Puesta en producción

Lo que separa un servidor "que funciona" de uno preparado para un negocio real: copias automáticas, protección contra ataques y cabeceras de seguridad. Todo está en la carpeta `scripts/` del proyecto.

### 11a. Módulos de Apache para seguridad y rendimiento

El `.htaccess` de la raíz manda cabeceras de seguridad (Content-Security-Policy, HSTS…), comprime los archivos y le dice al navegador cuánto tiempo guardarlos. Para eso Apache necesita estos módulos:

```bash
sudo a2enmod headers expires deflate
sudo cp /var/www/sabor-mediterraneo/scripts/servidor/apache-seguridad.conf /etc/apache2/conf-available/sabor-seguridad.conf
sudo a2enconf sabor-seguridad
sudo apache2ctl configtest
sudo systemctl reload apache2
```

Comprobación (debe salir `content-security-policy`, `x-frame-options`, `strict-transport-security`… y `Server: Apache` sin versión):

```bash
curl -skI https://localhost | grep -iE "server|content-security|x-frame|strict-transport|x-content"
```

### 11b. Copias de seguridad automáticas

```bash
sudo install -m 700 /var/www/sabor-mediterraneo/scripts/backup.sh /usr/local/sbin/sabor-backup
sudo install -m 700 /var/www/sabor-mediterraneo/scripts/restaurar.sh /usr/local/sbin/sabor-restaurar
sudo install -m 644 /var/www/sabor-mediterraneo/scripts/servidor/sabor-backup.cron /etc/cron.d/sabor-backup
```

- Los scripts se **copian** a `/usr/local/sbin` (de root, permisos 700) en vez de ejecutarse desde `/var/www`: cron los lanza como root, así que nunca deben estar en una carpeta de la web.
- Cada copia guarda la base de datos completa (con procedimiento, función, trigger y vistas) y la carpeta `uploads/`, en `/var/backups/sabor-mediterraneo/`, con permisos solo para root.
- Se hace a las 14:00 y 10 minutos después de cada arranque (la torre no está encendida de noche). Se guardan 7 días.

Probarla ahora mismo y ver el resultado:

```bash
sudo sabor-backup
sudo ls -lh /var/backups/sabor-mediterraneo
journalctl -t sabor-backup --no-pager | tail
```

Restaurar (lista las copias; luego se le pasa la que se quiera y pide escribir `SI`):

```bash
sudo sabor-restaurar
sudo sabor-restaurar /var/backups/sabor-mediterraneo/bd_AAAA-MM-DD_HHMM.sql.gz
```

Antes de restaurar guarda automáticamente el estado actual, por si hay que deshacerlo.

> Una copia de seguridad en el mismo disco no protege si el disco se rompe. En un negocio real se copiaría además a otro equipo (`rsync`/`scp`) o a la nube. Para el proyecto podéis demostrarlo copiándola a vuestro PC: `scp usuario@192.168.4.121:/ruta/copia.sql.gz .` (antes `sudo cp` a vuestra carpeta personal y `sudo chown`).

### 11c. Bloquear ataques de fuerza bruta (fail2ban)

```bash
sudo apt install -y fail2ban
sudo cp /var/www/sabor-mediterraneo/scripts/servidor/fail2ban-sabor.local /etc/fail2ban/jail.d/sabor.local
sudo systemctl enable --now fail2ban
sudo systemctl restart fail2ban
sudo fail2ban-client status sshd
```

5 contraseñas de SSH mal en 10 minutos → esa IP queda bloqueada 1 hora (y el doble cada vez que reincide). El panel de la web tiene su propio bloqueo de intentos de login, y la API limita las peticiones por IP (error 429).

Si os bloqueáis a vosotros mismos probando: `sudo fail2ban-client set sshd unbanip 192.168.X.X`.

### 11d. Endurecer SSH

```bash
sudo cp /var/www/sabor-mediterraneo/scripts/servidor/ssh-sabor.conf /etc/ssh/sshd_config.d/99-sabor.conf
sudo sshd -t && sudo systemctl restart ssh
```

`sshd -t` comprueba el archivo antes de reiniciar: si da error, **no reiniciéis**. Y no cerréis la sesión SSH actual hasta comprobar desde otra ventana de PowerShell que podéis entrar.

Prohíbe entrar como `root`, limita los intentos y cierra sesiones abandonadas.

### 11e. Actualizaciones de seguridad automáticas

Ubuntu Server ya trae `unattended-upgrades`. Comprobar que está activo:

```bash
systemctl status unattended-upgrades --no-pager
cat /etc/apt/apt.conf.d/20auto-upgrades
```

Las dos líneas deben acabar en `"1"`. Si no: `sudo dpkg-reconfigure -plow unattended-upgrades` → Sí.

### 11f. La base de datos solo escucha en el propio servidor

```bash
sudo ss -tlnp | grep -E "3306|mysql|mariadb"
```

Debe salir `127.0.0.1:3306`, nunca `0.0.0.0:3306`: así nadie de la red puede conectarse a MariaDB directamente, solo PHP desde dentro del servidor. Además el cortafuegos (paso 8) solo deja pasar SSH, HTTP y HTTPS.

### 11g. Monitorización

Cada 5 minutos un script comprueba los servicios (Apache, MariaDB, SSH, fail2ban, cron, UFW), que la web responde, el disco, la memoria, la última copia, el certificado HTTPS, las IPs bloqueadas y las actualizaciones. El resultado se ve en el panel → **Estado del servidor**, y si algo falla sale un aviso en la pantalla «Hoy».

```bash
sudo install -m 700 /var/www/sabor-mediterraneo/scripts/monitor.sh /usr/local/sbin/sabor-monitor
sudo install -m 644 /var/www/sabor-mediterraneo/scripts/servidor/sabor-monitor.cron /etc/cron.d/sabor-monitor
sudo sabor-monitor
cat /var/lib/sabor-mediterraneo/estado.json
```

- El informe (`estado.json`) se puede leer, pero solo root lo escribe: la web lo lee sin tener ningún permiso de administración del sistema.
- Cuando algo pasa a estar mal (o se arregla) queda anotado una sola vez en el registro: `journalctl -t sabor-monitor`.
- `https://IP/api/salud.php` responde 200 si la web y la base de datos funcionan y 503 si no: sirve para cualquier servicio de vigilancia externo.

Prueba para la presentación: `sudo systemctl stop mariadb`, `sudo sabor-monitor`, recargar el panel (sale el aviso en rojo) y `sudo systemctl start mariadb`.

### 11h. Ver la base de datos del servidor con phpMyAdmin (túnel SSH)

En el servidor **no se instala phpMyAdmin**: sería una puerta de entrada más. Para verla con el phpMyAdmin de XAMPP del PC se usa un túnel SSH, que lleva la conexión cifrada dentro de SSH sin abrir el puerto 3306.

1. En el servidor, un usuario con contraseña solo para esta base de datos:

```bash
sudo mysql -e "CREATE USER 'gestor'@'127.0.0.1' IDENTIFIED BY 'UNA_CLAVE_LARGA'; GRANT ALL PRIVILEGES ON sabor_mediterraneo.* TO 'gestor'@'127.0.0.1'; FLUSH PRIVILEGES;"
```

2. En el PC, abrir el túnel y dejar la ventana abierta (el puerto 3307 del PC lleva al 3306 de la torre):

```powershell
ssh -N -L 3307:127.0.0.1:3306 usuario@192.168.4.121
```

3. Al final de `C:\xampp\phpMyAdmin\config.inc.php`:

```php
$i++;
$cfg['Servers'][$i]['verbose']   = 'Servidor del instituto (túnel SSH)';
$cfg['Servers'][$i]['host']      = '127.0.0.1';
$cfg['Servers'][$i]['port']      = '3307';
$cfg['Servers'][$i]['auth_type'] = 'cookie';
```

4. En phpMyAdmin, columna izquierda → **Servidor actual** → «Servidor del instituto (túnel SSH)», usuario `gestor`.

### 11i. Redis: caché, límite de peticiones y sesiones (opcional)

Redis es una base de datos en memoria. La web la usa, si está activada, para:

1. **Caché de la carta y del horario** (`api/carta.php`, `api/info.php`): la segunda petición no toca MariaDB. La cabecera `X-Cache` dice `HIT` (de la caché), `MISS` (de la base de datos) u `OFF` (sin Redis). Cualquier cambio en el panel vacía la caché al momento.
2. **Límite de peticiones y bloqueo del login**: ventana deslizante en un *sorted set*, en una operación atómica (`MULTI`), que caduca sola.
3. **Sesiones del panel**.

Si Redis se para, la web sigue funcionando igual (sin caché, con los contadores en archivos temporales).

```bash
sudo apt install -y redis-server php-redis
sudo systemctl restart apache2
CLAVE=$(openssl rand -hex 24); echo "CONTRASEÑA DE REDIS: $CLAVE"
sudo sed -i "s/^# *requirepass .*/requirepass $CLAVE/" /etc/redis/redis.conf
sudo systemctl restart redis-server
sudo ss -tlnp | grep 6379          # solo 127.0.0.1 y ::1
redis-cli ping                     # sin contraseña: NOAUTH
REDISCLI_AUTH=$CLAVE redis-cli ping   # con contraseña: PONG
```

Activarla en `api/config.local.php` (dentro del `return [ … ];`):

```php
'redis' => ['activo' => true, 'host' => '127.0.0.1', 'puerto' => 6379,
            'password' => 'LA_CONTRASEÑA', 'prefijo' => 'sabor:', 'cache_segundos' => 600],
```

Comprobar (la primera `MISS`, la segunda `HIT`):

```bash
curl -skI https://localhost/api/carta.php | grep -i x-cache
curl -skI https://localhost/api/carta.php | grep -i x-cache
```

Medir antes y después: `ab -n 500 -c 10 -k https://localhost/api/carta.php` (paquete `apache2-utils`).

### 11j. Auditoría de seguridad con Lynis

Lynis es la herramienta de auditoría que usan los administradores de sistemas: revisa cientos de puntos y da una **nota de endurecimiento** de 0 a 100.

```bash
sudo apt install -y lynis
sudo lynis audit system --quick > /tmp/lynis-antes.txt 2>&1
sudo grep hardening_index /var/log/lynis-report.dat
```

Nota inicial: **62**. Las recomendaciones que tienen sentido para este servidor están en `scripts/servidor/endurecer.sh` (comentado paso a paso) y se aplican con:

```bash
sudo bash scripts/servidor/endurecer.sh
```

Qué hace: actualiza paquetes vulnerables, endurece el núcleo (sysctl), bloquea protocolos de red sin uso, endurece SSH, pone un aviso legal, protege Redis, activa `mod_evasive` en Apache, instala `rkhunter`, `auditd`, `sysstat` y `acct`, y restringe permisos de cron, SSH y GRUB.

Qué **no** se aplica y por qué: `umask 027` (Apache dejaría de leer los archivos de `git pull`), bloquear USB (copias en pendrive), `AllowTcpForwarding no` (túnel de phpMyAdmin), contraseña de GRUB (riesgo de no poder arrancar), particiones separadas (solo al instalar) y ModSecurity (falsos positivos con los formularios sin un ajuste fino).

## Si algo falla

| Síntoma | Causa probable | Qué mirar |
|---|---|---|
| Sale un listado de carpeta o un 403 en `/` | `Index.html` en mayúscula | `ls /var/www/sabor-mediterraneo` |
| "No se puede conectar con la base de datos" | Usuario o contraseña mal en `config.local.php` | Probar `mysql -u sabor_web -p` |
| La web dice "Modo demostración" | PHP no se ejecuta o la API falla | Abrir `http://IP/api/carta.php` |
| Página en blanco o error 500 | Error de PHP | `sudo tail -n 30 /var/log/apache2/sabor_error.log` |
| `database/` se puede abrir | Falta `AllowOverride All` | Revisar el VirtualHost |
| No se entra desde otro PC | Cable, IP o cortafuegos | `ip a`, `ping` desde el otro PC, `sudo ufw status` |
| La web cambia de dirección al reiniciar | La IP la da el DHCP | Paso 1c: IP fija |
