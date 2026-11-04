# Sabor Mediterráneo · Proyecto intermodular 2º SMR

Web de un restaurante de cocina mediterránea en Granada: carta dinámica, reservas online con control de mesas, formulario de contacto y panel de administración, todo sobre PHP + MySQL.

## Estructura

```
Proyecto-German/
├── index.html              Web pública
├── style.css               Estilos de la web (responsive)
├── javascript.js           Carta, filtros, horario en vivo, reservas y contacto
├── i18n.js, i18n-en.js     Idiomas: funciones de traducción y diccionario inglés
├── carta.html/.css/.js     Carta para el móvil (se abre con el QR de cada mesa)
├── api/                    PHP que conecta la web con la base de datos (devuelve JSON)
│   ├── config.php          Datos de conexión (usuario, contraseña, nombre de la BD)
│   ├── db.php              Conexión PDO y funciones comunes
│   ├── carta.php           GET  · categorías, platos y alérgenos
│   ├── info.php            GET  · horario, servicios, opiniones y días cerrados
│   ├── disponibilidad.php  GET  · horas libres para una fecha y nº de personas
│   ├── reservas.php        POST · crea una reserva y le asigna mesa
│   ├── mi-reserva.php      POST · el cliente consulta o cancela su reserva con código y teléfono
│   ├── eventos.php         GET  · próximos eventos con plazas libres
│   ├── inscripcion.php     POST · apuntarse a un evento (control de plazas con FOR UPDATE)
│   ├── galeria.php         GET  · fotos visibles de la galería
│   ├── contacto.php        POST · guarda un mensaje del formulario
│   ├── presupuesto.php     POST · solicitud de presupuesto para una celebración
│   └── salud.php           GET  · comprobación de salud (200/503) para la monitorización
├── admin/                  Panel de administración
│   ├── login.php / logout.php / instalar.php
│   ├── index.php           Resumen del día y gráfico de ocupación
│   ├── reservas.php        Agenda, filtros, asignar mesa, reservas por teléfono
│   ├── carta.php, plato.php  Gestión de la carta y los alérgenos
│   ├── eventos.php, evento.php  Crear y publicar eventos, ver y quitar inscritos
│   ├── galeria.php         Subir, ordenar, ocultar y borrar fotos
│   ├── opiniones.php       Publicar o retirar reseñas
│   ├── presupuestos.php    Solicitudes de presupuesto de celebraciones
│   ├── qr.php, estado.php  Códigos QR de las mesas y estado del servidor
│   ├── mensajes.php        Bandeja del formulario de contacto
│   └── usuarios.php        Equipo (roles Administración y Sala)
├── 404.html                Página de error propia
├── .htaccess               Cabeceras de seguridad (CSP, HSTS…), compresión y caché
├── favicon.svg, manifest.webmanifest, robots.txt
├── fonts/                  Tipografías incluidas en WOFF2 (Source Serif 4 y Hanken Grotesk, licencia OFL)
├── img/                    Fotos del proyecto en WebP: portada, comedor, platos y galería
├── scripts/                Copias de seguridad automáticas y configuración del servidor (fail2ban, SSH, Apache)
├── uploads/                Fotos subidas desde el panel (no se suben a GitHub)
└── database/
    ├── restaurante.sql     Script completo de la base de datos
    ├── migraciones/        Cambios para bases de datos que ya existían
    └── README.md           Diagrama E-R y decisiones de diseño
```

## Puesta en marcha con XAMPP

1. Copia la carpeta del proyecto dentro de `C:\xampp\htdocs\`.
2. En el panel de XAMPP arranca **Apache** y **MySQL**.
3. Abre <http://localhost/phpmyadmin> → pestaña **Importar** → elige `database/restaurante.sql` → **Continuar**.
4. Abre la web: <http://localhost/Proyecto-German/>
5. Abre el panel: <http://localhost/Proyecto-German/admin/>. La primera vez te pedirá crear la cuenta de administración.

Si MySQL tiene contraseña o la base de datos tiene otro nombre, cámbialo en `api/config.php`.

> Si abres `index.html` con doble clic (sin XAMPP), la web funciona igual con datos de demostración, pero no guarda nada.

## Seguridad aplicada

- **Inyección SQL:** todas las consultas con datos del usuario usan consultas preparadas de PDO.
- **XSS:** todo lo que se pinta en el panel pasa por `htmlspecialchars` (función `e()`), y en la web por `escapeHTML`.
- **Contraseñas:** cifradas con `password_hash` (bcrypt), nunca en texto plano.
- **CSRF:** cada formulario del panel lleva un token secreto que se comprueba en el servidor.
- **Sesiones:** cookie `HttpOnly` y `SameSite=Strict`, id nuevo al entrar, cierre tras 30 min de inactividad.
- **Fuerza bruta:** 10 fallos de login en 15 minutos bloquean esa IP y esa cuenta (contados en el servidor, no en la cookie). El login tarda lo mismo exista o no el email, para no revelar quién es del equipo.
- **Accesos revocados al momento:** en cada página se comprueba en la base de datos que el usuario sigue activo y su rol.
- **Datos de los clientes:** desde la web nadie puede cambiar el nombre o el teléfono de otro cliente escribiendo su email; cada reserva guarda los datos con los que se hizo, y para consultarla o cancelarla hacen falta el código y ese teléfono.
- **Datos recibidos:** se valida el tipo de cada campo (un objeto en lugar de un texto se rechaza) y una misma persona no puede reservar dos mesas a menos de 3 horas.
- **Concurrencia:** las mesas se asignan de una en una con un cerrojo de MySQL (`GET_LOCK`), y las plazas de los eventos con `SELECT … FOR UPDATE`: dos personas a la vez nunca se quedan con la misma mesa ni sobrepasan el aforo.
- **Roles:** el personal de Sala solo ve las reservas; Administración gestiona todo.
- **Validación doble:** en el navegador (comodidad) y en el servidor (seguridad).
- **Anti-spam:** campo trampa oculto en los formularios públicos.
- **Content-Security-Policy:** el navegador solo ejecuta JavaScript de nuestro servidor; no hay ni una línea de JS dentro del HTML (ni en la web ni en el panel), así que un `<script>` colado en una opinión nunca se ejecutaría.
- **Cabeceras de seguridad:** HSTS, X-Frame-Options (anti-clickjacking), X-Content-Type-Options, Referrer-Policy y Permissions-Policy. Apache y PHP no dicen su versión.
- **Límite de peticiones (rate limiting):** la API pública limita reservas, inscripciones, consultas y mensajes por IP (error `429 Too Many Requests`), contra bots y contra quien intente adivinar códigos de reserva.
- **Servidor:** copias de seguridad automáticas con rotación de 7 días, fail2ban contra fuerza bruta en SSH, SSH sin acceso de root, MariaDB solo en `127.0.0.1` y cortafuegos UFW.
- **Subida de fotos:** máximo 6 MB, se comprueba que el contenido es una imagen real con `getimagesize()`, nombre aleatorio y extensión según el tipo real, y la carpeta `uploads/` no puede ejecutar código.

## Hoja de ruta

- [x] Paso 1 · Rediseño visual y carta dinámica
- [x] Paso 2 · Base de datos (18 tablas, vistas, función, procedimiento y trigger)
- [x] Paso 3 · API en PHP: carta, horarios, disponibilidad, reservas y contacto
- [x] Paso 4 · Panel de administración con login y roles
- [x] Servidor Ubuntu Server con HTTPS (ver `docs/servidor-ubuntu.md`)
- [x] Consultar y cancelar reserva con código y teléfono
- [x] Eventos con inscripción y plazas limitadas
- [x] Galería de fotos con subida segura desde el panel
- [x] Rediseño premium: portada con el arco que se abre al hacer scroll, tipografía Source Serif 4, carta en modo noche y pie con el nombre gigante
- [x] Fotos propias del restaurante (generadas con IA y optimizadas: de 83 MB a 7,6 MB) y subida de foto en «Editar plato»
- [x] Rendimiento: fotos en WebP (de 7,6 MB a 3,2 MB), fuentes WOFF2, compresión y caché del navegador con versionado de CSS/JS
- [x] SEO: datos estructurados schema.org (Restaurant), Open Graph para compartir en WhatsApp y redes, favicon, manifest y robots.txt
- [x] Producción: CSP, cabeceras de seguridad, rate limiting, página 404, copias automáticas, fail2ban y SSH endurecido
- [x] Cambiar reserva (día, hora, personas) y «Añadir al calendario» (.ics)
- [x] Carta para el móvil con QR por mesa (`carta.html`, panel → Códigos QR)
- [x] Monitorización: estado del servidor en el panel, avisos y `api/salud.php`
- [x] Ficha de cada plato con maridaje recomendado, celebraciones con formulario de presupuesto, zonas visuales en la reserva, contadores y apariciones al hacer scroll, barra «Reservar» en el móvil
- [x] Web en inglés: selector ES · EN (o idioma del navegador), textos en `i18n-en.js`, contenido traducido en la base de datos (`_en`) y editable desde el panel, mensajes de la API en el idioma del cliente
