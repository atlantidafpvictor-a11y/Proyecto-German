"use strict";

/* =========================================================
   SABOR MEDITERRÁNEO · Lógica de la web
   ---------------------------------------------------------
   La web pide los datos al servidor PHP (carpeta /api).
   Si el servidor todavía no existe (por ejemplo, al abrir el
   index.html con doble clic), usa los DATOS_DEMO de abajo,
   que tienen exactamente la misma forma que las tablas de la
   base de datos. Así la web funciona en los dos casos.
   ========================================================= */

const API = {
    carta: "api/carta.php",
    info: "api/info.php",
    disponibilidad: "api/disponibilidad.php",
    reservas: "api/reservas.php",
    contacto: "api/contacto.php",
    miReserva: "api/mi-reserva.php",
};

/* ---------- Datos de demostración (copia de la BD) ---------- */
const IMG = (id, w = 900) => `https://images.unsplash.com/photo-${id}?auto=format&fit=crop&w=${w}&q=80`;

const DATOS_DEMO = {
    alergenos: [
        { id: 1, nombre: "Gluten" }, { id: 2, nombre: "Crustáceos" }, { id: 3, nombre: "Huevo" },
        { id: 4, nombre: "Pescado" }, { id: 5, nombre: "Cacahuetes" }, { id: 6, nombre: "Soja" },
        { id: 7, nombre: "Lácteos" }, { id: 8, nombre: "Frutos de cáscara" }, { id: 9, nombre: "Apio" },
        { id: 10, nombre: "Mostaza" }, { id: 11, nombre: "Sésamo" }, { id: 12, nombre: "Sulfitos" },
        { id: 13, nombre: "Altramuces" }, { id: 14, nombre: "Moluscos" },
    ],
    categorias: [
        { id: 1, nombre: "Entrantes" },
        { id: 2, nombre: "Arroces" },
        { id: 3, nombre: "Principales" },
        { id: 4, nombre: "Postres" },
        { id: 5, nombre: "Bodega" },
    ],
    platos: [
        { id: 1, categoria_id: 1, nombre: "Salmorejo con jamón y huevo", descripcion: "Tomate de la Vega, pan de pueblo y aceite de Montefrío.", precio: 7.5, imagen: IMG("1547592180-85f173990554"), vegetariano: 0, sin_gluten: 0, destacado: 0, alergenos: [1, 3] },
        { id: 2, categoria_id: 1, nombre: "Remojón granadino", descripcion: "Naranja, bacalao desalado, aceituna negra y cebolleta.", precio: 8.5, imagen: IMG("1512621776951-a57141f2eefd"), vegetariano: 0, sin_gluten: 1, destacado: 1, alergenos: [4] },
        { id: 3, categoria_id: 1, nombre: "Berenjenas fritas con miel de caña", descripcion: "Finas, crujientes y con miel de caña de Frigiliana.", precio: 8, imagen: IMG("1512621776951-a57141f2eefd"), vegetariano: 1, sin_gluten: 0, destacado: 0, alergenos: [1] },
        { id: 4, categoria_id: 1, nombre: "Croquetas caseras de puchero", descripcion: "Seis unidades, con la carne del cocido de la casa.", precio: 9, imagen: IMG("1476224203421-9ac39bcb3327"), vegetariano: 0, sin_gluten: 0, destacado: 0, alergenos: [1, 3, 7] },

        { id: 5, categoria_id: 2, nombre: "Arroz negro con alioli", descripcion: "Sepia de Motril y su tinta. Mínimo dos personas.", precio: 16.5, unidad: "p.p.", imagen: IMG("1476224203421-9ac39bcb3327"), vegetariano: 0, sin_gluten: 1, destacado: 1, alergenos: [3, 14] },
        { id: 6, categoria_id: 2, nombre: "Arroz de verduras de la huerta", descripcion: "Alcachofa, judía verde, pimiento y ñora. Mínimo dos personas.", precio: 14, unidad: "p.p.", imagen: IMG("1547592180-85f173990554"), vegetariano: 1, sin_gluten: 1, destacado: 0, alergenos: [] },
        { id: 7, categoria_id: 2, nombre: "Arroz meloso de pato y setas", descripcion: "Pato confitado y setas de temporada de Sierra Nevada.", precio: 17, unidad: "p.p.", imagen: IMG("1476224203421-9ac39bcb3327"), vegetariano: 0, sin_gluten: 1, destacado: 0, alergenos: [9, 12] },

        { id: 8, categoria_id: 3, nombre: "Lubina a la sal", descripcion: "Pieza entera para compartir, con verduras asadas.", precio: 19.5, imagen: IMG("1544025162-d76694265947"), vegetariano: 0, sin_gluten: 1, destacado: 0, alergenos: [4] },
        { id: 9, categoria_id: 3, nombre: "Carrillada ibérica al vino tinto", descripcion: "Guisada a fuego lento durante seis horas, con puré de patata.", precio: 16, imagen: IMG("1544025162-d76694265947"), vegetariano: 0, sin_gluten: 1, destacado: 1, alergenos: [7, 12] },
        { id: 10, categoria_id: 3, nombre: "Cordero segureño al horno", descripcion: "Paletilla con patatas a lo pobre.", precio: 21, imagen: IMG("1544025162-d76694265947"), vegetariano: 0, sin_gluten: 1, destacado: 0, alergenos: [] },
        { id: 11, categoria_id: 3, nombre: "Pasta fresca al pesto de albahaca", descripcion: "Hecha cada mañana, con piñones y queso curado.", precio: 12.5, imagen: IMG("1551183053-bf91a1d81141"), vegetariano: 1, sin_gluten: 0, destacado: 0, alergenos: [1, 3, 7, 8] },

        { id: 12, categoria_id: 4, nombre: "Piononos de Santa Fe", descripcion: "Bizcocho borracho y crema tostada, el dulce de la provincia.", precio: 5.5, imagen: IMG("1488477181946-6428a0291777"), vegetariano: 1, sin_gluten: 0, destacado: 1, alergenos: [1, 3, 7] },
        { id: 13, categoria_id: 4, nombre: "Tarta de queso al horno", descripcion: "Cremosa por dentro, con mermelada de higos.", precio: 6, imagen: IMG("1488477181946-6428a0291777"), vegetariano: 1, sin_gluten: 0, destacado: 0, alergenos: [1, 3, 7] },
        { id: 14, categoria_id: 4, nombre: "Fruta de temporada", descripcion: "Lo que traiga ese día el mercado.", precio: 4.5, imagen: IMG("1488477181946-6428a0291777"), vegetariano: 1, sin_gluten: 1, destacado: 0, alergenos: [] },

        { id: 15, categoria_id: 5, nombre: "Vino tinto de la Contraviesa", descripcion: "Copa. Tempranillo y garnacha de altura.", precio: 3.5, imagen: IMG("1414235077428-338989a2e8c0"), vegetariano: 1, sin_gluten: 1, destacado: 0, alergenos: [12] },
        { id: 16, categoria_id: 5, nombre: "Vino blanco de Granada", descripcion: "Copa. Vijiriega, fresco y aromático.", precio: 3.5, imagen: IMG("1414235077428-338989a2e8c0"), vegetariano: 1, sin_gluten: 1, destacado: 0, alergenos: [12] },
        { id: 17, categoria_id: 5, nombre: "Cerveza de barril", descripcion: "Caña o tercio, con su tapa.", precio: 2.5, imagen: IMG("1414235077428-338989a2e8c0"), vegetariano: 1, sin_gluten: 0, destacado: 0, alergenos: [1] },
    ],
    // dia_semana: 1 = lunes ... 7 = domingo (igual que en la BD)
    horarios: [
        { dia_semana: 1, turno: "comida", apertura: null, cierre: null },
        { dia_semana: 1, turno: "cena", apertura: null, cierre: null },
        { dia_semana: 2, turno: "comida", apertura: "13:00", cierre: "16:00" },
        { dia_semana: 2, turno: "cena", apertura: "20:00", cierre: "23:30" },
        { dia_semana: 3, turno: "comida", apertura: "13:00", cierre: "16:00" },
        { dia_semana: 3, turno: "cena", apertura: "20:00", cierre: "23:30" },
        { dia_semana: 4, turno: "comida", apertura: "13:00", cierre: "16:00" },
        { dia_semana: 4, turno: "cena", apertura: "20:00", cierre: "23:30" },
        { dia_semana: 5, turno: "comida", apertura: "13:00", cierre: "16:30" },
        { dia_semana: 5, turno: "cena", apertura: "20:00", cierre: "00:00" },
        { dia_semana: 6, turno: "comida", apertura: "13:00", cierre: "16:30" },
        { dia_semana: 6, turno: "cena", apertura: "20:00", cierre: "00:00" },
        { dia_semana: 7, turno: "comida", apertura: "13:00", cierre: "16:30" },
        { dia_semana: 7, turno: "cena", apertura: null, cierre: null },
    ],
    servicios: [
        { nombre: "Menú del día", descripcion: "Primero, segundo, postre, pan y bebida. De martes a viernes a mediodía.", precio_desde: 15.9 },
        { nombre: "Celebraciones", descripcion: "Cumpleaños, comuniones o comidas de empresa en nuestro reservado para 20 personas.", precio_desde: 35 },
        { nombre: "Cata con maridaje", descripcion: "Un jueves al mes: cinco vinos de Granada con cinco platos de la casa.", precio_desde: 42 },
        { nombre: "Para llevar", descripcion: "Arroces y guisos listos para recoger. Encárgalos con una hora de antelación.", precio_desde: 12 },
    ],
    opiniones: [
        { nombre: "Lucía M.", puntuacion: 5, comentario: "El arroz negro es de los mejores que he probado en Granada. Y nos atendieron sin ninguna prisa.", fecha: "2026-09-14" },
        { nombre: "Javier R.", puntuacion: 5, comentario: "Fuimos doce para un cumpleaños y lo organizaron todo. El pionono del final, imprescindible.", fecha: "2026-08-30" },
        { nombre: "Carmen P.", puntuacion: 4, comentario: "Carta corta pero muy cuidada. Me avisaron de los alérgenos de cada plato sin tener que preguntar.", fecha: "2026-10-02" },
    ],
};

/* ---------- Utilidades ---------- */
const $ = (sel, ctx = document) => ctx.querySelector(sel);
const $$ = (sel, ctx = document) => [...ctx.querySelectorAll(sel)];
const euro = (n) => Number(n).toLocaleString("es-ES", { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + " €";
const DIAS = ["Lunes", "Martes", "Miércoles", "Jueves", "Viernes", "Sábado", "Domingo"];
const diaSemana = (date) => ((date.getDay() + 6) % 7) + 1; // 1 = lunes ... 7 = domingo
const isoLocal = (d) => new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
const aMinutos = (hhmm) => { const [h, m] = hhmm.split(":").map(Number); return (h === 0 ? 24 : h) * 60 + m; };
const escapeHTML = (s) => String(s).replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));

/**
 * Pide JSON al servidor PHP.
 * - Si PHP responde con JSON (aunque sea un error de validación), se devuelve tal cual.
 * - Si no hay servidor PHP (archivo abierto con doble clic, o el .php no existe),
 *   se usan los datos de demostración para que la web siga funcionando.
 */
async function pedir(url, respaldo, opciones) {
    try {
        const res = await fetch(url, opciones);
        const tipo = res.headers.get("Content-Type") || "";
        if (!tipo.includes("application/json")) throw new Error("Sin API");
        return { datos: await res.json(), demo: false };
    } catch {
        return { datos: typeof respaldo === "function" ? respaldo() : respaldo, demo: true };
    }
}

/* =========================================================
   1. CABECERA Y MENÚ MÓVIL
   ========================================================= */
function iniciarCabecera() {
    const header = $("#header");
    const toggle = $("#menu-toggle");
    const nav = $("#nav");

    const cerrar = () => {
        nav.classList.remove("open");
        toggle.setAttribute("aria-expanded", "false");
        toggle.setAttribute("aria-label", "Abrir menú");
    };

    toggle.addEventListener("click", () => {
        const abierto = nav.classList.toggle("open");
        toggle.setAttribute("aria-expanded", String(abierto));
        toggle.setAttribute("aria-label", abierto ? "Cerrar menú" : "Abrir menú");
    });
    $$("a", nav).forEach((a) => a.addEventListener("click", cerrar));
    document.addEventListener("keydown", (e) => e.key === "Escape" && cerrar());

    window.addEventListener("scroll", () => header.classList.toggle("scrolled", window.scrollY > 10), { passive: true });

    // Marca en el menú la sección que se está viendo
    const enlaces = $$(".nav a[href^='#']:not(.nav-cta)");
    const observer = new IntersectionObserver((entradas) => {
        entradas.forEach((e) => {
            if (e.isIntersecting) {
                enlaces.forEach((a) => a.classList.toggle("active", a.getAttribute("href") === "#" + e.target.id));
            }
        });
    }, { rootMargin: "-45% 0px -50% 0px" });
    enlaces.forEach((a) => { const s = $(a.getAttribute("href")); if (s) observer.observe(s); });

    $("#year").textContent = new Date().getFullYear();
}

/* =========================================================
   2. HORARIO: "Abierto ahora" + tabla semanal
   ========================================================= */
function turnosDelDia(horarios, dia) {
    return horarios.filter((h) => h.dia_semana === dia && h.apertura);
}

function pintarHorario(horarios) {
    const hoy = diaSemana(new Date());
    const tbody = $("#schedule tbody");
    tbody.innerHTML = DIAS.map((nombre, i) => {
        const dia = i + 1;
        const celda = (turno) => {
            const h = horarios.find((x) => x.dia_semana === dia && x.turno === turno);
            return h && h.apertura
                ? `<td>${h.apertura}–${h.cierre}</td>`
                : `<td class="closed">Cerrado</td>`;
        };
        return `<tr class="${dia === hoy ? "today" : ""}"><td>${nombre}${dia === hoy ? " (hoy)" : ""}</td>${celda("comida")}${celda("cena")}</tr>`;
    }).join("");

    // Estado en tiempo real
    const ahora = new Date();
    const min = ahora.getHours() * 60 + ahora.getMinutes();
    const turnos = turnosDelDia(horarios, hoy);
    const abierto = turnos.find((t) => min >= aMinutos(t.apertura) && min < aMinutos(t.cierre));
    const proximo = turnos.find((t) => min < aMinutos(t.apertura));

    const estado = $("#open-status");
    const texto = $(".open-status-text", estado);
    if (abierto) {
        estado.classList.add("is-open");
        texto.textContent = `Abierto ahora · cocina hasta las ${abierto.cierre}`;
    } else if (proximo) {
        estado.classList.add("is-closed");
        texto.textContent = `Cerrado ahora · abrimos hoy a las ${proximo.apertura}`;
    } else {
        estado.classList.add("is-closed");
        // Busca el siguiente día con servicio
        for (let i = 1; i <= 7; i++) {
            const d = ((hoy - 1 + i) % 7) + 1;
            const t = turnosDelDia(horarios, d)[0];
            if (t) { texto.textContent = `Cerrado ahora · abrimos el ${DIAS[d - 1].toLowerCase()} a las ${t.apertura}`; break; }
        }
    }
}

/* =========================================================
   3. CARTA DINÁMICA
   ========================================================= */
function iniciarCarta({ categorias, platos, alergenos }) {
    const tabs = $("#menu-tabs");
    const lista = $("#dish-list");
    const fVeg = $("#f-veg");
    const fSg = $("#f-sg");
    const previewImg = $("#preview-img");
    const previewCap = $("#preview-caption");
    const nombreAlergeno = Object.fromEntries(alergenos.map((a) => [a.id, a.nombre]));

    let categoriaActiva = categorias[0].id;

    tabs.innerHTML = categorias.map((c, i) =>
        `<button class="tab" role="tab" type="button" data-id="${c.id}" aria-selected="${i === 0}" tabindex="${i === 0 ? 0 : -1}">${escapeHTML(c.nombre)}</button>`
    ).join("");

    $("#allergen-legend").innerHTML = alergenos.map((a) => `<li>${escapeHTML(a.nombre)}</li>`).join("");

    function mostrarPreview(plato) {
        if (!plato || previewImg.dataset.id === String(plato.id)) return;
        previewImg.dataset.id = plato.id;
        previewImg.classList.add("swapping");
        const nueva = new Image();
        nueva.onload = nueva.onerror = () => {
            previewImg.src = plato.imagen;
            previewImg.alt = plato.nombre;
            previewImg.classList.remove("swapping");
        };
        nueva.src = plato.imagen;
        previewCap.textContent = plato.nombre;
        $$(".dish", lista).forEach((li) => li.classList.toggle("is-preview", li.dataset.id === String(plato.id)));
    }

    function pintar() {
        const visibles = platos.filter((p) =>
            p.categoria_id === categoriaActiva &&
            (!fVeg.checked || p.vegetariano) &&
            (!fSg.checked || p.sin_gluten)
        );

        if (!visibles.length) {
            lista.innerHTML = `<li class="dish-empty">No hay platos en esta sección con esos filtros.
                <button type="button" id="clear-filters">Quitar filtros</button></li>`;
            $("#clear-filters").onclick = () => { fVeg.checked = fSg.checked = false; pintar(); };
            return;
        }

        lista.innerHTML = visibles.map((p) => {
            const tags = [];
            if (p.destacado) tags.push(`<span class="tag tag-star">Recomendado</span>`);
            if (p.vegetariano) tags.push(`<span class="tag tag-diet">Vegetariano</span>`);
            if (p.sin_gluten) tags.push(`<span class="tag tag-diet">Sin gluten</span>`);
            p.alergenos.forEach((id) => tags.push(`<span class="tag">${escapeHTML(nombreAlergeno[id])}</span>`));
            return `
            <li class="dish" data-id="${p.id}" tabindex="0">
                <div class="dish-top">
                    <h3 class="dish-name">${escapeHTML(p.nombre)}</h3>
                    <span class="dish-leader" aria-hidden="true"></span>
                    <span class="dish-price">${euro(p.precio)}${p.unidad ? ` <small>${escapeHTML(p.unidad)}</small>` : ""}</span>
                </div>
                <p class="dish-desc">${escapeHTML(p.descripcion)}</p>
                ${tags.length ? `<div class="dish-meta">${tags.join("")}</div>` : ""}
            </li>`;
        }).join("");

        $$(".dish", lista).forEach((li) => {
            const plato = platos.find((p) => String(p.id) === li.dataset.id);
            li.addEventListener("mouseenter", () => mostrarPreview(plato));
            li.addEventListener("focus", () => mostrarPreview(plato));
        });
        mostrarPreview(visibles.find((p) => p.destacado) || visibles[0]);
    }

    tabs.addEventListener("click", (e) => {
        const btn = e.target.closest(".tab");
        if (!btn) return;
        activarTab(btn);
    });
    // Navegación con flechas entre pestañas (accesibilidad)
    tabs.addEventListener("keydown", (e) => {
        if (!["ArrowRight", "ArrowLeft"].includes(e.key)) return;
        const lista = $$(".tab", tabs);
        const i = lista.indexOf(document.activeElement);
        const sig = lista[(i + (e.key === "ArrowRight" ? 1 : -1) + lista.length) % lista.length];
        sig.focus(); activarTab(sig);
    });
    function activarTab(btn) {
        $$(".tab", tabs).forEach((t) => { t.setAttribute("aria-selected", "false"); t.tabIndex = -1; });
        btn.setAttribute("aria-selected", "true"); btn.tabIndex = 0;
        categoriaActiva = Number(btn.dataset.id);
        pintar();
    }

    fVeg.addEventListener("change", pintar);
    fSg.addEventListener("change", pintar);
    pintar();
}

/* =========================================================
   4. SERVICIOS Y OPINIONES
   ========================================================= */
function pintarServicios(servicios) {
    $("#services-grid").innerHTML = servicios.map((s) => `
        <article class="service">
            <h3>${escapeHTML(s.nombre)}</h3>
            <p>${escapeHTML(s.descripcion)}</p>
            ${s.precio_desde ? `<p class="from">Desde ${euro(s.precio_desde)}</p>` : ""}
        </article>`).join("");
}

function pintarOpiniones(opiniones) {
    $("#reviews-track").innerHTML = opiniones.map((o) => `
        <figure class="review">
            <p class="stars" aria-label="${o.puntuacion} de 5 estrellas">${"★".repeat(o.puntuacion)}${"☆".repeat(5 - o.puntuacion)}</p>
            <blockquote>“${escapeHTML(o.comentario)}”</blockquote>
            <figcaption><strong>${escapeHTML(o.nombre)}</strong> · ${new Date(o.fecha).toLocaleDateString("es-ES", { month: "long", year: "numeric" })}</figcaption>
        </figure>`).join("");
}

/* =========================================================
   5. RESERVAS
   ========================================================= */
function iniciarReservas(horarios, diasCerrados = []) {
    const cerrados = new Set(diasCerrados.map((d) => d.fecha));
    const form = $("#booking-form");
    const party = $("#party");
    const strip = $("#date-strip");
    const otraFecha = $("#fecha");
    const slots = $("#slots");
    const resumen = $("#booking-summary");
    const enviar = $("#booking-submit");
    const hecho = $("#booking-done");

    const estado = { personas: 2, fecha: null, hora: null };

    /* --- Paso 1: personas --- */
    party.innerHTML = Array.from({ length: 8 }, (_, i) => i + 1).map((n) =>
        `<button type="button" class="opt" role="radio" data-n="${n}" aria-checked="${n === 2}" aria-label="${n} ${n === 1 ? "persona" : "personas"}">${n}</button>`
    ).join("");
    party.addEventListener("click", (e) => {
        const b = e.target.closest(".opt"); if (!b) return;
        estado.personas = Number(b.dataset.n);
        marcar(party, b);
        cargarHoras();
    });

    /* --- Paso 2: fecha (los próximos 7 días con servicio) --- */
    const hoy = new Date(); hoy.setHours(0, 0, 0, 0);
    otraFecha.min = isoLocal(hoy);
    const max = new Date(hoy); max.setDate(max.getDate() + 90);
    otraFecha.max = isoLocal(max);

    const dias = [];
    for (let i = 0; dias.length < 7 && i < 21; i++) {
        const d = new Date(hoy); d.setDate(d.getDate() + i);
        if (turnosDelDia(horarios, diaSemana(d)).length && !cerrados.has(isoLocal(d))) dias.push(d);
    }
    strip.innerHTML = dias.map((d, i) => `
        <button type="button" class="opt" role="radio" data-fecha="${isoLocal(d)}" aria-checked="false">
            <span class="wd">${i === 0 && d.getTime() === hoy.getTime() ? "hoy" : d.toLocaleDateString("es-ES", { weekday: "short" })}</span>
            <span class="d">${d.getDate()}</span>
            <span class="m">${d.toLocaleDateString("es-ES", { month: "short" })}</span>
        </button>`).join("");

    strip.addEventListener("click", (e) => {
        const b = e.target.closest(".opt"); if (!b) return;
        estado.fecha = b.dataset.fecha;
        otraFecha.value = "";
        marcar(strip, b);
        cargarHoras();
    });
    otraFecha.addEventListener("change", () => {
        if (!otraFecha.value) return;
        estado.fecha = otraFecha.value;
        const b = $(`[data-fecha="${otraFecha.value}"]`, strip);
        marcar(strip, b);
        cargarHoras();
    });

    /* --- Paso 3: horas disponibles --- */
    function horasDemo(fecha, personas) {
        // Genera huecos cada 30 min hasta 1 h antes del cierre y simula
        // ocupación de forma estable (misma fecha = mismo resultado).
        const d = new Date(fecha + "T12:00:00");
        const ahora = new Date();
        const esHoy = isoLocal(ahora) === fecha;
        const minAhora = ahora.getHours() * 60 + ahora.getMinutes();
        const resultado = [];
        turnosDelDia(horarios, diaSemana(d)).forEach((t) => {
            for (let m = aMinutos(t.apertura); m <= aMinutos(t.cierre) - 60; m += 30) {
                const hora = `${String(Math.floor(m / 60) % 24).padStart(2, "0")}:${String(m % 60).padStart(2, "0")}`;
                const semilla = [...(fecha + hora)].reduce((a, c) => (a * 31 + c.charCodeAt(0)) % 997, 7);
                const ocupacion = (semilla % 10) / 10 + personas * 0.04;
                resultado.push({ hora, turno: t.turno, libre: !(esHoy && m <= minAhora + 60) && ocupacion < 0.85 });
            }
        });
        return { slots: resultado };
    }

    async function cargarHoras() {
        estado.hora = null;
        actualizarResumen();
        if (!estado.fecha) {
            slots.innerHTML = `<p class="slots-msg">Elige un día para ver las horas libres.</p>`;
            return;
        }
        const d = new Date(estado.fecha + "T12:00:00");
        if (!turnosDelDia(horarios, diaSemana(d)).length || cerrados.has(estado.fecha)) {
            slots.innerHTML = `<p class="slots-msg">Ese día estamos cerrados. Prueba con otra fecha.</p>`;
            return;
        }
        slots.innerHTML = `<p class="slots-msg">Buscando mesa…</p>`;
        const url = `${API.disponibilidad}?fecha=${estado.fecha}&personas=${estado.personas}`;
        const peticion = (cargarHoras.ultima = Symbol());
        const { datos } = await pedir(url, () => horasDemo(estado.fecha, estado.personas));
        if (peticion !== cargarHoras.ultima) return; // llegó una respuesta antigua: se ignora

        if (!datos.slots || datos.cerrado) {
            slots.innerHTML = `<p class="slots-msg">${datos.cerrado ? "Ese día estamos cerrados. Prueba con otra fecha." : escapeHTML(datos.error || "No hemos podido consultar las mesas libres.")}</p>`;
            return;
        }

        const grupos = { comida: "Comida", cena: "Cena" };
        const html = Object.entries(grupos).map(([turno, titulo]) => {
            const lista = datos.slots.filter((s) => s.turno === turno);
            if (!lista.length) return "";
            return `<div class="slots-group"><h4>${titulo}</h4><div class="slots-row" role="radiogroup" aria-label="${titulo}">
                ${lista.map((s) => `<button type="button" class="opt" role="radio" aria-checked="false" data-hora="${s.hora}" ${s.libre ? "" : "disabled aria-label=\"" + s.hora + ", completo\""}>${s.hora}</button>`).join("")}
            </div></div>`;
        }).join("");
        const hayLibres = datos.slots.some((s) => s.libre);
        slots.innerHTML = hayLibres ? html : `<p class="slots-msg">No quedan mesas para ${estado.personas} ese día. Prueba otro día o llámanos.</p>`;
    }

    slots.addEventListener("click", (e) => {
        const b = e.target.closest(".opt:not([disabled])"); if (!b) return;
        estado.hora = b.dataset.hora;
        $$(".opt", slots).forEach((o) => o.setAttribute("aria-checked", String(o === b)));
        actualizarResumen();
        $(".slots-error", slots)?.remove();
    });

    function marcar(grupo, elegido) {
        $$(".opt", grupo).forEach((o) => o.setAttribute("aria-checked", String(o === elegido)));
    }

    function actualizarResumen() {
        if (!estado.fecha || !estado.hora) { resumen.innerHTML = ""; return; }
        const f = new Date(estado.fecha + "T12:00:00").toLocaleDateString("es-ES", { weekday: "long", day: "numeric", month: "long" });
        resumen.innerHTML = `Mesa para <strong>${estado.personas} ${estado.personas === 1 ? "persona" : "personas"}</strong> el <strong>${f}</strong> a las <strong>${estado.hora}</strong>.`;
    }

    /* --- Validación --- */
    function error(campo, msg) {
        const p = $(`.field-error[data-for="${campo}"]`);
        if (p) p.textContent = msg;
        const input = document.getElementById(campo);
        if (input) input.closest(".field")?.classList.toggle("invalid", Boolean(msg));
    }

    function validar() {
        let ok = true;
        const nombre = $("#nombre").value.trim();
        const tel = $("#telefono").value.replace(/\s/g, "");
        const email = $("#email").value.trim();

        error("nombre", nombre.length >= 3 ? "" : "Escribe tu nombre y apellidos.");
        error("telefono", /^\+?[0-9]{9,14}$/.test(tel) ? "" : "Escribe un teléfono de 9 cifras.");
        error("email", /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) ? "" : "Revisa el email: falta algo como nombre@correo.com.");
        error("privacidad", $("#privacidad").checked ? "" : "Necesitamos tu permiso para guardar la reserva.");
        if (nombre.length < 3 || !/^\+?[0-9]{9,14}$/.test(tel) || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) || !$("#privacidad").checked) ok = false;

        if (!estado.fecha || !estado.hora) {
            ok = false;
            slots.scrollIntoView({ behavior: "smooth", block: "center" });
            if (!$(".slots-error", slots)) slots.insertAdjacentHTML("beforeend", `<p class="field-error slots-error">Elige día y hora para continuar.</p>`);
        }
        return ok;
    }
    ["nombre", "telefono", "email"].forEach((id) => $("#" + id).addEventListener("input", () => error(id, "")));
    $("#privacidad").addEventListener("change", () => error("privacidad", ""));

    /* --- Envío --- */
    form.addEventListener("submit", async (e) => {
        e.preventDefault();
        if (!validar()) { form.querySelector(".invalid input")?.focus(); return; }

        const reserva = {
            nombre: $("#nombre").value.trim(),
            telefono: $("#telefono").value.replace(/\s/g, ""),
            email: $("#email").value.trim(),
            fecha: estado.fecha,
            hora: estado.hora,
            personas: estado.personas,
            zona_id: Number($("#zona").value),
            comentarios: $("#comentarios").value.trim(),
            web: $("#web").value, // campo trampa anti-bots
        };

        enviar.disabled = true;
        enviar.textContent = "Confirmando…";

        const codigoDemo = () => ({ ok: true, codigo: Math.random().toString(36).slice(2, 8).toUpperCase() });
        const { datos, demo } = await pedir(API.reservas, codigoDemo, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(reserva),
        });

        enviar.disabled = false;
        enviar.textContent = "Confirmar reserva";

        if (!datos.ok) {
            // Errores de validación del servidor: se marcan en su campo
            Object.entries(datos.campos || {}).forEach(([campo, msg]) => error(campo, msg));
            if (datos.campos?.hora || /completar/.test(datos.error || "")) cargarHoras();
            resumen.innerHTML = `<span style="color:var(--error)">${escapeHTML(datos.error || "No se ha podido guardar la reserva. Inténtalo de nuevo o llámanos.")}</span>`;
            return;
        }

        const f = new Date(reserva.fecha + "T12:00:00").toLocaleDateString("es-ES", { weekday: "long", day: "numeric", month: "long" });
        $("#booking-done-text").textContent =
            `${reserva.nombre.split(" ")[0]}, te esperamos el ${f} a las ${reserva.hora}. ` +
            (demo ? "Modo demostración: la reserva aún no se ha guardado en la base de datos."
                  : "Guarda este código: te lo pediremos si necesitas cambiar o cancelar la reserva.");
        $("#booking-code").textContent = datos.codigo;
        ultimaReserva = { codigo: datos.codigo, telefono: reserva.telefono };
        form.hidden = true;
        hecho.hidden = false;
        hecho.focus();
    });

    $("#booking-new").addEventListener("click", () => {
        form.reset();
        estado.fecha = estado.hora = null;
        marcar(strip, null);
        cargarHoras();
        hecho.hidden = true;
        form.hidden = false;
    });

    cargarHoras();
}

/* =========================================================
   5b. CONSULTAR O CANCELAR UNA RESERVA
   ========================================================= */
let ultimaReserva = null; // se rellena al reservar, para no tener que copiar el código

function iniciarMiReserva() {
    const dialogo = $("#manage-dialog");
    if (!dialogo) return;
    const form = $("#manage-form");
    const buscar = $("#manage-search");
    const errorGeneral = $("#manage-error");
    const vistaBuscar = $("#manage-lookup");
    const vistaResultado = $("#manage-result");
    const confirmar = $("#manage-confirm");
    const acciones = $("#manage-actions");
    const botonCancelar = $("#manage-cancel");
    const ESTADOS = {
        pendiente: "Pendiente de mesa",
        confirmada: "Confirmada",
        cancelada: "Cancelada",
        completada: "Completada",
        no_presentado: "No presentada",
    };
    let actual = null; // { codigo, telefono } de la reserva que se está viendo

    const marcar = (id, msg) => {
        const campo = document.getElementById(id).closest(".field");
        campo.classList.toggle("invalid", Boolean(msg));
        $(".field-error", campo).textContent = msg || "";
    };

    function abrir() {
        mostrarBuscar();
        if (ultimaReserva) {
            $("#m-codigo").value = ultimaReserva.codigo;
            $("#m-telefono").value = ultimaReserva.telefono;
        }
        dialogo.showModal();
        $("#m-codigo").focus();
    }

    function mostrarBuscar() {
        vistaResultado.hidden = true;
        vistaBuscar.hidden = false;
        errorGeneral.textContent = "";
        ["m-codigo", "m-telefono"].forEach((id) => marcar(id, ""));
    }

    function mostrarReserva(r) {
        const fecha = new Date(r.fecha + "T12:00:00").toLocaleDateString("es-ES", { weekday: "long", day: "numeric", month: "long" });
        $("#manage-result-title").textContent = r.estado === "cancelada" ? "Reserva cancelada" : `Hola, ${r.nombre}`;
        const estado = $("#manage-status");
        estado.textContent = ESTADOS[r.estado] || r.estado;
        estado.className = "manage-status is-" + r.estado;
        $("#manage-details").innerHTML = `
            <dt>Código</dt><dd>${escapeHTML(r.codigo)}</dd>
            <dt>Día</dt><dd>${escapeHTML(fecha.charAt(0).toUpperCase() + fecha.slice(1))}</dd>
            <dt>Hora</dt><dd>${escapeHTML(r.hora)}</dd>
            <dt>Personas</dt><dd>${r.personas}</dd>
            ${r.zona ? `<dt>Zona</dt><dd>${escapeHTML(r.zona)}</dd>` : ""}`;
        $("#manage-note").textContent = r.estado === "cancelada"
            ? "La mesa ha quedado libre. Si cambias de idea, puedes hacer una reserva nueva."
            : r.motivo;
        botonCancelar.hidden = !r.puede_cancelar;
        confirmar.hidden = true;
        acciones.hidden = false;
        vistaBuscar.hidden = true;
        vistaResultado.hidden = false;
        $("#manage-result-title").focus();
    }

    async function enviar(accion) {
        const { datos, demo } = await pedir(API.miReserva, () => ({
            ok: false,
            error: "Para consultar reservas hace falta el servidor (XAMPP o Ubuntu). En modo demostración no se guardan.",
        }), {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ ...actual, accion }),
        });
        return { datos, demo };
    }

    form.addEventListener("submit", async (e) => {
        e.preventDefault();
        const codigo = $("#m-codigo").value.trim().toUpperCase();
        const telefono = $("#m-telefono").value.trim();
        let ok = true;
        if (!/^[A-Z0-9]{6}$/.test(codigo)) { marcar("m-codigo", "El código tiene 6 letras y números."); ok = false; }
        if (telefono.replace(/\D/g, "").length < 9) { marcar("m-telefono", "Escribe el teléfono de la reserva."); ok = false; }
        if (!ok) return;

        actual = { codigo, telefono };
        errorGeneral.textContent = "";
        buscar.disabled = true;
        buscar.textContent = "Buscando…";
        const { datos } = await enviar("consultar");
        buscar.disabled = false;
        buscar.textContent = "Buscar reserva";

        if (!datos.ok) {
            if (datos.campo) marcar(datos.campo, datos.error);
            else errorGeneral.textContent = datos.error || "No se ha podido consultar la reserva.";
            return;
        }
        mostrarReserva(datos.reserva);
    });

    ["m-codigo", "m-telefono"].forEach((id) => document.getElementById(id).addEventListener("input", () => {
        marcar(id, "");
        errorGeneral.textContent = "";
    }));

    botonCancelar.addEventListener("click", () => {
        acciones.hidden = true;
        confirmar.hidden = false;
        $("#manage-cancel-no").focus();
    });
    $("#manage-cancel-no").addEventListener("click", () => {
        confirmar.hidden = true;
        acciones.hidden = false;
        botonCancelar.focus();
    });
    $("#manage-cancel-yes").addEventListener("click", async (e) => {
        const b = e.currentTarget;
        b.disabled = true;
        b.textContent = "Cancelando…";
        const { datos } = await enviar("cancelar");
        b.disabled = false;
        b.textContent = "Sí, cancelar reserva";
        if (datos.reserva) mostrarReserva(datos.reserva);
        if (!datos.ok && !datos.reserva) {
            confirmar.hidden = true;
            acciones.hidden = false;
            $("#manage-note").textContent = datos.error || "No se ha podido cancelar. Llámanos al 958 000 000.";
        }
    });

    $("#manage-back").addEventListener("click", () => { mostrarBuscar(); form.reset(); $("#m-codigo").focus(); });
    $("#manage-open").addEventListener("click", abrir);
    $("#manage-close").addEventListener("click", () => dialogo.close());
    // Cerrar al pulsar fuera de la ventana
    dialogo.addEventListener("click", (e) => {
        if (e.target !== dialogo) return;
        const r = dialogo.getBoundingClientRect();
        const fuera = e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom;
        if (fuera) dialogo.close();
    });
    dialogo.addEventListener("close", () => $("#manage-open").focus());
}

/* =========================================================
   6. FORMULARIO DE CONTACTO
   ========================================================= */
function iniciarContacto() {
    const form = $("#contact-form");
    if (!form) return;
    const estado = $("#contact-status");
    const boton = $("button[type=submit]", form);

    const reglas = {
        "c-nombre": (v) => v.length >= 2 || "Escribe tu nombre.",
        "c-email": (v) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) || "Revisa el email: falta algo como nombre@correo.com.",
        "c-asunto": (v) => v.length >= 3 || "Escribe un asunto.",
        "c-mensaje": (v) => v.length >= 10 || "Cuéntanos un poco más (mínimo 10 caracteres).",
    };
    const marcar = (id, msg) => {
        const campo = document.getElementById(id).closest(".field");
        campo.classList.toggle("invalid", Boolean(msg));
        $(".field-error", campo).textContent = msg || "";
    };
    Object.keys(reglas).forEach((id) => document.getElementById(id).addEventListener("input", () => marcar(id, "")));

    form.addEventListener("submit", async (e) => {
        e.preventDefault();
        let ok = true;
        Object.entries(reglas).forEach(([id, regla]) => {
            const r = regla(document.getElementById(id).value.trim());
            marcar(id, r === true ? "" : r);
            if (r !== true) ok = false;
        });
        if (!ok) { $(".invalid input, .invalid textarea", form)?.focus(); return; }

        boton.disabled = true;
        boton.textContent = "Enviando…";
        const cuerpo = {
            nombre: $("#c-nombre").value.trim(),
            email: $("#c-email").value.trim(),
            asunto: $("#c-asunto").value.trim(),
            mensaje: $("#c-mensaje").value.trim(),
            web: $("#c-web").value,
        };
        const { datos, demo } = await pedir(API.contacto, { ok: true }, {
            method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(cuerpo),
        });
        boton.disabled = false;
        boton.textContent = "Enviar mensaje";

        if (!datos.ok) {
            Object.entries(datos.campos || {}).forEach(([id, msg]) => marcar(id, msg));
            estado.textContent = datos.error || "No se ha podido enviar. Prueba a llamarnos.";
            estado.className = "contact-status is-error";
            return;
        }
        form.reset();
        estado.textContent = demo
            ? "Mensaje enviado (modo demostración: no se ha guardado)."
            : "Mensaje enviado. Te responderemos en menos de 24 horas.";
        estado.className = "contact-status is-ok";
    });
}

/* =========================================================
   ARRANQUE
   ========================================================= */
document.addEventListener("DOMContentLoaded", async () => {
    iniciarCabecera();

    const cartaDemo = { categorias: DATOS_DEMO.categorias, platos: DATOS_DEMO.platos, alergenos: DATOS_DEMO.alergenos };
    const infoDemo = { horarios: DATOS_DEMO.horarios, servicios: DATOS_DEMO.servicios, opiniones: DATOS_DEMO.opiniones };
    let [{ datos: carta }, { datos: info }] = await Promise.all([pedir(API.carta, cartaDemo), pedir(API.info, infoDemo)]);

    // Si la base de datos falla, la web sigue mostrando la carta de ejemplo
    if (!carta.platos || !carta.categorias?.length) carta = cartaDemo;
    if (!info.horarios) info = infoDemo;

    pintarHorario(info.horarios);
    iniciarCarta(carta);
    pintarServicios(info.servicios);
    pintarOpiniones(info.opiniones);
    iniciarReservas(info.horarios, info.dias_cerrados || []);
    iniciarContacto();
    iniciarMiReserva();
});
