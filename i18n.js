/* =========================================================
   Idiomas de la web (español / inglés)

   - El idioma se elige con ?lang=en o ?lang=es en la dirección, o con el
     selector ES · EN (se recuerda en el navegador). Si nunca se ha elegido,
     se usa el del navegador: un turista con el móvil en inglés ve la web en
     inglés sin tocar nada.
   - En el HTML, data-i18n="clave" cambia el texto del elemento;
     data-i18n-attr="placeholder=clave;aria-label=clave" cambia atributos;
     data-i18n-html="clave" se usa solo con textos de este archivo que llevan
     <strong> o <br> (nunca con datos de usuarios).
   - En JavaScript: t("clave", { n: 3 }) devuelve el texto con {n} sustituido.
   - Los platos, eventos y fotos llegan ya traducidos desde la API (?lang=en).
   ========================================================= */
"use strict";

const IDIOMAS = ["es", "en"];

const IDIOMA = (() => {
    const guardar = (v) => { try { localStorage.setItem("sabor-idioma", v); } catch { /* modo privado */ } };
    const url = new URLSearchParams(location.search).get("lang");
    if (IDIOMAS.includes(url)) { guardar(url); return url; }
    try {
        const guardado = localStorage.getItem("sabor-idioma");
        if (IDIOMAS.includes(guardado)) return guardado;
    } catch { /* modo privado */ }
    return (navigator.language || "es").toLowerCase().startsWith("es") ? "es" : "en";
})();

const LOCALE = IDIOMA === "en" ? "en-GB" : "es-ES";

const TEXTOS = {
    es: {
        "idioma.nombre": "Español",
        "carta.titulo": "La carta",
        "carta.lead": "Cocina de temporada con producto de la Vega de Granada.",
        "carta.filtro": "Ocultar platos con alérgenos",
        "carta.filtroAyuda": "Marca lo que no puedes tomar y esconderemos los platos que lo llevan.",
        "carta.cargando": "Cargando la carta…",
        "carta.error": "No hemos podido cargar la carta. Pídela al personal, por favor.",
        "carta.legal": "Si tienes una alergia o intolerancia, avísanos al pedir: te confirmamos los ingredientes de cada plato.",
        "carta.reservar": "Reservar otro día",
        "carta.mesa": "Mesa {n}",
        "carta.ocultos": "{n} platos ocultos por los alérgenos que has marcado.",
        "carta.oculto1": "1 plato oculto por los alérgenos que has marcado.",
        "carta.contiene": "Contiene: {lista}",
        "carta.sinAlergenos": "Sin alérgenos declarados",
        "tag.recomendado": "Recomendado",
        "tag.vegetariano": "Vegetariano",
        "tag.vegano": "Vegano",
        "tag.singluten": "Sin gluten",
        "unidad.p.p.": "por persona",
        "unidad.copa": "copa",
    },
    en: {
        "idioma.nombre": "English",
        "carta.titulo": "The menu",
        "carta.lead": "Seasonal cooking with produce from the Vega de Granada.",
        "carta.filtro": "Hide dishes with allergens",
        "carta.filtroAyuda": "Tick what you can't eat and we'll hide the dishes that contain it.",
        "carta.cargando": "Loading the menu…",
        "carta.error": "We couldn't load the menu. Please ask our staff for one.",
        "carta.legal": "If you have an allergy or intolerance, please tell us when you order: we'll confirm the ingredients of each dish.",
        "carta.reservar": "Book another day",
        "carta.mesa": "Table {n}",
        "carta.ocultos": "{n} dishes hidden because of the allergens you ticked.",
        "carta.oculto1": "1 dish hidden because of the allergens you ticked.",
        "carta.contiene": "Contains: {lista}",
        "carta.sinAlergenos": "No declared allergens",
        "tag.recomendado": "Chef's pick",
        "tag.vegetariano": "Vegetarian",
        "tag.vegano": "Vegan",
        "tag.singluten": "Gluten-free",
        "unidad.p.p.": "per person",
        "unidad.copa": "glass",
    },
};

/** Texto traducido. Si falta en inglés se usa el español, y si falta del todo, la propia clave. */
function t(clave, vars = {}) {
    const texto = TEXTOS[IDIOMA]?.[clave] ?? TEXTOS.es[clave] ?? clave;
    return texto.replace(/\{(\w+)\}/g, (_, k) => (k in vars ? String(vars[k]) : `{${k}}`));
}

/** Añade textos (cada página trae los suyos: la web en javascript.js, la carta aquí). */
function anadirTextos(nuevos) {
    IDIOMAS.forEach((i) => Object.assign(TEXTOS[i], nuevos[i] || {}));
}

/** Traduce los elementos marcados con data-i18n dentro de raiz. */
function traducirPagina(raiz = document) {
    document.documentElement.lang = IDIOMA;
    raiz.querySelectorAll("[data-i18n]").forEach((el) => { el.textContent = t(el.dataset.i18n); });
    raiz.querySelectorAll("[data-i18n-html]").forEach((el) => { el.innerHTML = t(el.dataset.i18nHtml); });
    raiz.querySelectorAll("[data-i18n-attr]").forEach((el) => {
        el.dataset.i18nAttr.split(";").forEach((par) => {
            const [attr, clave] = par.split("=").map((x) => x.trim());
            if (attr && clave) el.setAttribute(attr, t(clave));
        });
    });
}

/** Botones ES · EN. Al pulsar se recarga la página en ese idioma (conservando ?mesa=…). */
function pintarSelectorIdioma(contenedor) {
    if (!contenedor) return;
    contenedor.innerHTML = IDIOMAS.map((i) =>
        `<button type="button" lang="${i}" data-lang="${i}" aria-pressed="${i === IDIOMA}" aria-label="${TEXTOS[i]["idioma.nombre"]}">${i.toUpperCase()}</button>`
    ).join("");
    contenedor.addEventListener("click", (e) => {
        const b = e.target.closest("button[data-lang]");
        if (!b || b.dataset.lang === IDIOMA) return;
        const url = new URL(location.href);
        url.searchParams.set("lang", b.dataset.lang);
        location.href = url.toString();
    });
}

/* ---------- Traducción por texto (como gettext) ----------
   En la web principal el propio texto en español es la clave:
   __("Reservar mesa") → "Book a table". Así el HTML se queda tal cual,
   legible para quien lo mantiene, y si falta una traducción se ve el
   texto en español (nunca una clave rara). El diccionario está en
   i18n-en.js. */
const EN = (typeof TRADUCCIONES_EN === "object" && TRADUCCIONES_EN) || {};
const normalizar = (t) => t.replace(/\s+/g, " ").trim();

/** Texto de la web en el idioma actual. __("Quedan {n} plazas", { n: 3 }) */
function __(es, vars = {}) {
    const texto = (IDIOMA === "en" && EN[es]) || es;
    return texto.replace(/\{(\w+)\}/g, (_, k) => (k in vars ? String(vars[k]) : `{${k}}`));
}

/**
 * Traduce el HTML ya escrito: cada texto y los atributos que lee la gente o
 * un lector de pantalla (aria-label, placeholder, alt, title). Los espacios
 * de alrededor se conservan para no pegar palabras.
 */
function traducirDocumento(raiz = document.body) {
    document.documentElement.lang = IDIOMA;
    if (IDIOMA === "es") return;
    const recorrido = document.createTreeWalker(raiz, NodeFilter.SHOW_TEXT, {
        acceptNode: (n) => (n.parentElement.closest("script, style") ? NodeFilter.FILTER_REJECT : NodeFilter.FILTER_ACCEPT),
    });
    for (let n = recorrido.nextNode(); n; n = recorrido.nextNode()) {
        const limpio = normalizar(n.nodeValue);
        if (limpio && EN[limpio]) {
            const [, antes, despues] = n.nodeValue.match(/^(\s*)[\s\S]*?(\s*)$/);
            n.nodeValue = antes + EN[limpio] + despues;
        }
    }
    raiz.querySelectorAll("[aria-label], [placeholder], [alt], [title]").forEach((el) => {
        ["aria-label", "placeholder", "alt", "title"].forEach((a) => {
            const v = el.getAttribute(a);
            if (v && EN[normalizar(v)]) el.setAttribute(a, EN[normalizar(v)]);
        });
    });
    if (EN[document.title]) document.title = EN[document.title];
    const desc = document.querySelector('meta[name="description"]');
    if (desc && EN[desc.content]) desc.content = EN[desc.content];
}

/** Precio en euros con el formato de cada idioma: 8,50 € / €8.50 */
const dinero = (n) => new Intl.NumberFormat(LOCALE, { style: "currency", currency: "EUR" }).format(Number(n));
