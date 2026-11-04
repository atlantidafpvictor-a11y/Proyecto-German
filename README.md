# Sabor Mediterráneo · Proyecto intermodular 2º SMR

Web de un restaurante de cocina mediterránea en Granada: carta dinámica, reservas online con control de mesas, formulario de contacto y panel de administración, todo sobre PHP + MySQL.

## Estructura

```
Proyecto-German/
├── index.html              Web pública
├── style.css               Estilos de la web (responsive)
├── javascript.js           Carta, filtros, horario en vivo, reservas y contacto
├── api/                    PHP que conecta la web con la base de datos (devuelve JSON)
│   ├── config.php          Datos de conexión (usuario, contraseña, nombre de la BD)
│   ├── db.php              Conexión PDO y funciones comunes
│   ├── carta.php           GET  · categorías, platos y alérgenos
│   ├── info.php            GET  · horario, servicios, opiniones y días cerrados
│   ├── disponibilidad.php  GET  · horas libres para una fecha y nº de personas
│   ├── reservas.php        POST · crea una reserva y le asigna mesa
│   ├── mi-reserva.php      POST · el cliente consulta o cancela su reserva con código y teléfono
│   └── contacto.php        POST · guarda un mensaje del formulario
├── admin/                  Panel de administración
│   ├── login.php / logout.php / instalar.php
│   ├── index.php           Resumen del día y gráfico de ocupación
│   ├── reservas.php        Agenda, filtros, asignar mesa, reservas por teléfono
│   ├── carta.php, plato.php  Gestión de la carta y los alérgenos
│   ├── opiniones.php       Publicar o retirar reseñas
│   ├── mensajes.php        Bandeja del formulario de contacto
│   └── usuarios.php        Equipo (roles Administración y Sala)
└── database/
    ├── restaurante.sql     Script completo de la base de datos
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
- **Fuerza bruta:** bloqueo de 5 minutos tras 5 intentos fallidos de login.
- **Roles:** el personal de Sala solo ve las reservas; Administración gestiona todo.
- **Validación doble:** en el navegador (comodidad) y en el servidor (seguridad).
- **Anti-spam:** campo trampa oculto en los formularios públicos.

## Hoja de ruta

- [x] Paso 1 · Rediseño visual y carta dinámica
- [x] Paso 2 · Base de datos (16 tablas, vistas, función, procedimiento y trigger)
- [x] Paso 3 · API en PHP: carta, horarios, disponibilidad, reservas y contacto
- [x] Paso 4 · Panel de administración con login y roles
- [x] Servidor Ubuntu Server con HTTPS (ver `docs/servidor-ubuntu.md`)
- [x] Consultar y cancelar reserva con código y teléfono
- [ ] Paso 5 · Más extras: eventos con inscripción, galería de fotos
