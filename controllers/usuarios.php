<?php
// controllers/usuarios.php
// Controlador para gestionar usuarios (activar/desactivar/eliminar)
// Revisado 2026-10-29: consultas de "último administrador" con sentencia preparada
// y filtrando por estado activo.
require_once __DIR__ . '/../config/bootstrap.php';

// Solo admin puede acceder
require_login(['admin']);

$pdo = app();

// Validar que venga una acción
$accion = $_GET['action'] ?? '';
$id = intval($_GET['id'] ?? 0);

if (empty($accion) || $id <= 0) {
    header("Location: ../views/admin_usuarios.php?error=datos_invalidos");
    exit();
}

// ID del usuario logueado (para evitar que se modifique a sí mismo)
$usuarioActual = $_SESSION['user_id'] ?? 0;

try {
    // Verificar que el usuario existe
    $stmt = $pdo->prepare("SELECT id, username, role, estado FROM usuarios WHERE id = ?");
    $stmt->execute([$id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        header("Location: ../views/admin_usuarios.php?error=usuario_no_existe");
        exit();
    }

    // ============================================
    // ACCIÓN 1: TOGGLE ESTADO (Activar/Desactivar)
    // ============================================
    if ($accion === 'toggle_estado') {
        $nuevoEstado = $_GET['estado'] ?? '';

        // Validar estado
        if (!in_array($nuevoEstado, ['activo', 'inactivo'])) {
            header("Location: ../views/admin_usuarios.php?error=estado_invalido");
            exit();
        }

        // No puede desactivarse a sí mismo
        if ($id == $usuarioActual && $nuevoEstado === 'inactivo') {
            header("Location: ../views/admin_usuarios.php?error=no_puedes_desactivarte");
            exit();
        }

        // Si va a desactivar, verificar que no sea el último admin activo
        if ($nuevoEstado === 'inactivo' && $usuario['role'] === 'admin') {
            $stmtAdmins = $pdo->prepare("
                SELECT COUNT(*) FROM usuarios
                WHERE role = 'admin' AND estado = 'activo' AND id != ?
            ");
            $stmtAdmins->execute([$id]);
            $adminsActivos = $stmtAdmins->fetchColumn();

            if ($adminsActivos == 0) {
                header("Location: ../views/admin_usuarios.php?error=ultimo_admin");
                exit();
            }
        }

        // Actualizar estado
        $stmtUpdate = $pdo->prepare("UPDATE usuarios SET estado = ? WHERE id = ?");
        $stmtUpdate->execute([$nuevoEstado, $id]);

        header("Location: ../views/admin_usuarios.php?success=estado_actualizado");
        exit();
    }

    // ============================================
    // ACCIÓN 2: ELIMINAR USUARIO
    // ============================================
    if ($accion === 'eliminar') {
        // No puede eliminarse a sí mismo
        if ($id == $usuarioActual) {
            header("Location: ../views/admin_usuarios.php?error=no_puedes_eliminarte");
            exit();
        }

        // Si es admin, verificar que no sea el último admin ACTIVO
        if ($usuario['role'] === 'admin') {
            $stmtAdmins = $pdo->prepare("
                SELECT COUNT(*) FROM usuarios
                WHERE role = 'admin' AND estado = 'activo' AND id != ?
            ");
            $stmtAdmins->execute([$id]);
            $totalAdmins = $stmtAdmins->fetchColumn();

            if ($totalAdmins == 0) {
                header("Location: ../views/admin_usuarios.php?error=ultimo_admin");
                exit();
            }
        }

        // Verificar si tiene registros asociados (ventas, pedidos)
        $stmtVentas = $pdo->prepare("SELECT COUNT(*) FROM ventas WHERE vendedor_id = ?");
        $stmtVentas->execute([$id]);
        $tieneVentas = $stmtVentas->fetchColumn() > 0;

        if ($tieneVentas) {
            // En lugar de eliminar, desactivar (soft delete)
            $stmtUpdate = $pdo->prepare("UPDATE usuarios SET estado = 'inactivo' WHERE id = ?");
            $stmtUpdate->execute([$id]);
            header("Location: ../views/admin_usuarios.php?success=usuario_desactivado_historial");
            exit();
        }

        // Si no tiene historial, eliminar definitivamente
        $stmtDelete = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmtDelete->execute([$id]);

        header("Location: ../views/admin_usuarios.php?success=usuario_eliminado");
        exit();
    }

    // Acción no válida
    header("Location: ../views/admin_usuarios.php?error=accion_invalida");
    exit();

} catch (Exception $e) {
    // Log del error
    error_log("Error en controlador usuarios: " . $e->getMessage());
    header("Location: ../views/admin_usuarios.php?error=error_base_datos");
    exit();
}
