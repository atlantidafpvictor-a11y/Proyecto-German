/* =========================================================
   Carta para el móvil (carta.html?mesa=7)
   Pide la carta a la API en el idioma elegido y permite esconder
   los platos que llevan los alérgenos que el cliente no puede tomar.
   ========================================================= */
"use strict";

(function () {
    const $ = (s, c = document) => c.querySelector(s);
    const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
    const ocultar = new Set(); // ids de alérgenos marcados
    let datos = null;

    traducirPagina();
    pintarSelectorIdioma($("#lang-switch"));

    // Número de mesa del QR: solo números del 1 al 99 (nada de texto inventado en la dirección)
    const mesa = Number(new URLSearchParams(location.search).get("mesa"));
    if (Number.isInteger(mesa) && mesa >= 1 && mesa <= 99) {
        const etiqueta = $("#c-mesa");
        etiqueta.textContent = t("carta.mesa", { n: mesa });
        etiqueta.hidden = false;
        document.title = `${t("carta.mesa", { n: mesa })} · ${document.title}`;
    }

    // Recordar los alérgenos marcados mientras dura la visita
    try { JSON.parse(sessionStorage.getItem("sabor-alergenos") || "[]").forEach((id) => ocultar.add(id)); } catch { /* sin almacenamiento */ }

    fetch(`api/carta.php?lang=${IDIOMA}`, { headers: { Accept: "application/json" } })
        .then((r) => {
            if (!r.ok || !(r.headers.get("Content-Type") || "").includes("json")) throw new Error("sin API");
            return r.json();
        })
        .then((d) => { datos = d; pintarFiltro(); pintar(); })
        .catch(() => { $("#c-lista").innerHTML = `<p class="c-msg">${esc(t("carta.error"))}</p>`; });

    function pintarFiltro() {
        $("#c-chips").innerHTML = datos.alergenos.map((a) =>
            `<button type="button" data-id="${a.id}" aria-pressed="${ocultar.has(a.id)}">${esc(a.nombre)}</button>`).join("");
        $("#c-chips").addEventListener("click", (e) => {
            const b = e.target.closest("button[data-id]");
            if (!b) return;
            const id = Number(b.dataset.id);
            ocultar.has(id) ? ocultar.delete(id) : ocultar.add(id);
            b.setAttribute("aria-pressed", String(ocultar.has(id)));
            try { sessionStorage.setItem("sabor-alergenos", JSON.stringify([...ocultar])); } catch { /* sin almacenamiento */ }
            pintar();
        });
    }

    function pintar() {
        const nombre = Object.fromEntries(datos.alergenos.map((a) => [a.id, a.nombre]));
        $("#c-filtro-n").textContent = ocultar.size ? `(${ocultar.size})` : "";
        let ocultos = 0;

        $("#c-tabs").innerHTML = datos.categorias.map((c, i) =>
            `<a href="#cat-${c.id}" ${i === 0 ? 'aria-current="true"' : ""}>${esc(c.nombre)}</a>`).join("");

        $("#c-lista").innerHTML = datos.categorias.map((c) => {
            const platos = datos.platos.filter((p) => p.categoria_id === c.id);
            const visibles = platos.filter((p) => !p.alergenos.some((a) => ocultar.has(a)));
            ocultos += platos.length - visibles.length;
            return `<section class="c-cat" id="cat-${c.id}">
                <h2>${esc(c.nombre)}</h2>
                ${c.descripcion ? `<p class="c-cat-desc">${esc(c.descripcion)}</p>` : ""}
                ${visibles.map(plato).join("")}
            </section>`;
        }).join("") + (ocultos ? `<p class="c-ocultos">${esc(ocultos === 1 ? t("carta.oculto1") : t("carta.ocultos", { n: ocultos }))}</p>` : "");

        function plato(p) {
            const tags = [];
            if (p.destacado) tags.push(`<span class="c-tag c-tag-star">${esc(t("tag.recomendado"))}</span>`);
            if (p.vegano) tags.push(`<span class="c-tag c-tag-diet">${esc(t("tag.vegano"))}</span>`);
            else if (p.vegetariano) tags.push(`<span class="c-tag c-tag-diet">${esc(t("tag.vegetariano"))}</span>`);
            if (p.sin_gluten) tags.push(`<span class="c-tag c-tag-diet">${esc(t("tag.singluten"))}</span>`);
            const alerg = p.alergenos.length
                ? t("carta.contiene", { lista: p.alergenos.map((a) => nombre[a]).join(", ") })
                : t("carta.sinAlergenos");
            tags.push(`<span class="c-tag">${esc(alerg)}</span>`);
            const unidad = p.unidad ? ` <small>${esc(t("unidad." + p.unidad))}</small>` : "";
            return `<article class="c-plato">
                <h3>${esc(p.nombre)}</h3>
                <p class="c-precio">${esc(dinero(p.precio))}${unidad}</p>
                <p class="c-desc">${esc(p.descripcion)}</p>
                <div class="c-tags">${tags.join("")}</div>
            </article>`;
        }
        vigilarCategorias();
    }

    // Marca en las pestañas la categoría que se está viendo
    function vigilarCategorias() {
        if (!("IntersectionObserver" in window)) return;
        const tabs = [...document.querySelectorAll("#c-tabs a")];
        const obs = new IntersectionObserver((entradas) => {
            entradas.forEach((e) => {
                if (!e.isIntersecting) return;
                tabs.forEach((a) => a.setAttribute("aria-current", String(a.getAttribute("href") === "#" + e.target.id)));
                // Centrar la pestaña activa sin mover la página
                const nav = document.querySelector("#c-tabs");
                const activa = nav.querySelector('a[aria-current="true"]');
                if (activa) nav.scrollTo({ left: activa.offsetLeft - nav.clientWidth / 2 + activa.clientWidth / 2, behavior: "smooth" });
            });
        }, { rootMargin: "-70px 0px -70% 0px" });
        document.querySelectorAll(".c-cat").forEach((s) => obs.observe(s));
    }
})();
