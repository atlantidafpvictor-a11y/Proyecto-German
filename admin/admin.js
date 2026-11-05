/**
 * JavaScript del panel. Va en un archivo aparte (y no dentro del HTML)
 * para que la Content-Security-Policy pueda prohibir todo el código
 * en línea: aunque alguien consiguiera colar un <script> en una página,
 * el navegador no lo ejecutaría.
 */
"use strict";

// Botones peligrosos: <button data-confirm="¿Seguro?"> pide confirmación antes de enviar
document.addEventListener("click", (e) => {
    const boton = e.target.closest("[data-confirm]");
    if (boton && !window.confirm(boton.dataset.confirm)) e.preventDefault();
});

// Vista previa de la foto elegida: <input type="file" data-preview="id-de-la-img">
document.querySelectorAll("input[type=file][data-preview]").forEach((input) => {
    input.addEventListener("change", () => {
        const img = document.getElementById(input.dataset.preview);
        const archivo = input.files[0];
        if (!img) return;
        if (!archivo) { img.hidden = true; return; }
        img.src = URL.createObjectURL(archivo);
        img.hidden = false;
    });
});

// Menú lateral en el móvil: se abre como un cajón y se cierra con Escape o tocando fuera
(() => {
    const boton = document.getElementById("nav-toggle");
    const velo = document.getElementById("scrim");
    if (!boton || !velo) return;
    const abrir = (si) => {
        document.body.classList.toggle("nav-open", si);
        boton.setAttribute("aria-expanded", String(si));
        boton.setAttribute("aria-label", si ? "Cerrar menú" : "Abrir menú");
        velo.hidden = !si;
    };
    boton.addEventListener("click", () => abrir(!document.body.classList.contains("nav-open")));
    velo.addEventListener("click", () => abrir(false));
    document.addEventListener("keydown", (e) => { if (e.key === "Escape") abrir(false); });
})();

// Reloj de la barra superior (la hora la pone el servidor; aquí solo avanza)
(() => {
    const reloj = document.getElementById("reloj");
    if (!reloj) return;
    const pintar = () => { reloj.textContent = new Date().toLocaleTimeString("es-ES", { hour: "2-digit", minute: "2-digit" }); };
    setInterval(pintar, 20000);
})();
