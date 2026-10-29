<?php
// config/connection.php
// Revisado 2026-10-29: no expone el error de conexión al navegador y fuerza
// sentencias preparadas reales en el servidor de base de datos.

function connection() {
    $host = "127.0.0.1";
    $dbname = "unideportes";
    $username = "root";
    $password = "";

    try {
        // Usamos PDO
        $dsn = "mysql:host=" . $host . ";dbname=" . $dbname . ";charset=utf8mb4";

        $pdo = new PDO($dsn, $username, $password);

        // Configuración de PDO
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Que las sentencias preparadas se ejecuten de verdad en MySQL
        // y no se simulen en PHP (evita ciertos ataques de inyección vía charset).
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

        return $pdo;
    } catch (PDOException $e) {
        // El detalle va al log del servidor, no al navegador.
        error_log('Conexión a base de datos fallida: ' . $e->getMessage());
        http_response_code(500);
        die('No se pudo conectar con la base de datos.');
    }
}
