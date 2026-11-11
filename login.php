<?php
require __DIR__ . '/includes/bootstrap.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = db()->prepare('SELECT * FROM usuarios WHERE usuario = ? AND activo = 1');
    $stmt->execute([$usuario]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['usuario_id'] = $user['id'];
        $_SESSION['nombre'] = $user['nombre'];
        $_SESSION['rol'] = $user['rol'];
        header('Location: pos.php');
        exit;
    }
    $error = 'Usuario o contraseña incorrectos.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Ingresar - Sirpro</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-body">
<form method="post" class="login-card">
    <img src="assets/logo.png" alt="Sirpro" class="login-logo" onerror="this.onerror=function(){this.style.display='none'};this.src='assets/logo.svg'">
    <h1>Punto de venta</h1>
<?php if ($error): ?>
    <p class="alert error"><?= e($error) ?></p>
<?php endif; ?>
    <label>Usuario
        <input type="text" name="usuario" required autofocus>
    </label>
    <label>Contraseña
        <input type="password" name="password" required>
    </label>
    <button type="submit" class="btn primary full">Ingresar</button>
</form>
</body>
</html>
