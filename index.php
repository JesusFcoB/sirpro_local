<?php
require __DIR__ . '/includes/bootstrap.php';

header('Location: ' . (empty($_SESSION['usuario_id']) ? 'login.php' : 'dashboard.php'));
exit;
