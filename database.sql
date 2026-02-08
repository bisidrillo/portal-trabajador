CREATE DATABASE IF NOT EXISTS portal_trabajador CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE portal_trabajador;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Usuario inicial (password: CambiaEstaClave123!)
INSERT INTO users (username, password_hash)
VALUES (
    'admin',
    '$2y$12$RQmT1zuYkBvdNiOfc.ybc.0l/0s0rqp22w0UQuQXVHZsoYDJdj3gO'
)
ON DUPLICATE KEY UPDATE username = VALUES(username);
