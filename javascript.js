
"use strict";

// MENÚ DE NAVEGACIÓN PARA MÓVILES

const menuToggle = document.getElementById("menu-toggle");
const nav = document.getElementById("nav");

if (menuToggle && nav) {
    menuToggle.addEventListener("click", () => {
        const isOpen = nav.classList.toggle("open");

        menuToggle.setAttribute("aria-expanded", String(isOpen));
        menuToggle.setAttribute(
            "aria-label",
            isOpen ? "Cerrar menú" : "Abrir menú"
        );

        menuToggle.textContent = isOpen ? "✕" : "☰";
    });

    nav.querySelectorAll("a").forEach((link) => {
        link.addEventListener("click", () => {
            nav.classList.remove("open");
            menuToggle.setAttribute("aria-expanded", "false");
            menuToggle.setAttribute("aria-label", "Abrir menú");
            menuToggle.textContent = "☰";
        });
    });
}

// AÑO AUTOMÁTICO EN EL PIE DE PÁGINA

const yearElement = document.getElementById("year");

if (yearElement) {
    yearElement.textContent = new Date().getFullYear();
}

// EVITAR SELECCIONAR UNA FECHA PASADA

const dateInput = document.getElementById("fecha");

if (dateInput) {
    const today = new Date();
    const localDate = new Date(
        today.getTime() - today.getTimezoneOffset() * 60000
    ).toISOString().slice(0, 10);

    dateInput.min = localDate;
}

// FORMULARIO DE RESERVAS: DEMOSTRACIÓN LOCAL

const reservationForm = document.getElementById("reservation-form");
const formMessage = document.getElementById("form-message");

if (reservationForm && formMessage) {
    reservationForm.addEventListener("submit", (event) => {
        event.preventDefault();

        if (!reservationForm.reportValidity()) {
            return;
        }

        const formData = new FormData(reservationForm);
        const nombre = String(formData.get("nombre") || "").trim();
        const fecha = String(formData.get("fecha") || "");
        const hora = String(formData.get("hora") || "");
        const personas = String(formData.get("personas") || "");

        if (!nombre || !fecha || !hora || !personas) {
            formMessage.textContent = "Completa los campos obligatorios.";
            return;
        }

        const fechaSeleccionada = new Date(`${fecha}T12:00:00`);
        const fechaActual = new Date();
        fechaActual.setHours(0, 0, 0, 0);

        if (fechaSeleccionada < fechaActual) {
            formMessage.textContent =
                "Selecciona una fecha de hoy o posterior.";
            return;
        }

        formMessage.textContent =
            `Gracias, ${nombre}. Has completado la demostración de reserva ` +
            `para el ${fecha}, a las ${hora}, para ${personas} persona(s). ` +
            "Todavía no se ha enviado al restaurante ni se ha guardado.";

        // No se envían ni se guardan datos en esta primera versión.
    });
}
