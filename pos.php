<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/imagenes.php';
requerirLogin();

$categorias = db()->query('SELECT id, nombre FROM categorias ORDER BY nombre')->fetchAll();
$productos = db()->query(
    'SELECT p.id, p.sku, p.nombre, p.tipo, p.categoria_id, p.precio, p.precio_oferta, p.stock, p.imagen
     FROM productos p WHERE p.activo = 1 ORDER BY p.nombre'
)->fetchAll();

render('Punto de venta', '');
?>
<div class="pos">
    <section class="catalogo">
        <div class="toolbar">
            <input type="search" id="buscar" placeholder="Buscar por nombre o SKU..." autofocus>
            <select id="filtro-categoria">
                <option value="">Todas las categorías</option>
                <?php foreach ($categorias as $c): ?>
                    <option value="<?= (int) $c['id'] ?>"><?= e($c['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="grid" id="grid-productos">
            <?php foreach ($productos as $p):
                $precio = $p['precio_oferta'] !== null ? (float) $p['precio_oferta'] : (float) $p['precio'];
                $agotado = $p['tipo'] === 'producto' && (int) $p['stock'] <= 0;
            ?>
                <button type="button"
                        class="tarjeta<?= $agotado ? ' agotado' : '' ?>"
                        data-id="<?= (int) $p['id'] ?>"
                        data-categoria="<?= (int) $p['categoria_id'] ?>"
                        data-texto="<?= e(mb_strtolower($p['nombre'] . ' ' . $p['sku'])) ?>"
                        data-precio="<?= $precio ?>"
                        data-nombre="<?= e($p['nombre']) ?>"
                        data-tipo="<?= e($p['tipo']) ?>"
                        data-stock="<?= (int) $p['stock'] ?>"
                        <?= $agotado ? 'disabled' : '' ?>>
                    <?php if (!empty($p['imagen'])): ?>
                        <img src="<?= e(rutaImagen($p['imagen'])) ?>" alt="" class="tarjeta-img">
                    <?php endif; ?>
                    <span class="sku"><?= e($p['sku']) ?></span>
                    <span class="nombre"><?= e($p['nombre']) ?></span>
                    <span class="precio">
                        <?php if ($p['precio_oferta'] !== null): ?>
                            <s><?= moneda((float) $p['precio']) ?></s>
                        <?php endif; ?>
                        <?= moneda($precio) ?>
                    </span>
                    <span class="stock"><?= $p['tipo'] === 'servicio' ? 'Servicio' : ($agotado ? 'Agotado' : 'Stock: ' . (int) $p['stock']) ?></span>
                </button>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="carrito">
        <h2>Venta actual</h2>
        <table>
            <thead>
                <tr><th>Producto</th><th>Cant.</th><th>Importe</th><th></th></tr>
            </thead>
            <tbody id="items"></tbody>
        </table>
        <p class="vacio" id="vacio">Agrega productos a la venta.</p>

        <div class="totales">
            <div><span>Subtotal</span><strong id="subtotal">$0.00</strong></div>
            <div class="total"><span>Total</span><strong id="total">$0.00</strong></div>
        </div>

        <label>Método de pago
            <select id="metodo">
                <option value="efectivo">Efectivo</option>
                <option value="tarjeta">Tarjeta</option>
                <option value="transferencia">Transferencia</option>
            </select>
        </label>
        <label>Monto recibido
            <input type="number" id="recibido" min="0" step="0.01">
        </label>
        <p>Cambio: <strong id="cambio">$0.00</strong></p>

        <div class="acciones">
            <button type="button" class="btn secondary" id="vaciar">Vaciar</button>
            <button type="button" class="btn success" id="cobrar">Cobrar</button>
        </div>
        <p class="alert error" id="mensaje" hidden></p>
    </section>
</div>

<script>
const CSRF = <?= json_encode(csrfToken()) ?>;
const carrito = new Map();
const $ = (sel) => document.querySelector(sel);
const fmt = (n) => '$' + Number(n).toFixed(2);

function renderCarrito() {
    const tbody = $('#items');
    tbody.innerHTML = '';
    let total = 0;

    carrito.forEach((item, id) => {
        const importe = item.precio * item.cantidad;
        total += importe;
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${item.nombre}<small>${fmt(item.precio)} c/u</small></td>
            <td><input type="number" min="1" value="${item.cantidad}" data-id="${id}" class="cant"></td>
            <td>${fmt(importe)}</td>
            <td><button type="button" class="quitar" data-id="${id}">✕</button></td>`;
        tbody.appendChild(tr);
    });

    $('#vacio').hidden = carrito.size > 0;
    $('#subtotal').textContent = fmt(total);
    $('#total').textContent = fmt(total);
    calcularCambio();
}

function calcularCambio() {
    const total = [...carrito.values()].reduce((s, i) => s + i.precio * i.cantidad, 0);
    const recibido = parseFloat($('#recibido').value) || 0;
    $('#cambio').textContent = fmt(Math.max(0, recibido - total));
}

function agregar(btn) {
    const id = btn.dataset.id;
    const stock = Number(btn.dataset.stock);
    const tipo = btn.dataset.tipo;
    const actual = carrito.get(id)?.cantidad ?? 0;

    if (tipo === 'producto' && actual + 1 > stock) {
        mostrarError('No hay suficiente stock de ' + btn.dataset.nombre);
        return;
    }
    if (carrito.has(id)) {
        carrito.get(id).cantidad++;
    } else {
        carrito.set(id, { nombre: btn.dataset.nombre, precio: Number(btn.dataset.precio), cantidad: 1, stock, tipo });
    }
    ocultarError();
    renderCarrito();
}

function mostrarError(texto) {
    const m = $('#mensaje');
    m.textContent = texto;
    m.hidden = false;
}

function ocultarError() {
    $('#mensaje').hidden = true;
}

document.querySelectorAll('#grid-productos .tarjeta').forEach(btn => {
    btn.addEventListener('click', () => agregar(btn));
});

$('#buscar').addEventListener('input', filtrar);
$('#filtro-categoria').addEventListener('change', filtrar);

function filtrar() {
    const texto = $('#buscar').value.trim().toLowerCase();
    const cat = $('#filtro-categoria').value;
    document.querySelectorAll('#grid-productos .tarjeta').forEach(btn => {
        const okTexto = !texto || btn.dataset.texto.includes(texto);
        const okCat = !cat || btn.dataset.categoria === cat;
        btn.hidden = !(okTexto && okCat);
    });
}

$('#items').addEventListener('change', (ev) => {
    if (!ev.target.classList.contains('cant')) return;
    const id = ev.target.dataset.id;
    const item = carrito.get(id);
    let cant = parseInt(ev.target.value, 10);
    if (isNaN(cant) || cant < 1) cant = 1;
    if (item.tipo === 'producto' && cant > item.stock) {
        cant = item.stock;
        mostrarError('Stock máximo disponible: ' + item.stock);
    } else {
        ocultarError();
    }
    item.cantidad = cant;
    renderCarrito();
});

$('#items').addEventListener('click', (ev) => {
    const btn = ev.target.closest('.quitar');
    if (!btn) return;
    carrito.delete(btn.dataset.id);
    renderCarrito();
});

$('#recibido').addEventListener('input', calcularCambio);

$('#vaciar').addEventListener('click', () => {
    carrito.clear();
    $('#recibido').value = '';
    ocultarError();
    renderCarrito();
});

$('#cobrar').addEventListener('click', async () => {
    if (carrito.size === 0) {
        mostrarError('La venta está vacía.');
        return;
    }
    const total = [...carrito.values()].reduce((s, i) => s + i.precio * i.cantidad, 0);
    const recibido = parseFloat($('#recibido').value) || 0;
    if ($('#metodo').value === 'efectivo' && recibido < total) {
        mostrarError('El monto recibido es menor al total.');
        return;
    }

    const payload = {
        csrf: CSRF,
        metodo_pago: $('#metodo').value,
        pagado: $('#metodo').value === 'efectivo' ? recibido : total,
        items: [...carrito.entries()].map(([id, i]) => ({ producto_id: Number(id), cantidad: i.cantidad })),
    };

    $('#cobrar').disabled = true;
    try {
        const res = await fetch('api/venta.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const data = await res.json();
        if (!res.ok || !data.ok) {
            mostrarError(data.error || 'No se pudo registrar la venta.');
            return;
        }
        window.location.href = 'ticket.php?id=' + data.venta_id;
    } catch (err) {
        mostrarError('Error de conexión con el servidor.');
    } finally {
        $('#cobrar').disabled = false;
    }
});

renderCarrito();
</script>
<?php renderFin(); ?>
