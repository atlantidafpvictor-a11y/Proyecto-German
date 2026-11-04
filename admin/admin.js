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
