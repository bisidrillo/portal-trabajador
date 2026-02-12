<?php
declare(strict_types=1);

// MariaDB connection via PDO. Configure using environment variables.
return (function (): PDO {
    // Synology Web Station: set credentials directly here.
    $host = "localhost";
    $port = "3306";
    $name = "portal-trabajadores";
    $user = "bisidrillo";
    $pass = "Nanillo-2026!";

    // Prefer unix socket on Synology if available (root often blocked over TCP).
    $socket_candidates = [
        "/run/mysqld/mysqld.sock",
        "/var/run/mysqld/mysqld.sock",
        "/var/run/mariadb/mariadb.sock",
    ];
    $socket = null;
    foreach ($socket_candidates as $cand) {
        if (is_readable($cand)) {
            $socket = $cand;
            break;
        }
    }

    if ($socket) {
        $dsn = "mysql:unix_socket={$socket};dbname={$name};charset=utf8mb4";
    } else {
        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
    }
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    return new PDO($dsn, $user, $pass, $options);
})();
