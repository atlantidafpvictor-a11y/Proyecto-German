# Sabor Mediterráneo · Proyecto intermodular 2º SMR

Web de un restaurante de cocina mediterránea en Granada con carta dinámica, reservas online y base de datos MySQL.

## Estructura

```
Proyecto-German/
├── index.html            Página principal
├── style.css             Estilos (diseño responsive)
├── javascript.js         Carta, filtros, horario en vivo y reservas
├── database/
│   ├── restaurante.sql   Script de la base de datos (tablas + datos de ejemplo)
│   └── README.md         Diagrama E-R y explicación
└── api/                  (paso 3) Archivos PHP que conectan la web con la BD
```

## Cómo verla

- **Sin servidor:** abre `index.html` en el navegador. Funciona con datos de demostración.
- **Con XAMPP:** copia la carpeta a `C:\xampp\htdocs\`, importa `database/restaurante.sql` en phpMyAdmin y abre `http://localhost/Proyecto-German/`.

## Hoja de ruta

- [x] Paso 1 · Rediseño visual y carta dinámica
- [x] Paso 2 · Base de datos (16 tablas, vistas, función, procedimiento y trigger)
- [ ] Paso 3 · API en PHP (PDO): carta, horarios y reservas reales
- [ ] Paso 4 · Panel de administración con login
- [ ] Paso 5 · Extras: galería, eventos con inscripción, emails de confirmación, despliegue
