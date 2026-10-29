<?php
// controllers/auth.php
// Revisado 2026-10-29: valida el estado de la cuenta y renueva el ID de sesión.
require_once __DIR__ . '/../config/connection.php';

// Definir constante para evitar duplicación de literales (SonarCloud)
define('REDIRECT_HEADER', 'Location: ');

// Si no hay sesión iniciada, la arrancamos de forma segura
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// DEFINICIÓN DE LA RUTA BASE CORRECTA CON LA CARPETA PUBLIC
$base_url = "/unideportes-system/public";

// 1. MOTOR DE SALIDA (Logout)
if (isset($_GET['logout'])) {
    // Vaciar la sesión y borrar la cookie antes de destruirla
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
    header(REDIRECT_HEADER . $base_url . "/index.php");
    exit();
}

// 2. MOTOR DE ENTRADA (Login) - Se activa al enviar el formulario POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userForm = $_POST['username'] ?? '';
    $passForm = $_POST['password'] ?? '';

    // Comparación estricta: empty('0') devolvería true y un usuario "0" no podría entrar
    if ($userForm === '' || $passForm === '') {
        header(REDIRECT_HEADER . $base_url . "/index.php?msj=vacio");
        exit();
    }

    // Obtener conexión PDO
    $pdo = connection();

    try {
        // Consulta SQL con sentencia preparada. Traemos también el estado de la cuenta.
        $sql = "SELECT id, username, password, role, estado FROM usuarios WHERE username = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userForm]);
        $row = $stmt->fetch();

        if ($row) {
            $storedPassword = $row['password'];
            $loginOk = false;

            if (password_verify($passForm, $storedPassword)) {
                $loginOk = true;
            } elseif ($storedPassword === $passForm) {
                // Caso de contraseña antigua almacenada en texto plano.
                $loginOk = true;
                $newHash = password_hash($passForm, PASSWORD_DEFAULT);
                $updateStmt = $pdo->prepare('UPDATE usuarios SET password = ? WHERE id = ?');
                $updateStmt->execute([$newHash, $row['id']]);
            }

            // La contraseña es correcta pero la cuenta está desactivada.
            // Se comprueba después de validar la contraseña para no revelar
            // a un tercero si el usuario existe.
            if ($loginOk && ($row['estado'] ?? 'activo') !== 'activo') {
                header(REDIRECT_HEADER . $base_url . "/index.php?error=usuario_inactivo");
                exit();
            }

            if ($loginOk) {
                // Renovar el ID de sesión al cambiar de privilegios (evita fijación de sesión)
                session_regenerate_id(true);

                // Variables de sesión estándar para todo el sistema
                $_SESSION['user_id']     = $row['id'];
                $_SESSION['vendedor_id'] = $row['id']; // Para el módulo de ventas
                $_SESSION['username']    = $row['username'];
                $_SESSION['role']        = $row['role'];

                // Determinar destino con rutas web absolutas y limpias
                if ($row['role'] === 'admin') {
                    $destino = "/unideportes-system/views/panel_admin.php";
                } elseif ($row['role'] === 'colaborador') {
                    $destino = "/unideportes-system/views/panel_colaborador.php";
                } else {
                    $destino = "/unideportes-system/views/panel_vendedor.php";
                }

                header(REDIRECT_HEADER . $destino);
                exit();
            }
        }

        // Credenciales incorrectas - Redirecciona al index dentro de public
        header(REDIRECT_HEADER . $base_url . "/index.php?error=datos_incorrectos");
        exit();

    } catch (PDOException $e) {
        error_log('Error en login: ' . $e->getMessage());
        header(REDIRECT_HEADER . $base_url . "/index.php?error=error_interno");
        exit();
    }
} else {
    // Si alguien intenta entrar a este archivo por URL sin POST ni GET[logout]
    header(REDIRECT_HEADER . $base_url . "/index.php");
    exit();
}
