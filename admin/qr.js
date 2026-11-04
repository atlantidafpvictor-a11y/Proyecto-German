/* Dibuja un QR (SVG) por mesa con la librería qrcode-generator (admin/vendor). */
"use strict";

(function () {
    const base = document.getElementById("qr-base");
    const que = document.getElementById("qr-que");
    const grid = document.getElementById("qr-grid");
    const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));

    // Dirección de la web = la del panel sin "/admin/…"
    base.value = location.href.replace(/\/admin\/.*$/, "/");

    /** SVG de un QR: un cuadradito por módulo oscuro, con 4 módulos de margen (lo que pide el estándar). */
    function qrSvg(texto) {
        const qr = qrcode(0, "M"); // 0 = tamaño automático, M = corrige hasta un 15 % de daño
        qr.addData(texto);
        qr.make();
        const n = qr.getModuleCount(), m = 4;
        let d = "";
        for (let fila = 0; fila < n; fila++) {
            for (let col = 0; col < n; col++) {
                if (qr.isDark(fila, col)) d += `M${col + m} ${fila + m}h1v1h-1z`;
            }
        }
        return `<svg viewBox="0 0 ${n + 2 * m} ${n + 2 * m}" role="img" aria-label="Código QR: ${esc(texto)}" shape-rendering="crispEdges">` +
               `<rect width="100%" height="100%" fill="#fff"/><path d="${d}" fill="#11160E"/></svg>`;
    }

    function pintar() {
        let raiz = base.value.trim();
        if (!/^https?:\/\//.test(raiz)) return;
        if (!raiz.endsWith("/")) raiz += "/";
        grid.querySelectorAll(".qr-card").forEach((card) => {
            const mesa = card.dataset.mesa;
            const url = raiz + "carta.html" + (mesa ? "?mesa=" + mesa : "");
            card.querySelector(".qr-code").innerHTML = qrSvg(url);
            card.querySelector(".qr-url").textContent = url.replace(/^https?:\/\//, "");
            card.hidden = que.value === "entrada" && mesa !== "";
        });
    }

    base.addEventListener("input", pintar);
    que.addEventListener("change", pintar);
    document.getElementById("qr-imprimir").addEventListener("click", () => window.print());
    pintar();
})();
