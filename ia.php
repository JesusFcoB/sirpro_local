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
                <textarea id="ia-input" rows="2" maxlength="800" placeholder="Escribís tu pregunta…" aria-label="Mensaje para el asistente"></textarea>
                <div class="ia-acciones-envio">
                    <button id="ia-enviar" class="btn primary" type="submit">Enviar</button>
                    <button id="ia-cancelar" class="btn secondary" type="button" hidden>Detener</button>
                </div>
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
            'Resumís las ventas de los últimos 30 días',
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
            'Describís un SSD NVMe de 1TB para venderlo',
            'Escribís un aviso por stock bajo',
            'Generás un título para una promo de laptops',
            'Redactás una descripción para un servicio de mantenimiento'
        ]
    };
    var bienvenidas = {
        analisis: 'Hola. Puedo analizar tus ventas e inventario con los datos actuales de Sirpro. Probás una de las preguntas rápidas.',
        ayuda: 'Hola. Conozco el manual de Sirpro: ventas, productos, stock, reportes y más. ¿Qué necesitás hacer?',
        textos: 'Hola. Puedo redactar descripciones, avisos y promociones usando tu catálogo real. Me decís qué necesitás.'
    };

    var estadoEl = document.getElementById('ia-estado');
    var avisoEl = document.getElementById('ia-aviso');
    var modeloEl = document.getElementById('ia-modelo');
    var chat = document.getElementById('ia-chat');
    var bienvenidaEl = document.getElementById('ia-bienvenida');
    var input = document.getElementById('ia-input');
    var form = document.getElementById('ia-form');
    var enviarBtn = document.getElementById('ia-enviar');
    var cancelarBtn = document.getElementById('ia-cancelar');
    var chips = document.getElementById('ia-chips');
    var limpiarBtn = document.getElementById('ia-limpiar');
    var botonesModo = Array.prototype.slice.call(document.querySelectorAll('.ia-modo'));

    var modo = puedeAnalizar ? 'analisis' : 'ayuda';
    var historial = [];
    var ocupado = false;
    var controlador = null;
    var fueCancelado = false;

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
        cancelar();
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
            mostrarEstado({ configurada: false, mensaje: 'No se puede consultar el estado del asistente.' });
        }
    }

    function agregarAcciones(burbuja, texto, mensaje) {
        var contenedor = burbuja.parentNode;
        var barra = document.createElement('div');
        barra.className = 'ia-acciones';

        var copiar = document.createElement('button');
        copiar.type = 'button';
        copiar.className = 'btn link mini';
        copiar.textContent = '📋 Copiar';
        copiar.addEventListener('click', function () {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(texto).then(function () {
                    copiar.textContent = '✓ Copiado';
                    setTimeout(function () { copiar.textContent = '📋 Copiar'; }, 1500);
                });
            }
        });

        var regenerar = document.createElement('button');
        regenerar.type = 'button';
        regenerar.className = 'btn link mini';
        regenerar.textContent = '🔄 Regenerar';
        regenerar.addEventListener('click', function () {
            if (ocupado || !mensaje) return;
            var ultimo = historial[historial.length - 1];
            if (ultimo && ultimo.rol === 'asistente') historial.pop();
            var anteultimo = historial[historial.length - 1];
            if (anteultimo && anteultimo.rol === 'usuario') historial.pop();
            enviarMensaje(mensaje);
        });

        barra.appendChild(copiar);
        barra.appendChild(regenerar);
        contenedor.appendChild(barra);
    }

    function terminar() {
        ocupado = false;
        controlador = null;
        enviarBtn.disabled = false;
        enviarBtn.textContent = 'Enviar';
        cancelarBtn.hidden = true;
        input.focus();
    }

    function cancelar() {
        if (!ocupado || !controlador) return;
        fueCancelado = true;
        controlador.abort();
    }

    async function enviarMensaje(mensaje) {
        if (ocupado || !mensaje) return;
        ocupado = true;
        fueCancelado = false;
        controlador = new AbortController();
        enviarBtn.disabled = true;
        enviarBtn.textContent = 'Pensando…';
        cancelarBtn.hidden = false;
        input.value = '';
        agregarMensaje('usuario', mensaje);
        var burbuja = agregarMensaje('asistente', 'Pensando…');
        burbuja.classList.add('ia-typing');
        var texto = '';

        try {
            var r = await fetch('api/ia.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                signal: controlador.signal,
                body: JSON.stringify({
                    csrf: IA_CSRF,
                    mensaje: mensaje,
                    modo: modo,
                    historial: historial,
                    stream: true
                })
            });

            if (!r.ok) {
                var datosError = await r.json().catch(function () { return {}; });
                throw new Error(datosError.error || 'No se puede obtener una respuesta.');
            }

            var lector = r.body.getReader();
            var decodificador = new TextDecoder('utf-8');
            var pendiente = '';
            while (true) {
                var parte = await lector.read();
                if (parte.done) break;
                pendiente += decodificador.decode(parte.value, { stream: true });
                var bloques = pendiente.split('\n\n');
                pendiente = bloques.pop();
                for (var i = 0; i < bloques.length; i++) {
                    var linea = bloques[i].trim();
                    if (!linea || linea.indexOf('data:') !== 0) continue;
                    var evento;
                    try { evento = JSON.parse(linea.slice(5).trim()); } catch (e) { continue; }
                    if (evento.tipo === 'delta') {
                        texto += evento.texto || '';
                        burbuja.classList.remove('ia-typing');
                        burbuja.innerHTML = formatear(texto);
                        chat.scrollTop = chat.scrollHeight;
                    } else if (evento.tipo === 'reset') {
                        texto = '';
                        burbuja.innerHTML = '';
                    } else if (evento.tipo === 'error') {
                        throw new Error(evento.error || 'Error al consultar el asistente.');
                    } else if (evento.tipo === 'fin') {
                        texto = evento.respuesta || texto;
                        burbuja.innerHTML = formatear(texto);
                    }
                }
            }

            if (fueCancelado && texto === '') texto = 'Generación detenida.';
            burbuja.classList.remove('ia-typing');
            burbuja.innerHTML = formatear(texto || 'El asistente no devuelve texto.');
            historial.push({ rol: 'usuario', contenido: mensaje });
            historial.push({ rol: 'asistente', contenido: texto });
            if (historial.length > 8) historial = historial.slice(-8);
            agregarAcciones(burbuja, texto, mensaje);
        } catch (e) {
            burbuja.classList.remove('ia-typing');
            var mensajeError = (e && e.name === 'AbortError') ? 'Generación detenida.' : (e.message || 'Error al consultar el asistente.');
            burbuja.innerHTML = '<span class="ia-error">' + escapar(mensajeError) + '</span>';
        } finally {
            terminar();
        }
    }

    function enviar(evento) {
        evento.preventDefault();
        enviarMensaje(input.value.trim());
    }

    botonesModo.forEach(function (b) {
        b.addEventListener('click', function () { seleccionarModo(b.getAttribute('data-modo')); });
    });

    form.addEventListener('submit', enviar);
    cancelarBtn.addEventListener('click', cancelar);
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            form.requestSubmit();
        }
    });

    limpiarBtn.addEventListener('click', function () {
        cancelar();
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
