# Montar el servidor en Ubuntu Server 24.04

Guía para publicar Sabor Mediterráneo en un servidor Linux con Apache, MariaDB y PHP (pila **LAMP**).

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
sudo openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout /etc/ssl/private/sabor.key \
  -out /etc/ssl/certs/sabor.crt \
  -subj "/C=ES/ST=Granada/L=Granada/O=Sabor Mediterraneo/CN=sabor.local"
```

Añadid al final de `/etc/apache2/sites-available/sabor-mediterraneo.conf`:

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
    Redirect permanent / https://IP-DEL-SERVIDOR/
```

```bash
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

> ⚠️ **No volváis a importar `restaurante.sql` en el servidor** salvo que queráis empezar de cero: el script empieza con `DROP DATABASE` y borra todas las reservas, mensajes y usuarios reales.

## Copia de seguridad de la base de datos

```bash
sudo mysqldump sabor_mediterraneo --routines --triggers > ~/copia_$(date +%F).sql
```

`--routines` y `--triggers` incluyen la función, el procedimiento y el trigger.

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
