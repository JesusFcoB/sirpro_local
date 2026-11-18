<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/ia.php';
requerirLogin();

$esAdmin = ($_SESSION['rol'] ?? '') === 'admin';
$puedeAnalizar = $esAdmin && iaIncluirDatos();
render('Asistente IA', '');
?>
<div class="ia-page">
    <div class="ia-cabecera">
        <div>
            <h1>Asistente IA</h1>
            <p class="ia-sub">Disponible para todos los usuarios de Sirpro. Configurado una sola vez en el servidor.</p>
        </div>
        <div id="ia-estado" class="ia-estado"><span class="punto"></span> Verificando…</div>
    </div>

    <div id="ia-aviso" class="alert warning" hidden></div>

    <div class="ia-layout">
        <aside class="card ia-panel">
            <h2>Modo</h2>
            <div class="ia-modos">
                <?php if ($puedeAnalizar): ?>
                    <button class="ia-modo activo" type="button" data-modo="analisis">📊 Analizar negocio</button>
                <?php endif; ?>
                <button class="ia-modo <?php echo $puedeAnalizar ? '' : 'activo'; ?>" type="button" data-modo="ayuda">❓ Ayuda del sistema</button>
                <button class="ia-modo" type="button" data-modo="textos">✍️ Redactar textos</button>
            </div>

            <h2>Preguntas rápidas</h2>
            <div id="ia-chips" class="ia-chips"></div>

            <button id="ia-limpiar" class="btn secondary full" type="button">Limpiar conversación</button>
            <p class="ia-nota">Servicio: <strong id="ia-modelo">—</strong></p>
        </aside>

        <section class="card ia-chat-card">
            <div id="ia-chat" class="ia-chat" aria-live="polite">
                <div class="ia-msj asistente">
                    <span class="ia-quien">Sirpro IA</span>
                    <div class="ia-burbuja" id="ia-bienvenida">Hola. ¿En qué te puedo ayudar hoy?</div>
                </div>
            </div>
            <form id="ia-form" class="ia-form">
                <textarea id="ia-input" rows="2" maxlength="800" placeholder="Escribí tu pregunta…" aria-label="Mensaje para el asistente"></textarea>
                <button id="ia-enviar" class="btn primary" type="submit">Enviar</button>
            </form>
            <p class="ia-ayuda">Enter para enviar · Mayús + Enter para salto de línea</p>
        </section>
    </div>
</div>

<script>
(function () {
    'use strict';

    var IA_CSRF = <?php echo json_encode(csrfToken(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var puedeAnalizar = <?php echo $puedeAnalizar ? 'true' : 'false'; ?>;
    var sugerencias = {
        analisis: [
            '¿Qué productos debo reponer primero?',
            'Resumí las ventas de los últimos 30 días',
            '¿Cuáles son mis productos más vendidos?',
            '¿Cuánto vale mi inventario?'
        ],
        ayuda: [
            '¿Cómo hago una venta?',
            '¿Cómo agrego un producto nuevo?',
            '¿Cómo descargo un reporte en Excel?',
            '¿Para qué sirve el stock mínimo?'
        ],
        textos: [
            'Describí un SSD NVMe de 1TB para venderlo',
            'Escribí un aviso por stock bajo',
            'Generá un título para una promo de laptops',
            'Redactá una descripción para un servicio de mantenimiento'
        ]
    };
    var bienvenidas = {
        analisis: 'Hola. Puedo analizar tus ventas e inventario con los datos actuales de Sirpro. Probá una de las preguntas rápidas.',
        ayuda: 'Hola. Conozco el manual de Sirpro: ventas, productos, stock, reportes y más. ¿Qué necesitás hacer?',
        textos: 'Hola. Puedo redactar descripciones, avisos y promociones usando tu catálogo real. Decime qué necesitás.'
    };

    var estadoEl = document.getElementById('ia-estado');
    var avisoEl = document.getElementById('ia-aviso');
    var modeloEl = document.getElementById('ia-modelo');
    var chat = document.getElementById('ia-chat');
    var bienvenidaEl = document.getElementById('ia-bienvenida');
    var input = document.getElementById('ia-input');
    var form = document.getElementById('ia-form');
    var enviarBtn = document.getElementById('ia-enviar');
    var chips = document.getElementById('ia-chips');
    var limpiarBtn = document.getElementById('ia-limpiar');
    var botonesModo = Array.prototype.slice.call(document.querySelectorAll('.ia-modo'));

    var modo = puedeAnalizar ? 'analisis' : 'ayuda';
    var historial = [];
    var ocupado = false;

    function escapar(texto) {
        return String(texto)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatear(texto) {
        return escapar(texto)
            .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
            .replace(/\n/g, '<br>');
    }

    function agregarMensaje(quien, texto) {
        var div = document.createElement('div');
        div.className = 'ia-msj ' + quien;
        var etiqueta = document.createElement('span');
        etiqueta.className = 'ia-quien';
        etiqueta.textContent = quien === 'usuario' ? 'Vos' : 'Sirpro IA';
        var burbuja = document.createElement('div');
        burbuja.className = 'ia-burbuja';
        burbuja.innerHTML = formatear(texto);
        div.appendChild(etiqueta);
        div.appendChild(burbuja);
        chat.appendChild(div);
        chat.scrollTop = chat.scrollHeight;
        return burbuja;
    }

    function pintarChips() {
        chips.innerHTML = '';
        sugerencias[modo].forEach(function (texto) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'btn link ia-chip';
            b.textContent = texto;
            b.addEventListener('click', function () {
                input.value = texto;
                input.focus();
            });
            chips.appendChild(b);
        });
    }

    function seleccionarModo(nuevo) {
        modo = nuevo;
        historial = [];
        botonesModo.forEach(function (b) {
            b.classList.toggle('activo', b.getAttribute('data-modo') === modo);
        });
        bienvenidaEl.innerHTML = formatear(bienvenidas[modo]);
        pintarChips();
        input.focus();
    }

    function mostrarEstado(datos) {
        var ok = !!(datos && datos.configurada);
        estadoEl.className = 'ia-estado ' + (ok ? 'ok' : 'error');
        estadoEl.innerHTML = '<span class="punto"></span> ' + escapar(ok ? 'IA lista' : 'IA sin configurar');
        if (datos && datos.proveedor && datos.modelo) {
            modeloEl.textContent = datos.proveedor + ' · ' + (datos.modelos && datos.modelos.length > 1
                ? datos.modelos.join(' → ')
                : datos.modelo);
        }
        if (ok) {
            avisoEl.hidden = true;
        } else {
            avisoEl.textContent = (datos && datos.mensaje) || 'El asistente no está configurado.';
            avisoEl.hidden = false;
        }
    }

    async function verificarEstado() {
        try {
            var r = await fetch('api/ia.php?estado=1', { cache: 'no-store' });
            mostrarEstado(await r.json());
        } catch (e) {
            mostrarEstado({ configurada: false, mensaje: 'No se pudo consultar el estado del asistente.' });
        }
    }

    async function enviar(evento) {
        evento.preventDefault();
        if (ocupado) return;
        var mensaje = input.value.trim();
        if (!mensaje) return;

        ocupado = true;
        enviarBtn.disabled = true;
        enviarBtn.textContent = 'Pensando…';
        input.value = '';
        agregarMensaje('usuario', mensaje);
        var burbuja = agregarMensaje('asistente', 'Pensando…');
        burbuja.classList.add('ia-typing');

        try {
            var r = await fetch('api/ia.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    csrf: IA_CSRF,
                    mensaje: mensaje,
                    modo: modo,
                    historial: historial
                })
            });
            var datos = await r.json();
            if (!r.ok || datos.error) {
                throw new Error(datos.error || 'No se pudo obtener una respuesta.');
            }
            burbuja.classList.remove('ia-typing');
            burbuja.innerHTML = formatear(datos.respuesta);
            historial.push({ rol: 'usuario', contenido: mensaje });
            historial.push({ rol: 'asistente', contenido: datos.respuesta });
            if (historial.length > 8) historial = historial.slice(-8);
        } catch (e) {
            burbuja.classList.remove('ia-typing');
            burbuja.innerHTML = '<span class="ia-error">' + escapar(e.message || 'Error al consultar el asistente.') + '</span>';
        } finally {
            ocupado = false;
            enviarBtn.disabled = false;
            enviarBtn.textContent = 'Enviar';
            input.focus();
        }
    }

    botonesModo.forEach(function (b) {
        b.addEventListener('click', function () { seleccionarModo(b.getAttribute('data-modo')); });
    });

    form.addEventListener('submit', enviar);
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            form.requestSubmit();
        }
    });

    limpiarBtn.addEventListener('click', function () {
        historial = [];
        Array.prototype.slice.call(chat.querySelectorAll('.ia-msj:not(:first-child)')).forEach(function (n) { n.remove(); });
        bienvenidaEl.innerHTML = formatear(bienvenidas[modo]);
        input.focus();
    });

    pintarChips();
    verificarEstado();
})();
</script>
<?php renderFin(); ?>
