CREATE DATABASE IF NOT EXISTS sirpro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sirpro;

DROP TABLE IF EXISTS venta_detalles;
DROP TABLE IF EXISTS ventas;
DROP TABLE IF EXISTS productos;
DROP TABLE IF EXISTS categorias;
DROP TABLE IF EXISTS usuarios;

CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    usuario VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    rol ENUM('admin','cajero') NOT NULL DEFAULT 'cajero',
    activo TINYINT(1) NOT NULL DEFAULT 1
);

CREATE TABLE categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE
);

CREATE TABLE productos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sku VARCHAR(50) NOT NULL UNIQUE,
    nombre VARCHAR(150) NOT NULL,
    categoria_id INT NOT NULL,
    tipo ENUM('producto','servicio') NOT NULL DEFAULT 'producto',
    descripcion TEXT,
    precio DECIMAL(10,2) NOT NULL DEFAULT 0,
    precio_oferta DECIMAL(10,2) NULL,
    stock INT NOT NULL DEFAULT 0,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id)
);

CREATE TABLE ventas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    folio VARCHAR(20) NOT NULL UNIQUE,
    usuario_id INT NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    pagado DECIMAL(10,2) NOT NULL,
    cambio DECIMAL(10,2) NOT NULL DEFAULT 0,
    metodo_pago ENUM('efectivo','tarjeta','transferencia') NOT NULL DEFAULT 'efectivo',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

CREATE TABLE venta_detalles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venta_id INT NOT NULL,
    producto_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,
    cantidad INT NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (venta_id) REFERENCES ventas(id) ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES productos(id)
);

INSERT INTO usuarios (nombre, usuario, password, rol) VALUES
('Administrador', 'admin', '$2y$10$5ukJmO1EZwnknX3tVzaeiOqDkSL6K8NcI5oInSsL21UtYuCpIZzzm', 'admin');

INSERT INTO categorias (nombre) VALUES
('Laptops'),
('Computadoras de Escritorio'),
('Impresoras'),
('Discos SSD'),
('Perifericos'),
('Memorias USB'),
('Servicios');

INSERT INTO productos (sku, nombre, categoria_id, tipo, descripcion, precio, precio_oferta, stock) VALUES
('LAP-001', 'Laptop Core i5 15.6" 8GB 512GB', 1, 'producto', 'Intel Core i5, 8GB RAM, SSD 512GB', 12500.00, 11900.00, 5),
('LAP-002', 'Laptop Ryzen 7 14" 16GB 1TB', 1, 'producto', 'AMD Ryzen 7, 16GB RAM, SSD 1TB', 18900.00, NULL, 3),
('PC-001', 'PC Escritorio Torre Core i3 8GB 1TB', 2, 'producto', 'Torre ensamblada, Intel Core i3, 8GB RAM, HDD 1TB', 8900.00, NULL, 4),
('PC-002', 'All-in-One 23.8" Core i5 16GB 512GB', 2, 'producto', 'All-in-One, Core i5, 16GB RAM, SSD 512GB', 14500.00, 13900.00, 2),
('IMP-001', 'Impresora Laser Monocromatica', 3, 'producto', 'Laser monocromatica, USB y red', 3200.00, NULL, 6),
('IMP-002', 'Multifuncional Tinta Continua', 3, 'producto', 'Multifuncional con tanques de tinta, WiFi', 4800.00, 4500.00, 4),
('SSD-001', 'SSD SATA 480GB', 4, 'producto', 'Interfaz SATA III, lectura 550MB/s', 850.00, NULL, 15),
('SSD-002', 'SSD NVMe M.2 1TB', 4, 'producto', 'Interfaz NVMe PCIe 4.0, lectura 7000MB/s', 1990.00, 1790.00, 8),
('PER-001', 'Mouse Inalambrico Ergonomico', 5, 'producto', 'Conectividad 2.4GHz y Bluetooth', 450.00, NULL, 20),
('PER-002', 'Teclado Gaming RGB', 5, 'producto', 'Teclado mecanico USB retroiluminado', 990.00, 890.00, 10),
('USB-001', 'Memoria USB 3.2 64GB', 6, 'producto', 'USB 3.2 Gen 1, tipo A', 180.00, NULL, 30),
('USB-002', 'Memoria USB Tipo C 128GB', 6, 'producto', 'USB tipo C, transferencia hasta 400MB/s', 320.00, NULL, 25),
('SRV-001', 'Mantenimiento Preventivo de Impresora', 7, 'servicio', 'Limpieza, diagnostico y ajuste', 350.00, NULL, 0),
('SRV-002', 'Cambio de Pantalla para Laptop', 7, 'servicio', 'Mano de obra, pantalla no incluida', 500.00, NULL, 0),
('SRV-003', 'Instalacion Paquete Office Completo', 7, 'servicio', 'Word, Excel, PowerPoint y mas, presencial o remota', 300.00, NULL, 0);
